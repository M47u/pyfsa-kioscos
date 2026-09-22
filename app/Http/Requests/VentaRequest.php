<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación de una venta: un array de ítems (producto + cantidad) y un
 * medio de pago. cliente_id solo tiene sentido cuando medio_pago es
 * 'fiado' — se exige en ese caso (required_if) y se rechaza en cualquier
 * otro caso (prohibited_unless), en vez de aceptarlo y limpiarlo en
 * silencio.
 */
class VentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'medio_pago' => [
                'required',
                Rule::in([
                    Venta::MEDIO_PAGO_EFECTIVO,
                    Venta::MEDIO_PAGO_TRANSFERENCIA,
                    Venta::MEDIO_PAGO_DEBITO,
                    Venta::MEDIO_PAGO_QR,
                    Venta::MEDIO_PAGO_FIADO,
                ]),
            ],
            'cliente_id' => [
                'nullable',
                'integer',
                'required_if:medio_pago,'.Venta::MEDIO_PAGO_FIADO,
                'prohibited_unless:medio_pago,'.Venta::MEDIO_PAGO_FIADO,
                Rule::exists('clientes', 'id'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            // Offline (ver CLAUDE.md, arquitectura offline): lo genera
            // SIEMPRE el frontend (crypto.randomUUID()), tanto si la venta
            // se manda online al toque como si se encola en IndexedDB. Sin
            // 'unique' acá a propósito: un uuid repetido NO es un error de
            // validación, es un sync repetido — VentaController::store lo
            // trata como éxito idempotente en vez de rechazarlo (ver ahí).
            'uuid_dispositivo' => ['nullable', 'string', 'uuid'],
        ];
    }

    /**
     * Bloquea la venta completa (todo o nada, no se permite vender
     * parcialmente el carrito) si dejaría el stock de algún producto
     * negativo — a diferencia del límite de crédito de un cliente fiado,
     * que solo advierte (ver VentaController::store), acá SÍ es una
     * validación dura: el usuario confirmó explícitamente que el stock
     * negativo no es una decisión de negocio del kiosquero, es un estado
     * inválido del sistema.
     *
     * Vive acá y no en el controller porque la convención del proyecto es
     * que TODA validación de negocio de una venta vive en el FormRequest
     * (ver cliente_id/medio_pago arriba).
     *
     * IMPORTANTE — esto es un PRE-CHECK de UX, no la garantía real: es un
     * SELECT simple, sin lock, que corre ANTES de la transacción del
     * controller. Sirve para fallar rápido con un mensaje claro en el caso
     * común, pero bajo concurrencia (dos cajas vendiendo el mismo producto,
     * o un doble submit) dos requests pueden pasar este chequeo los dos
     * antes de que ninguno confirme. La garantía real contra condiciones de
     * carrera vive en VentaController::store, que vuelve a calcular el
     * stock con lockForUpdate() DENTRO de la transacción.
     *
     * Offline (ver CLAUDE.md, arquitectura offline): si la request trae
     * uuid_dispositivo (viene de la cola offline), este chequeo entero se
     * salta — decisión de negocio confirmada: una venta offline no pudo
     * validar el stock contra el servidor en el momento real de la venta
     * (el cliente ya se fue con el producto en mano), así que nunca se
     * rechaza acá. VentaController::store es quien decide, con el mismo
     * criterio, si además hay que marcarla sincronizada_con_stock_insuficiente.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('uuid_dispositivo')) {
                return;
            }

            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            // Un mismo producto puede aparecer en más de una fila del
            // carrito (caso real: el kiosquero lo escaneó dos veces en vez
            // de sumar la cantidad a mano). Hay que validar la cantidad
            // TOTAL pedida por producto contra el stock, no cada fila por
            // separado, o dos filas de 1 c/u contra un stock de 1
            // pasarían la validación aunque juntas pidan 2.
            $cantidadesPorProducto = collect($items)
                ->filter(fn ($item) => is_array($item) && isset($item['producto_id'], $item['cantidad']))
                ->groupBy('producto_id')
                ->map(fn ($filas) => (int) $filas->sum('cantidad'));

            if ($cantidadesPorProducto->isEmpty()) {
                return;
            }

            $productos = Producto::query()
                ->whereIn('id', $cantidadesPorProducto->keys())
                ->get()
                ->keyBy('id');

            foreach ($cantidadesPorProducto as $productoId => $cantidadPedida) {
                $producto = $productos->get($productoId);

                // Si el producto no existe, ya lo reporta la regla
                // Rule::exists de items.*.producto_id: no duplicar el error.
                if ($producto === null) {
                    continue;
                }

                // Control de stock opcional (ver Producto::controla_stock):
                // este producto nunca bloquea una venta por falta de stock,
                // sea cual sea la cantidad pedida.
                if (! $producto->controla_stock) {
                    continue;
                }

                $stockActual = $producto->stockActual();

                if ($cantidadPedida > $stockActual) {
                    $validator->errors()->add(
                        'items',
                        "No hay stock suficiente de {$producto->nombre}: quedan {$stockActual}, se pidieron {$cantidadPedida}."
                    );
                }
            }
        });
    }
}
