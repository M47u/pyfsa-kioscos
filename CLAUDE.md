# pyfsa-kioscos — Session Starter

Multi-tenant SaaS for kiosco/almacén businesses ("comercios"): the real stock/sales backend that the leads from the sibling project (`AsistenteClientesKiosko`, the lead scraper + WhatsApp outreach + fake demo) eventually get sold into. That project's `/demo/{lead}` is mock data only — this is where the real product lives.

Laravel 12, own git repo (`c:\xampp\htdocs\pyfsa-kioscos`), separate from `AsistenteClientesKiosko` and from the accidental repo at `c:\xampp\htdocs`. One commit so far: base scaffold + tenancy wiring.

## Architecture decisions (already made — don't relitigate)

- **Multi-tenancy**: `stancl/tenancy` v3.10, **database-per-tenant** (each Comercio gets its own real MySQL database, auto-created/dropped by the package).
- **Tenant model**: `App\Models\Comercio` (extends Stancl's `Tenant`, `implements TenantWithDatabase`, `use HasDatabase`) — named `Comercio` instead of `Tenant` because that's what it is in the business domain.
- **Tenant resolution: by authenticated user, NOT by subdomain.** Custom `App\Http\Middleware\InitializeTenancyByAuthenticatedUser` replaces the package's default `InitializeTenancyByDomain` in `routes/tenant.php`. It reads `$user->comercio_id` and calls `tenancy()->initialize()`.
  - **Why**: avoids configuring DNS/hosting per new comercio — no subdomain needed to onboard a client.
  - Central `users` table has a `comercio_id` column (string, FK → `tenants.id`, nullable, `nullOnDelete`) — string not `foreignId` because tenant ids are UUIDs.
- **Central DB** (auth + subscriptions + tenant registry): `pyfsa_kioscos_central`.
- **Gotcha de middleware — leer antes de agregar rutas con binding implícito**: `bootstrap/app.php` fuerza `InitializeTenancyByAuthenticatedUser` a correr antes que `SubstituteBindings` vía `$middleware->prependToPriorityList()`. Sin eso, el orden default de Laravel deja `SubstituteBindings` (parte del grupo `web`) corriendo ANTES que nuestro middleware de tenancy, así que cualquier ruta con binding implícito de Eloquent (`Producto $producto`, etc.) intenta resolver el modelo contra la base CENTRAL antes de que la tenancy esté inicializada → 500 en producción, con los tests en verde (`TenantTestCase::setUp()` inicializa tenancy a mano y enmascara el bug). Si agregás una ruta nueva con binding implícito dentro del grupo de `routes/tenant.php`, este fix ya la cubre — pero si tocás `bootstrap/app.php` o el orden de middlewares, revisá esto primero.

## Local environment — READ BEFORE TOUCHING MYSQL

This machine has **two separate MySQL-family servers** — easy to debug the wrong one:

| | Port | Status |
|---|---|---|
| XAMPP's bundled MariaDB 10.4 | 3307 | **Corrupted data directory** ("page in the future" InnoDB errors). Not used by any real project. Don't waste time on it. |
| Standalone MySQL 8.0.42 ("MySQL80" Windows service) | 3306 | **The real one.** `root`/`root`. Every Laravel project on this machine (this one, saasgym, gimnasio, votacion, etc.) runs against it. phpMyAdmin points here. |

**Always use `127.0.0.1:3306` / `root`/`root` for this project.**

Gotcha: the `mysql` CLI at `c:/xampp/mysql/bin/mysql.exe` is MariaDB's client and **can't authenticate against MySQL 8** (missing `caching_sha2_password` plugin). For manual DB work use:
```
"c:/Program Files/MySQL/MySQL Server 8.0/bin/mysql.exe"
```

(Harmless leftover: `skip-name-resolve` was added to `c:/xampp/mysql/bin/my.ini` while debugging the unused MariaDB instance — no functional impact, just noted in case it's revisited.)

**Gotcha — el CSS se queda viejo sin avisar**: `public/build/` (gitignored, nunca se commitea) se genera con `npm run build`, y Tailwind v4 solo mete en el bundle las clases que encuentra escaneando los archivos Blade que existen AL MOMENTO de compilar. `php artisan test` nunca toca esto — un agente puede agregar vistas nuevas, correr toda la suite en verde, y el CSS de esas vistas simplemente no existe hasta que alguien corre `npm run build` a mano. Ya pasó una vez (5/9/2026: las vistas de Fiado y del panel/nav se crearon horas después del último build, y se veían sin ningún estilo — HTML crudo). Corré `npm run build` después de cualquier tanda de trabajo que toque `resources/views/**` o `resources/css/**`, antes de dar por buena una revisión visual.

**Gotcha — comercios ya creados no se re-migran solos**: `stancl/tenancy` corre las migraciones de `database/migrations/tenant/` automáticamente solo cuando CREA un comercio nuevo. Si agregás una migración de tenant nueva (como pasó con `clientes`/`ventas`/`items_venta`/`pagos`, agregadas en sesiones distintas) y ya existía un comercio de antes, su base de datos se queda con el esquema viejo — el síntoma es un `Base table or view not found` recién al navegar a esa sección, con todo lo demás funcionando bien. Solución: `php artisan tenants:migrate` corre las migraciones pendientes contra TODOS los comercios existentes. Correlo después de cualquier migración de tenant nueva. (Y si `php artisan tenants:list` muestra un comercio cuya base de datos ya no existe — huérfano de alguna prueba anterior — hay que borrar esa fila de la tabla `tenants` central con una query directa, `Comercio::find($id)->delete()` intenta un `DROP DATABASE` que va a fallar si la base ya no está.)

## Verified working

- Creating a `Comercio` auto-creates a real MySQL database (`tenantUUID`); deleting it auto-drops that database. Tested live, and now also exercised by the test suite (see Testing below).
- Auth scaffolding present: `AuthenticatedSessionController` (login/logout), `/login` + `/logout` routes.
- `/panel` is a real dashboard now (`PanelController`): welcome message with the comercio id + shortcut cards to Productos/Clientes/Ventas. No aggregated data/stats yet — that's the separate, not-yet-built Reportes module.
- **Shared layout + persistent nav**: `resources/views/layouts/app.blade.php` (classic `@extends`/`@yield` — chosen over anonymous-component slots because every existing view already used plain Blade `@include` for partials, e.g. `productos._form`, so `@extends` kept the same idiom instead of mixing two layout mechanisms) holds the `<!DOCTYPE>`/`<head>`/`@vite` boilerplate and two `@yield` hooks (`body-class`, `container-class`) so each view can still control its own body alignment (centered form vs. top-aligned list) and max-width without repeating markup. `resources/views/layouts/nav.blade.php` is included only inside `@auth` (so `/login` never renders it, no separate flag needed) and highlights the active section with `request()->routeIs(...)`. The two duplicated banners became anonymous Blade components: `<x-status-banner />` (`session('status')`, green) and `<x-validation-errors />` (`$errors->any()`, red) — anonymous components chosen over `@include` for these specifically because they take no parameters and read straight from the view's implicit state, so a self-closing tag reads cleaner than `@include('components.foo')`. All 8 pre-existing views + `auth/login.blade.php` were migrated to extend the layout; no visual/markup change to the page content itself, only how it's wrapped.
- **Stock**: `Producto` (alta con nombre/código de barras/precio costo/precio venta/stock mínimo), stock calculado por `MovimientoStock` (nunca editable directamente), `bajoMinimo()`, búsqueda por nombre o código de barras. Reposición de stock vía `ProductoController::reponerStock`.
- **Ventas + Cliente (mínimo)**: `VentaController` registra una venta con carrito de ítems (online, síncrono — nada de offline todavía), medios de pago efectivo/transferencia/fiado, dentro de una transacción: crea la `Venta`, cada `ItemVenta` con snapshot de `precio_unitario`, y un `MovimientoStock` tipo `venta` con cantidad negativa por ítem. La venta SE BLOQUEA si dejaría el stock de algún producto negativo, en dos capas: `VentaRequest::withValidator` es un pre-check de UX (SELECT simple, sin lock, falla rápido con buen mensaje) y `VentaController::store` es la garantía real contra condiciones de carrera — DENTRO de la transacción trae los productos con `lockForUpdate()` y recalcula el stock antes de crear nada; si no alcanza, lanza `ValidationException::withMessages(['items' => ...])` (mismo tratamiento que cualquier otro error de validación de la app, sin catch manual). Ambas capas agrupan cantidades por producto sumando filas repetidas del carrito — todo o nada, no se vende parcialmente el carrito. `Cliente` mínimo (nombre/teléfono/límite de crédito) con `saldo()` calculado como sus ventas fiadas menos sus pagos.
- **Fiado (módulo completo)**: `Pago` (cliente_id, monto, user_id sin FK — mismo patrón que `movimientos_stock`/`ventas`) resta del saldo del cliente. `Cliente::saldo() = ventas fiadas - pagos` (puede dar negativo si pagó de más, es información válida, no se clampea). `Cliente::superaLimite(?float $saldo = null): bool` (mismo patrón que `Producto::bajoMinimo()`, solo alerta) acepta un saldo ya calculado opcionalmente para que los call sites que ya lo necesitaron no lo recalculen de cero. A diferencia del stock, el límite de crédito de fiado **NO bloquea** la venta — `VentaController::store` deja un flash `advertencia` (no bloqueante) si el cliente queda por encima de su límite después de una venta fiada. `ClienteController::show` arma en memoria (sin tabla propia) el historial cronológico mezclando `ventas` fiadas + `pagos`, más reciente primero. `PagoRequest` valida `monto` (numeric, min:0.01, max:99999999.99 — tope real de la columna `decimal(10,2)`).

## Testing

- `phpunit.xml` corre contra MySQL 8 real en `127.0.0.1:3306` (usuario `root`/`root`), base central de testing `pyfsa_kioscos_central_testing` — **nunca** sqlite in-memory, porque `stancl/tenancy` es database-per-tenant sobre MySQL real: crear un `Comercio` en un test dispara `CreateDatabase`/`MigrateDatabase` de verdad contra el servidor. La base `pyfsa_kioscos_central_testing` tiene que existir de antemano (se crea una sola vez a mano, RefreshDatabase solo corre las migraciones).
- `tests/Feature/TenantTestCase.php` es la base para tests de features tenant-scoped: crea un `Comercio` + `User` reales, inicializa la tenancy en `setUp()`, y en `tearDown()` termina la tenancy y borra el `Comercio` (dispara `DeleteDatabase`, limpia la base del tenant de verdad). Cada test corre contra su propia base de datos MySQL real, creada y destruida al vuelo.
- `php artisan test` — 30 tests pasando (95 assertions). Producto: alta, reposición, bajo mínimo, búsqueda por nombre/código (con escape de comodines `%`/`_`), stock_minimo vacío usa default 0, y una regresión clave que NO reusa la tenancy pre-inicializada de `TenantTestCase` (termina tenancy a mano y deja que el middleware real la reinicialice dentro del pipeline HTTP) para poder detectar el gotcha de middleware de arriba. Cliente: alta, límite de crédito vacío usa default 0, vista de cuenta corriente (`show`) con alerta de límite superado. Venta: efectivo, fiado, fiado sin cliente falla, medio de pago inválido falla, venta que pide más stock del disponible se rechaza sin crear nada, venta que pide exactamente el stock disponible se acepta, carrito con producto repetido suma cantidades para validar stock, y una prueba de concurrencia real: abre una SEGUNDA conexión MySQL (su propia sesión, no la de Eloquent) que toma `lockForUpdate()` sobre el producto sin confirmar, y confirma que `VentaController::store` queda bloqueado por MySQL de verdad (`innodb_lock_wait_timeout` bajado a 1s para el test) en vez de pasar de largo — prueba que el lock de la condición de carrera es real, no solo el pre-check del Request. Pago: alta reduce el saldo, pago parcial deja saldo pendiente, pago total deja saldo en cero, monto cero falla validación, monto que desborda `decimal(10,2)` falla validación, `superaLimite()` true/false según saldo vs. límite, venta fiado que supera el límite se registra igual y deja advertencia, y la misma regresión de binding de tenant sin tenancy pre-inicializada aplicada a la ruta nueva de pagos. Panel: `GET /panel` responde 200 autenticado y muestra el id del comercio, y un usuario no autenticado es redirigido a `/login`. Welcome: `GET /` responde 200 (cobertura mínima de la ruta pública, fuera de cualquier tenant).

## Key files

- `app/Models/Comercio.php`, `app/Models/User.php`
- `app/Http/Middleware/InitializeTenancyByAuthenticatedUser.php`
- `bootstrap/app.php` — `prependToPriorityList()` forces the tenancy middleware to run before `SubstituteBindings` (see gotcha above)
- `config/tenancy.php` — `tenant_model` points at `Comercio`
- `routes/tenant.php`, `routes/web.php`
- `database/migrations/2026_09_05_022300_add_comercio_id_to_users_table.php`
- `bootstrap/providers.php` — `TenancyServiceProvider` registered
- Stock: `app/Models/{Producto,MovimientoStock}.php`, `app/Http/Controllers/ProductoController.php`, `database/migrations/tenant/2026_09_04_120000_*` and `_120100_*`
- Ventas/Cliente: `app/Models/{Venta,ItemVenta,Cliente}.php`, `app/Http/Controllers/{VentaController,ClienteController}.php`, `database/migrations/tenant/2026_09_04_130000_*` through `_130200_*`
- Fiado: `app/Models/Pago.php`, `app/Http/Requests/PagoRequest.php`, `database/migrations/tenant/2026_09_05_090000_create_pagos_table.php`, `resources/views/clientes/show.blade.php`, `tests/Feature/PagoTest.php`
- Panel + layout compartido: `app/Http/Controllers/PanelController.php`, `resources/views/panel.blade.php`, `resources/views/layouts/{app,nav}.blade.php`, `resources/views/components/{status-banner,validation-errors}.blade.php`, `tests/Feature/PanelTest.php`
- `tests/Feature/TenantTestCase.php` — base para tests contra un tenant MySQL real

## Scope document

Full spec ("Sistema de Gestión para Kioscos") is published as a Claude Artifact — this is what code comments mean by "el documento de alcance":
https://claude.ai/code/artifact/ea5c932c-7198-497d-b8cf-ea9173963852

Known scaling note already recorded there (section 04, Decisiones de arquitectura): Hostinger shared hosting caps MySQL at **75 simultaneous connections per user**, shared across ALL tenant databases (database-per-tenant architecture). Fine to launch and validate with early customers; will need a VPS/dedicated DB server before hitting ~100+ concurrently-active comercios (target market: Formosa + Alberdi, Paraguay). Not an issue to design around now — noted for later.

## Status / Next steps

- Base scaffold + tenancy is done and verified.
- **Stock**, **Ventas** (con bloqueo de stock negativo) and **Fiado (módulo completo)** are implemented and covered by feature tests (see Testing above).
- **Still pending**: Reportes (ventas, stock, cuenta corriente agregada), Usuarios/roles (hoy cualquier user autenticado del comercio puede todo), offline mode for Ventas (Service Worker/IndexedDB/UUID de dispositivo/Alpine.js — explicitly out of scope for the online/sync version built so far), comercio onboarding UI, subscription/billing logic.
- `/panel` + shared layout/nav are done (see above) — `/panel` is a real dashboard, not a placeholder anymore.
- Check the scope Artifact above for the actual feature/requirements breakdown before starting new work.
