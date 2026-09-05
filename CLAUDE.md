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

## Verified working

- Creating a `Comercio` auto-creates a real MySQL database (`tenantUUID`); deleting it auto-drops that database. Tested live, and now also exercised by the test suite (see Testing below).
- Auth scaffolding present: `AuthenticatedSessionController` (login/logout), `/login` + `/logout` routes.
- `/panel` route (tenant-scoped, behind `auth` + the custom middleware) currently just echoes `tenant('id')` — placeholder only.
- **Stock**: `Producto` (alta con nombre/código de barras/precio costo/precio venta/stock mínimo), stock calculado por `MovimientoStock` (nunca editable directamente), `bajoMinimo()`, búsqueda por nombre o código de barras. Reposición de stock vía `ProductoController::reponerStock`.
- **Ventas + Cliente (mínimo)**: `VentaController` registra una venta con carrito de ítems (online, síncrono — nada de offline todavía), medios de pago efectivo/transferencia/fiado, dentro de una transacción: crea la `Venta`, cada `ItemVenta` con snapshot de `precio_unitario`, y un `MovimientoStock` tipo `venta` con cantidad negativa por ítem. La venta SE BLOQUEA si dejaría el stock de algún producto negativo (`VentaRequest::withValidator`, agrupa cantidades por producto sumando filas repetidas del carrito) — todo o nada, no se vende parcialmente el carrito. `Cliente` mínimo (nombre/teléfono/límite de crédito) con `saldo()` calculado como la suma de sus ventas fiadas (sin módulo de Pagos todavía, así que no hay nada que restar).

## Testing

- `phpunit.xml` corre contra MySQL 8 real en `127.0.0.1:3306` (usuario `root`/`root`), base central de testing `pyfsa_kioscos_central_testing` — **nunca** sqlite in-memory, porque `stancl/tenancy` es database-per-tenant sobre MySQL real: crear un `Comercio` en un test dispara `CreateDatabase`/`MigrateDatabase` de verdad contra el servidor. La base `pyfsa_kioscos_central_testing` tiene que existir de antemano (se crea una sola vez a mano, RefreshDatabase solo corre las migraciones).
- `tests/Feature/TenantTestCase.php` es la base para tests de features tenant-scoped: crea un `Comercio` + `User` reales, inicializa la tenancy en `setUp()`, y en `tearDown()` termina la tenancy y borra el `Comercio` (dispara `DeleteDatabase`, limpia la base del tenant de verdad). Cada test corre contra su propia base de datos MySQL real, creada y destruida al vuelo.
- `php artisan test` — 18 tests pasando (64 assertions). Producto: alta, reposición, bajo mínimo, búsqueda por nombre/código (con escape de comodines `%`/`_`), stock_minimo vacío usa default 0, y una regresión clave que NO reusa la tenancy pre-inicializada de `TenantTestCase` (termina tenancy a mano y deja que el middleware real la reinicialice dentro del pipeline HTTP) para poder detectar el gotcha de middleware de arriba. Cliente: alta, límite de crédito vacío usa default 0. Venta: efectivo, fiado, fiado sin cliente falla, medio de pago inválido falla, venta que pide más stock del disponible se rechaza sin crear nada, venta que pide exactamente el stock disponible se acepta, carrito con producto repetido suma cantidades para validar stock. Welcome: `GET /` responde 200 (cobertura mínima de la ruta pública, fuera de cualquier tenant).

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
- `tests/Feature/TenantTestCase.php` — base para tests contra un tenant MySQL real

## Scope document

Full spec ("Sistema de Gestión para Kioscos") is published as a Claude Artifact — this is what code comments mean by "el documento de alcance":
https://claude.ai/code/artifact/ea5c932c-7198-497d-b8cf-ea9173963852

Known scaling note already recorded there (section 04, Decisiones de arquitectura): Hostinger shared hosting caps MySQL at **75 simultaneous connections per user**, shared across ALL tenant databases (database-per-tenant architecture). Fine to launch and validate with early customers; will need a VPS/dedicated DB server before hitting ~100+ concurrently-active comercios (target market: Formosa + Alberdi, Paraguay). Not an issue to design around now — noted for later.

## Status / Next steps

- Base scaffold + tenancy is done and verified.
- **Stock** and **Ventas + Cliente (mínimo)** are implemented and covered by feature tests (see Testing above). Ventas now blocks any sale that would leave a product's stock negative. This is the minimum Cliente needs for Ventas to work — not the full Fiado module.
- **Still pending**: full Fiado module (registro de pagos, alerta de límite de crédito superado, reportes de cuenta corriente), offline mode for Ventas (Service Worker/IndexedDB/UUID de dispositivo/Alpine.js — explicitly out of scope for the online/sync version built so far), comercio onboarding UI, subscription/billing logic.
- `/panel` is still a placeholder route — no dashboard/nav wiring yet between `/panel`, Productos, Clientes and Ventas.
- Check the scope Artifact above for the actual feature/requirements breakdown before starting new work.
