# Módulos

Cada cliente tiene el mismo código; lo que cambia es qué módulos tiene encendidos. Todo el código y todas las migraciones están siempre: apagar un módulo oculta la función, **no borra datos**. Solo el SuperAdmin enciende y apaga módulos (panel `superadmin` → Módulos, o por consola).

## Estado y comandos

- Tabla `modules` (`key`, `enabled`, `settings`, `enabled_at`). Una fila ausente es un módulo apagado.
- `App\Services\Modules` (fachada `App\Facades\Modules`): `enabled()`, `enable()`, `disable()`, `settings()`, `setting()`, `updateSettings()`, `enableAll()`. El estado se cachea y se limpia en cada cambio.
- Reglas: no se enciende sin los módulos que requiere ni con configuración del servidor faltante (Login con Google sin credenciales); no se apaga si otro módulo encendido depende de él.

```bash
php artisan modules:list
php artisan modules:enable stock
php artisan modules:enable --all     # todo lo que se pueda, en orden de dependencia
php artisan modules:disable stock
```

`php artisan db:seed` no enciende ningún módulo (en un cliente nuevo se habilitan según su abono). `composer demo` enciende todos los posibles.

## Los cinco puntos de corte

Cada tipo de cosa se corta en un solo lugar. Cuando un recurso, página, ruta o campo pertenece a un módulo, **tiene que** usar uno de estos puntos.

| Qué | Cómo |
|---|---|
| Recursos y páginas de Filament | `use BelongsToModule;` y `protected static function module(): Module`. Oculta la navegación, bloquea el acceso y la ruta responde 404. Las policies siguen aplicando encima. No redefinir `canAccess()` ni `shouldRegisterNavigation()` en la clase: taparía el corte (lo verifica el test de arquitectura). |
| Widgets de Filament | `use WidgetBelongsToModule;` y `module()`. Maneja `canView()`. |
| Relation managers de Filament | `use RelationManagerBelongsToModule;` y `module()`. Maneja `canViewForRecord()`. |
| Rutas | `->middleware('module:stock')`: 404 con el módulo apagado o con una clave inexistente. |
| Blade | `@module('stock') … @endmodule` |
| Campos y columnas de otro módulo | `->visible(fn () => Modules::enabled(Module::Stock))` |
| Tareas programadas | `->when(fn () => Modules::enabled(Module::Stock))` |

Las acciones de negocio del núcleo no llaman directamente a un módulo: preguntan `Modules::enabled()` o disparan un evento que el módulo escucha. Las columnas que un módulo agrega a una tabla del núcleo existen siempre y son opcionales.

### Páginas que piden más de un módulo

Si una página necesita otro módulo además del suyo (un informe de Compras pide también Informes), la clase declara el principal con `module()` y el resto sobrescribiendo `alsoRequires(): array` (por ejemplo `return [Module::Purchases];`). El menú, el acceso y el middleware de la ruta exigen todos. No hay que redefinir `canAccess()`. La ruta de impresión que no es de Filament agrega el mismo `module:` o lo verifica en el controlador.

### Páginas y widgets solo para el administrador

Como el trait del módulo ocupa `canAccess()` / `canView()`, lo que además es solo del administrador extiende una base que lo resuelve (`AdministratorPage`, `AdministratorStatsWidget`, `AdministratorTableWidget`) y suma el trait del módulo: `class StockCount extends AdministratorPage { use BelongsToModule; … }`.

## Cómo reacciona un módulo a la venta

El núcleo no llama a los módulos: dispara eventos. `SaleCreated` y `SaleCancelled` (con la venta y el usuario que actúa) se disparan **dentro de la transacción** de `CreateSale` / `CancelSale`. Un listener sincrónico en `app/Listeners` (auto-descubierto) hace el efecto del módulo; si lanza una excepción (por ejemplo `ValidationException` cuando Stock está en modo bloquear), la venta completa se revierte. Cada listener empieza con `if (! Modules::enabled(...)) return;`, salvo los que deben revertir lo ya registrado (la devolución de stock mira los movimientos de esa venta, no el estado del módulo).

Cuenta corriente sigue el mismo camino: `RegisterAccountForSale` arma el vencimiento y asienta el libro del cliente cuando la venta deja saldo, y `ReverseAccountForCancelledSale` lo revierte al anular (y rechaza la anulación si ya hay cobros imputados). Lo único que el núcleo pregunta directamente es `Modules::enabled(CurrentAccount)` en `CreateSale`, para admitir cobros parciales o ninguno y exigir cliente y vencimiento cuando queda saldo.

## Cómo agregar un módulo

1. Agregar el caso a `App\Enums\Module` (después de los que requiere) con etiqueta, descripción, `requires()`, `missingConfiguration()` y `defaultSettings()` si corresponde.
2. Si tiene ajustes propios, sumar sus campos en `App\Filament\Superadmin\Schemas\ModuleSettingsForm`.
3. Poner cada recurso, página, widget, ruta y campo del módulo detrás del punto de corte que corresponda.
4. Tests: el módulo apagado oculta todo lo anterior y encendido lo muestra.

## Tests

La suite tiene que pasar con solo el núcleo y con todos los módulos encendidos. `composer test` corre las dos pasadas (`TEST_MODULES=none` y `TEST_MODULES=all`); `composer test:core` y `composer test:all-modules` corren una sola. Los tests de módulos parten de estado limpio con `resetModules()`.

El test `ArquitecturaTest` falla si aparece un recurso, página, widget o relation manager del panel `admin` que no figure en la lista del núcleo y no use el trait de su módulo (ni lo redefina).
