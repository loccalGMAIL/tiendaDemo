# Plan base — Plataforma de gestión comercial con panel, landing y módulos habilitables

Especificación para construir desde cero un **repositorio común** del que sale cada cliente nuevo.
Cada cliente es una instalación propia (sus dominios, su base, su `.env`) con el mismo código; lo
que cambia es **qué módulos tiene habilitados** según su abono, y eso lo decide únicamente el
SuperAdmin de la plataforma desde un panel aparte.

Este documento es autosuficiente: no depende de ningún proyecto anterior. Quien lo ejecute (una
persona o una IA) empieza por la sección 1.

---

## 1. Antes de empezar: preguntas obligatorias

No escribir código hasta tener estas respuestas. Si falta alguna, **preguntar**; no suponer.

1. **Nombre de la plataforma y del repositorio.**
2. **Dominios.** Hacen falta tres hosts (landing, panel, superadmin). Preguntar cuáles serán en
   producción o, si todavía no existen, qué forma tendrán (por ejemplo `negocio.com`,
   `app.negocio.com`, `plataforma.negocio.com`). Para local se usan `*.localhost` (ver §4).
3. **Primer cliente o instalación de demostración:** nombre del negocio, rubro, logo si hay.
4. **Módulos a habilitar en la primera instalación** (ver §7).
5. **Parámetros de venta** (solo los de los módulos que se habiliten):
   - Medios de pago que acepta y, para cada uno, cantidad de cuotas y recargo o descuento
     (por ejemplo: efectivo, transferencia, tarjeta en 3 cuotas con 15 %).
   - ¿Una venta se puede pagar con más de un medio a la vez?
   - ¿Los productos tienen variantes (talle, color)? ¿El precio es por producto o por variante?
   - ¿Se vende solo de contado, o también a cuenta (financiación propia del comercio)?
   - Cuotas: ¿vencen un día fijo del mes o el mismo día de la venta? ¿Los planes llevan recargo?
   - ¿El vendedor puede cambiar el precio al vender?
   - ¿El vendedor ve solo sus ventas o todas?
   - Stock: ¿se bloquea la venta sin stock o solo se avisa?
6. **Servidor:** ¿hay un proxy con SSL delante (Traefik, Nginx)? ¿El servidor tiene Node para
   compilar, o los assets se compilan antes y se suben?
7. **Login con Google:** ¿están las credenciales OAuth del cliente? (si no, el módulo queda apagado).

Valores por defecto si el usuario dice "lo estándar": español `es_AR`, pesos argentinos, zona
`America/Argentina/Buenos_Aires`, MySQL, venta de contado y a cuenta, vendedor ve solo sus ventas.

---

## 2. Decisiones de arquitectura

| Tema | Decisión |
|---|---|
| Repositorio | Uno solo para todos los clientes. Sin ramas ni forks por cliente. |
| Instalación | Una por cliente (single-tenant): base de datos, `.env` y dominios propios. |
| Diferencias entre clientes | Solo en `.env`, en la base (módulos, ajustes, contenido) y en `storage` (logo, imágenes). Nunca en el código. |
| Módulos | Interruptores en la base de datos. Todo el código y **todas las migraciones** están siempre; apagar un módulo oculta la función pero no borra datos. |
| Quién habilita | Rol **SuperAdmin**, con panel propio en un tercer dominio. Es invisible para todos los usuarios del cliente, incluido el dueño del negocio y cualquier administrador. |
| Apariencia | Tema gráfico, logotipos, favicon y recursos gráficos se eligen y cargan desde el panel `superadmin` y son comunes a toda instalación. Lo habilitable es que el cliente pueda cambiar la combinación de colores. |
| Ventas | Parte del núcleo, con medios de pago configurables (cuotas con tarjeta y recargo incluidos). Cuenta corriente, Cuotas propias, Compras, Stock y Variantes son módulos. |
| Productos | Todo producto tiene al menos una variante; ventas, compras y stock apuntan siempre a la variante. Sin el módulo Variantes hay una sola, implícita, y no se ve. |
| Estructura | La estándar de Laravel. Sin paquetes de "módulos" ni carpetas base nuevas. |

---

## 3. Stack

Versiones con las que este diseño está probado. Al crear el proyecto se instala la última estable
de cada una y se confirma con `composer show --direct`; ante cualquier duda de API se consulta la
documentación de la versión instalada (herramienta `search-docs` de Laravel Boost).

| Pieza | Versión de referencia | Uso |
|---|---|---|
| PHP | 8.3 (extensiones `intl`, `bcmath`, `pdo_mysql`, `mbstring`, `gd`, `zip`) | |
| Laravel | 13 | Framework |
| Filament | 5 | Paneles `admin` y `superadmin` |
| MySQL | 8 | Producción y desarrollo |
| SQLite en memoria | — | Tests (`phpunit.xml`) |
| Tailwind + Vite | 4 / 8 | Landing y tema de Filament |
| Pest | 4 | Tests |
| Larastan | 3, nivel 5 | Análisis estático |
| Pint | 1 | Formato |
| Laravel Boost | 2 | MCP, guidelines y skills para trabajar con IA |
| Laravel Socialite | última estable (sin versión de referencia probada) | Módulo Login con Google |

Sin otras dependencias. Cualquier paquete nuevo requiere aprobación.

---

## 4. Arranque del repositorio

### Comandos

```bash
composer create-project laravel/laravel .
composer require filament/filament laravel/socialite
php artisan filament:install --panels            # crea el panel "admin"
php artisan make:filament-panel superadmin
php artisan make:filament-theme admin            # tema propio (tablas compactas, variables de color)
composer require --dev larastan/larastan laravel/boost
composer remove --dev phpunit/phpunit
composer require --dev pestphp/pest pestphp/pest-plugin-laravel --with-all-dependencies
vendor/bin/pest --init
php artisan boost:install                        # MCP, guidelines (CLAUDE.md / AGENTS.md) y skills
php artisan lang:publish                         # y agregar lang/es
npm install && npm run build
```

Scripts de `composer.json`: `analyse` (`phpstan analyse --memory-limit=1G`), `lint` (`pint`),
`test`, `setup` (instala, copia `.env`, genera clave, migra, compila) y `demo`
(`migrate:fresh --seed` + seeder de demostración).

`phpstan.neon`: incluye la extensión de Larastan, `level: 5`, rutas `app`, `database`, `routes`.

### Variables de entorno (`.env.example` completo y comentado)

```dotenv
APP_NAME="Nombre del negocio"
APP_URL=http://app.negocio.localhost:8000
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_AR

DB_CONNECTION=mysql
DB_DATABASE=negocio

SESSION_DRIVER=database
SESSION_DOMAIN=null          # cada host tiene su propia sesión: no se comparte entre paneles
CACHE_STORE=database
QUEUE_CONNECTION=database

# Dominios (config/domains.php). Ningún host va escrito en el código.
LANDING_DOMAIN=negocio.localhost
PANEL_DOMAIN=app.negocio.localhost
SUPERADMIN_DOMAIN=plataforma.negocio.localhost

# Proxy con SSL delante de la app: IPs/CIDR separadas por coma, o * para confiar en todos.
TRUSTED_PROXIES=*

# Usuarios iniciales (los crea el seeder; se pueden volver a correr sin duplicar)
ADMIN_NAME=Administrador
ADMIN_EMAIL=
ADMIN_PASSWORD=
SUPERADMIN_NAME=
SUPERADMIN_EMAIL=
SUPERADMIN_PASSWORD=

# Módulo Login con Google (vacío = el módulo no se puede habilitar)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="https://${PANEL_DOMAIN}/auth/google/callback"
```

En producción además: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
`LOG_LEVEL=warning`.

### Configuración

- `config/app.php`: `timezone` según la respuesta de §1 (por defecto Buenos Aires).
- `config/domains.php`: `landing`, `panel`, `superadmin` leídos del `.env`.
- `config/platform.php`: `version` (semántica, igual al `CHANGELOG.md` y al tag de git) y los
  usuarios iniciales.
- `config/services.php`: bloque `google` con `client_id`, `client_secret`, `redirect`.
- `bootstrap/app.php`: `trustProxies` con `TRUSTED_PROXIES` (sin esto, detrás de un proxy con SSL
  los enlaces y assets salen con `http://`).
- Local sin tocar el archivo `hosts`: los navegadores resuelven `*.localhost` a 127.0.0.1 y
  `Route::domain` ignora el puerto, así que alcanza con `php artisan serve`.

### Login con Google: alta de credenciales

En Google Cloud Console → APIs y servicios → Credenciales → ID de cliente OAuth (aplicación web),
con URI de redirección `https://{PANEL_DOMAIN}/auth/google/callback`. Una credencial por cliente.

---

## 5. Arquitectura

### Tres dominios, una aplicación

| Dominio | Qué sirve | Quién entra |
|---|---|---|
| `LANDING_DOMAIN` | Landing pública y catálogo (si el módulo está activo) | Cualquiera |
| `PANEL_DOMAIN` | Panel Filament `admin`, en la raíz (`->path('')`) | Administradores y vendedores del cliente |
| `SUPERADMIN_DOMAIN` | Panel Filament `superadmin`, en la raíz | Solo SuperAdmin |

- Las rutas públicas van dentro de `Route::domain(config('domains.landing'))`.
- Cualquier otro host devuelve 404.

### Roles

| Rol | Alcance |
|---|---|
| `superadmin` | Solo el panel `superadmin`. No opera el negocio. |
| `admin` | Todo el panel `admin`: maestros, ventas, cobros, compras, informes, usuarios, anulaciones. |
| `seller` | Crea ventas y cobros, ve clientes y productos. Ve solo sus ventas (configurable). No anula, no ve informes ni costos de compra. |

`User::canAccessPanel()` decide por panel: `superadmin` solo para ese rol; `admin` para usuarios
activos con rol `admin` o `seller`. Un usuario inactivo no entra a ningún panel.

### SuperAdmin invisible

- No aparece en el listado de usuarios ni en selectores (vendedor, cobrador, filtros de informes).
- No se puede ver, editar ni borrar desde el panel `admin`; su URL de edición responde 404.
- Sus acciones no se muestran en el registro de actividad que ve el cliente.
- "SuperAdmin" no existe como opción al elegir el rol de un usuario.
- El panel `admin` no enlaza ni menciona al panel `superadmin`.
- En el login del panel `superadmin`, un usuario de otro rol recibe el mismo mensaje que con
  credenciales incorrectas.
- Se resuelve en un solo lugar: un scope que usan todas las consultas de usuarios del panel `admin`.

### Módulos

1. **`App\Enums\Module`** — lista cerrada. Cada caso declara etiqueta, descripción, `requires()`
   (módulos de los que depende) y `missingConfiguration()` (por ejemplo, Google sin credenciales).
2. **Tabla `modules`** — `key` (único), `enabled`, `settings` (json), `enabled_at`.
3. **`App\Services\Modules`** — `enabled(Module)`, `enable()`, `disable()`, `setting()`. Valida
   dependencias en ambos sentidos (no se enciende sin lo que requiere; no se apaga si otro
   encendido depende de él). Cacheado; se limpia al cambiar.
4. **Puntos de corte**, uno por tipo de cosa:
   - Recursos, páginas y widgets de Filament: trait `BelongsToModule` que resuelve `canAccess()`
     (y con eso la navegación). Las policies siguen aplicando encima.
   - Rutas: middleware `module:{key}` → 404 si está apagado.
   - Blade: directiva `@module('key') … @endmodule`.
   - Campos de formulario y columnas de otro módulo: `->visible(fn () => Modules::enabled(...))`.
   - Tareas programadas: `->when(...)`.
5. **Panel `superadmin`** — lista de módulos con interruptor, dependencias, aviso de configuración
   faltante y los ajustes propios de cada módulo.
6. **CLI** — `php artisan modules:list`, `modules:enable {key}`, `modules:disable {key}`.

Reglas:

- Un módulo apagado no rompe a otro. Las columnas que un módulo agrega a una tabla del núcleo
  existen siempre y son opcionales.
- Las acciones de negocio del núcleo no llaman directamente a un módulo: preguntan
  `Modules::enabled()` o disparan un evento que el módulo escucha.
- La suite de tests tiene que pasar **con todos los módulos encendidos y con solo el núcleo**.

---

## 6. Núcleo (siempre presente)

### Datos

Montos: `decimal(12,2)`, tratados en PHP como string y operados con `bcmath`.

- **users** — `name`, `email` (único), `password`, `role`, `google_id` (único, opcional), `active`,
  `is_default_seller`.
- **customers** — `name`, `document_number` (único, opcional), `phone`, `email`, `address`, `city`,
  `notes`, `active`.
- **categories** — `name`, `slug` (único), `position`, `active`.
- **products** — `sku` (único; si se deja vacío se asigna `PROD-00001`, `PROD-00002`, …), `name`,
  `brand`, `description`, `category_id`, `price` (precio de venta), `cost` (último costo), `active`.
  Columnas opcionales de módulos: `supplier_id` (Compras); `slug`, `is_published`, `show_price`,
  `images` json (Catálogo).
- **product_variants** — `product_id`, `name` (por ejemplo "M / Negro"; vacío en la variante
  implícita), `attributes` json (`{"talle": "M", "color": "Negro"}`), `sku` (único, opcional),
  `price` (opcional: si está vacío vale el del producto), `active`, `position`. Columnas del
  módulo Stock: `stock`, `min_stock`. Al crear un producto se crea su variante implícita.
- **payment_methods** — `name`, `installments` (por defecto 1), `adjustment_percent` (positivo =
  recargo, negativo = descuento, por defecto 0), `active`, `position`. "Tarjeta 3 cuotas" y
  "Tarjeta 6 cuotas" son dos filas. Se cargan por seeder Efectivo y Transferencia.
- **sales** — `customer_id` (opcional: consumidor final), `seller_id`, `date`, `subtotal` (ítems),
  `adjustment` (recargos y descuentos de los medios de pago), `total`, `total_cost`,
  `down_payment` (lo cobrado al vender), `financed_amount` (lo que quedó a cobrar),
  `financing_plan_id`, `installments_count`, `first_due_date` (los tres opcionales), `status`
  (`active` | `paid` | `cancelled`), `notes`, `cancelled_at`, `cancelled_by`, `cancellation_reason`.
- **sale_items** — `sale_id`, `product_id`, `product_variant_id`, `description` (nombre del
  producto y la variante al vender), `quantity`, `unit_cost`, `unit_price`, `subtotal`.
- **payments** — `customer_id` (opcional), `sale_id` (cobro hecho al vender), `date`,
  `payment_method_id`, `installments` y `adjustment_percent` (copiados del medio de pago),
  `base_amount`, `adjustment_amount`, `amount` (base + ajuste), `collected_by`, `notes`, campos de
  anulación.
- **activity_logs** — `user_id`, `event` (`login`, `logout`, `login_failed`, `created`, `updated`,
  `deleted`, `cancelled`), `description`, `subject` (morph), `properties` json, `ip_address`,
  `user_agent`, `created_at`. Sin `updated_at`: es inmutable.
- **table_column_preferences** — `user_id`, `table`, `columns` json; único por usuario y tabla.
- **modules** y **settings** (`key`, `value` json: contenido del sitio y apariencia).

### Reglas de venta (núcleo)

- Subtotal = Σ cantidad × precio unitario. El precio se propone con el de la variante (o el del
  producto si la variante no tiene uno propio); el costo unitario se copia de `products.cost` al
  vender, así los cambios posteriores no afectan la venta.
- **Medios de pago:** cada cobro aplica el ajuste de su medio sobre el monto que cubre
  (ajuste = base × porcentaje, redondeado a centavos). Total de la venta = subtotal + ajustes.
  El cobro copia cuotas y porcentaje del medio: cambiarlo después no afecta lo ya cobrado.
- Las **cuotas con tarjeta** son un medio de pago, no financiación propia: el comercio cobra el
  total en el acto y no queda saldo ni vencimientos. El recargo se informa aparte de la ganancia
  en los informes.
- Por defecto la venta se paga con un solo medio. Si en §1 se pidió pago combinado, admite varios
  cobros y la suma de sus montos base tiene que dar el subtotal.
- Al menos un ítem; productos activos; cantidades ≥ 1; montos no negativos.
- **Sin módulo Cuenta corriente toda venta es de contado:** se registra en el mismo acto un cobro
  por el total y la venta nace `paid`. El cliente es opcional.
- Una venta confirmada no se edita ni se borra: **se anula con motivo obligatorio** y queda en el
  historial con quién, cuándo y por qué. Anular revierte todos sus efectos.
- Numeración visible: ventas `V-00001`, cobros `C-00001`, órdenes de compra `OC-00001` (el id con
  ceros a la izquierda; no hay contador aparte).
- Vendedor por defecto: un solo usuario puede estar marcado; se propone al cargar una venta. Un
  vendedor siempre carga a su nombre.
- Un usuario con operaciones registradas no se borra, se desactiva. Nadie se borra a sí mismo.

### Comportamiento común del panel

- Registro de actividad inmutable, solo para administradores: ingresos, salidas e intentos
  fallidos; altas, cambios y bajas de maestros con valores antes y después; ventas, cobros, compras
  y sus anulaciones. Nunca guarda contraseñas. Filtros por usuario, evento y período.
- Todas las columnas de todas las tablas se pueden ocultar; la elección se guarda en la base por
  usuario y por tabla.
- Los botones con ícono muestran solo el ícono y el texto como tooltip; los que no tienen ícono
  (guardar, cancelar, confirmar) conservan el texto. Crear, editar, ver y borrar llevan ícono.
- Tablas compactas en todo el panel (tema propio).
- Alta rápida de cliente y de producto desde un modal dentro del formulario de venta.
- Versión del sistema visible en la barra superior y en el login.
- Edición del propio perfil.
- Dashboard: ventas del mes contra el anterior, cobrado del mes, últimas ventas y acceso directo a
  Nueva venta. Cada módulo suma sus widgets.

### Convenciones de código

- Lógica de negocio en `app/Actions` (una clase por operación, método `handle`) y `app/Services`
  (cálculos puros, sin base de datos, compartidos por la operación real y la vista previa).
- Toda operación que toca más de una tabla va en `DB::transaction` con `lockForUpdate` sobre lo que
  modifica (cuotas, productos, órdenes), bloqueando siempre en el mismo orden.
- Los recursos de Filament solo llaman acciones; no contienen reglas de negocio.
- Enums en `app/Enums` con `HasLabel` (y `HasColor` para estados), etiquetas en español.
- Una policy por modelo. Errores de negocio como `ValidationException` con mensaje para el usuario.
- Los cálculos y agrupados se hacen en PHP, no con funciones SQL propias del motor (los tests
  corren en SQLite y producción en MySQL).
- Factories para todos los modelos. Los datos de demostración se crean con las mismas acciones del
  sistema, nunca insertando filas a mano.

---

## 7. Módulos

| Módulo (`key`) | Depende de | Qué habilita |
|---|---|---|
| Cuenta corriente (`current-account`) | — | Ventas a cuenta, cobros imputados, saldo y ficha financiera del cliente |
| Cuotas propias (`installments`) | Cuenta corriente | Financiación del propio comercio: planes, ventas en varias cuotas, vencimientos y cobranza (las cuotas con tarjeta son del núcleo) |
| Variantes (`variants`) | — | Talles, colores u otras presentaciones por producto, con precio y stock propios |
| Compras (`purchases`) | — | Proveedores, órdenes de compra, facturas de compra |
| Stock (`stock`) | — | Existencias, movimientos, stock mínimo |
| Informes (`reports`) | — | Grupo Informes; cada informe exige además su módulo |
| Catálogo web (`catalog`) | — | Catálogo público en la landing con los productos del panel |
| Login con Google (`google-login`) | — | Botón "Ingresar con Google" en el login del panel |
| Colores personalizables (`custom-colors`) | — | El administrador del cliente elige la combinación de colores |

Futuros, a demanda: Lectura de remitos y facturas de proveedor con IA (a partir de una foto o un
PDF propone las líneas de la compra para revisar y confirmar; requiere Compras), Pasarela de pagos (Mercado Pago), Portal de clientes, App del vendedor (PWA),
Factura electrónica, Compras por pedido (vender primero y comprar después lo vendido, con listado
de pendientes de compra por proveedor).

### Cuenta corriente

Datos: **installments** (saldos a cobrar: `sale_id`, `customer_id`, `number`, `due_date`, `amount`,
`paid_amount`, `cost_portion`, `status` `pending` | `partial` | `paid` | `cancelled`; único por
venta y número), **installment_payment** (`payment_id`, `installment_id`, `amount`) y
**account_movements** (`customer_id`, `date`, `type` `sale` | `payment` | `adjustment`,
`description`, `debit`, `credit`, `balance` acumulado, `reference` morph, `user_id`).

Reglas:

- Una venta puede dejar saldo: anticipo opcional (≤ total), saldo = total − anticipo. Con saldo, el
  cliente es obligatorio.
- Sin el módulo Cuotas, el saldo queda como **un único vencimiento** con la fecha que se elija.
- La venta genera un débito por el total; el anticipo se registra como cobro y genera un crédito.
  Cada movimiento guarda el saldo acumulado del cliente.
- **Cobros:** se imputan a los vencimientos del cliente empezando por el más viejo. Admiten pagos
  parciales y adelantados. No se puede cobrar más que la deuda total. Opcionalmente se eligen los
  vencimientos a cancelar (el monto se propone con la suma de sus saldos) y entonces no puede
  superar ese saldo.
- Una venta pasa a `paid` cuando todos sus vencimientos están pagados.
- **Anular un cobro:** devuelve a cada vencimiento lo imputado, reabre las ventas que habían
  quedado pagadas y deja un débito de ajuste. El anticipo de una venta no se anula solo: se anula
  con la venta.
- **Anular una venta:** no se puede si tiene cobros imputados (hay que anularlos antes, así cada
  devolución queda registrada). Anula sus vencimientos, deja un crédito de ajuste por el total y
  anula el anticipo con su débito de ajuste.
- Ficha del cliente: saldo, deuda, vencidos, pestañas de vencimientos, cuenta corriente y productos
  comprados, y acceso directo a cobrar.
- Dashboard: total por cobrar, vencido, tabla de vencidos con acción de cobrar.

### Cuotas

Datos: **financing_plans** (`name`, `installments_count`, `surcharge_percent` por defecto 0,
`active`). Ajustes del módulo: regla de vencimiento (día fijo del mes, o mismo día de la venta).

Reglas:

- A financiar = (total − anticipo) × (1 + recargo). La venta copia la cantidad de cuotas y el
  recargo del plan: cambiar el plan después no la afecta.
- Cuota = a financiar ÷ cantidad, **redondeada hacia abajo** a centavos; la última absorbe la
  diferencia.
- Vencimientos mensuales consecutivos. Con día fijo, se elige el **mes** de la primera cuota (por
  defecto el siguiente) y en los meses más cortos vence el último día.
- Cada cuota guarda su porción de costo (costo × cuota ÷ total; la última absorbe el redondeo),
  para calcular ganancia cobrada y ganancia futura.
- El formulario de venta muestra en vivo total, cuota y cronograma, con el mismo servicio de
  cálculo que usa la venta real.
- Dashboard: ganancia futura y gráfico de ingresos proyectados a 12 meses (monto y ganancia).

### Compras

Datos: **suppliers** (`name`, `tax_id`, `phone`, `email`, `address`, `notes`, `active`),
**purchase_orders** (`supplier_id`, `date`, `status` `draft` | `issued` | `received` | `cancelled`,
`notes`, `user_id`, `issued_at`, campos de anulación), **purchase_order_items** (`product_id`,
`quantity`, `received_quantity`), **purchases** (factura: `purchase_order_id` opcional,
`supplier_id`, `date`, `invoice_number`, `total`, `notes`, `user_id`, campos de anulación) y
**purchase_items** (`product_id`, `ordered_quantity`, `quantity`, `unit_cost`, `subtotal`).

Reglas:

- La orden de compra es opcional: una factura se carga directa o desde una orden, con lo pedido
  precargado para corregir lo que llegó.
- La orden se edita mientras esté en borrador, se emite, se imprime y se anula. Al cargar su
  factura pasa a recibida; anular la factura la deja emitida otra vez.
- No puede llegar más de lo pedido ni un producto que no estaba en la orden; tiene que haber
  llegado algo.
- El número de factura no se repite para un mismo proveedor entre las compras vigentes.
- El costo de la factura queda como `products.cost`. Las ventas ya hechas no cambian.
- Proveedor habitual en cada producto (`supplier_id`).
- Dashboard: compras del mes, órdenes sin recibir, últimas compras.

### Stock

El stock se lleva **por variante** (un producto sin variantes usa su variante implícita).

Datos: `product_variants.stock`, `product_variants.min_stock` y **stock_movements** (`product_variant_id`, `type` `sale` |
`purchase` | `adjustment` | `cancellation`, `quantity` con signo, `balance`, `reference` morph,
`user_id`). Ajuste del módulo: al vender sin stock, **avisar** (valor por defecto: deja vender y el stock queda en negativo) o **bloquear**.

Reglas: la venta descuenta y su anulación devuelve; la factura de compra suma y su anulación resta
(no se puede anular si dejaría el stock en negativo); ajuste manual con motivo, solo administrador;
todo cambio queda en el historial. Dashboard: valor del stock y productos con stock bajo.

**Carga de stock sin Compras.** El módulo funciona solo: un comercio puede arrancar sin saber qué
tiene y sin cargar compras. Pantalla "Carga de stock" (solo administrador): se filtra por
categoría o producto y se escribe la cantidad contada de cada variante en una grilla; al guardar
se genera un movimiento de ajuste por cada diferencia, con el motivo indicado ("Stock inicial",
"Recuento"). Sirve para el stock inicial y para los recuentos posteriores. Hasta que se carga, un
producto tiene stock 0; por eso el módulo arranca en "avisar". Pasarlo a "bloquear" es una
decisión del SuperAdmin una vez terminado el primer recuento, y el panel lo advierte si todavía
hay variantes en negativo.

### Variantes

- Ajuste del módulo: lista de atributos que usa el negocio (por ejemplo Talle y Color) con sus
  valores posibles, para cargar rápido y sin errores de tipeo.
- En el formulario de producto, una tabla de variantes: se generan combinando los valores
  elegidos (S, M, L × Negro, Blanco) y cada una admite SKU, precio propio y, con Stock, existencia.
- En la venta, después de elegir el producto se elige la variante; con Stock se ve cuánto hay de
  cada una. El ítem guarda la descripción completa ("Remera lisa — M / Negro").
- Una variante con ventas o movimientos no se borra, se desactiva.
- Apagado: cada producto tiene solo su variante implícita y no aparece nada de esto en pantalla.
- Compras: las líneas de órdenes y facturas también apuntan a la variante.

### Informes

Solo administradores. Cada informe tiene tres piezas: un servicio que recibe filtros y devuelve
filas y totales; una página con filtros en vivo y tabla de Filament (ordenar, buscar, paginar,
ocultar columnas) con los totales debajo; y una versión imprimible en otra pestaña con los mismos
filtros (HTML con `@media print`, sin librería de PDF). Pantalla e impresión salen del mismo
servicio, así los números coinciden.

| Informe | Requiere | Contenido |
|---|---|---|
| Ventas y ganancia | — | Ventas de un período sin anuladas: total, costo, ganancia, margen; en detalle o agrupadas por vendedor o producto |
| Cobros realizados | — | Cobros de un período sin anulados, con subtotales por medio de pago y por cobrador |
| Saldos pendientes por cliente | Cuenta corriente | Una fila por cliente: Vencido, una columna por mes (6/12/18/24), Más adelante y Total |
| Cobranza del mes | Cuenta corriente | Vencimientos del mes con teléfono, dirección, días de atraso y saldo; opcionalmente los atrasados |
| Compras por proveedor | Compras | Facturas de un período agrupadas por proveedor |

Los totales de los informes tienen que coincidir con los del dashboard.

### Catálogo web

- Pestaña "Catálogo" en el formulario de producto: publicado, mostrar precio, imágenes
  (`FileUpload` de Filament en el disco `public`), slug automático.
- Rutas en el dominio de la landing, con middleware `module:catalog`: `/catalogo`,
  `/catalogo/{category:slug}`, `/producto/{product:slug}`. Buscador, filtro por categoría y botón
  de consulta por WhatsApp con el nombre del producto.
- Solo se muestran productos activos y publicados. Sin precio visible: "Consultar precio".
- Con Variantes, la ficha muestra los talles y colores disponibles; con Stock, las variantes sin
  existencia aparecen como agotadas (ajuste del módulo: mostrarlas u ocultarlas).
- Sección "Catálogo" en la landing con categorías y destacados.

### Login con Google

- Rutas `auth/google/redirect` y `auth/google/callback` en el dominio del panel, con middleware
  `module:google-login`. Un controlador propio con Socialite; sin plugin de Filament.
- **No registra usuarios:** solo entra quien ya existe y está activo, buscando por `google_id` o
  por email (y entonces guarda el `google_id`). En cualquier otro caso vuelve al login con error.
- Botón en el formulario de login mediante un render hook del panel.
- Sin credenciales en el `.env`, el panel `superadmin` lo avisa y no deja habilitarlo.
- No aplica al panel `superadmin`.

### Colores personalizables

Ver §8. Apagado: el cliente no ve nada. Encendido: aparece "Configuración → Colores" en el panel
`admin`.

---

## 8. Sitio, apariencia y temas

- **Temas**, en el repositorio y comunes a todos los clientes. Cada uno se registra en
  `config/themes.php` con nombre, vista previa, vistas de la landing
  (`resources/views/landings/{tema}`), hoja de estilos (`resources/css/themes/{tema}.css`), paleta
  y tipografía para el panel, y sus **combinaciones de colores** ya probadas (contraste, claro y
  oscuro). Todos se compilan siempre: cambiar de tema no requiere compilar ni desplegar.
- En la landing el tema cambia todo el diseño. En el panel `admin` hay un único tema de Filament
  con variables CSS: el tema elegido cambia colores, tipografía y logo, no la estructura.
- **Apariencia**, pantalla del panel `superadmin`, siempre disponible: tema, combinación de
  colores, logotipo para fondo claro y para fondo oscuro, logo del login, favicon, imagen para
  compartir en redes y recursos gráficos del tema (imágenes del hero y de las secciones). Se
  guardan en el disco `public`. Sin logo se muestra el nombre del negocio como texto.
- **Colores**, pantalla del panel `admin`, solo con el módulo encendido: el administrador elige
  entre las combinaciones del tema actual, con vista previa. Sin selector de color libre. El
  cliente nunca cambia de tema, logotipos ni recursos.
- **Contenido del sitio**, pantalla del panel `admin` ("Configuración → Sitio"), siempre
  disponible: nombre, lema, textos del hero, teléfono, WhatsApp, dirección, redes. Solo texto.
- **Landing por secciones** (hero, propuesta, catálogo, preguntas frecuentes, contacto), cada una
  activable, con botón "Ingresar al sistema" hacia el dominio del panel conservando esquema y
  puerto.

---

## 9. Fases y criterio de terminado

Una fase por vez. Una fase está terminada solo cuando se cumple su criterio **y** el cierre común:

1. `php artisan migrate:fresh --seed` sin errores.
2. `php artisan test --compact` en verde, con todos los módulos encendidos y con solo el núcleo.
3. `composer analyse` sin errores (nivel 5).
4. `vendor/bin/pint --dirty`.
5. `npm run build` si cambió CSS o JS.
6. Recorrida manual en los dominios afectados.
7. Entrada en `CHANGELOG.md` (indicando si requiere migración), `version` en `config/platform.php`,
   commit y pausa para que el usuario lo pruebe antes de seguir.

Los tests son de Pest, en su mayoría de feature, con factories. Cada regla de las secciones 5 a 8
tiene al menos un test que la prueba.

| # | Fase | Terminado cuando hay tests que prueban que… |
|---|---|---|
| 0 | **Preguntas y arranque** (§1, §4) | La landing responde en su host, el login de cada panel en el suyo y un host desconocido da 404; detrás de un proxy los enlaces salen con `https` |
| 1 | **Usuarios y base del panel** | Roles y acceso por panel; usuario inactivo no entra; el SuperAdmin no aparece en ningún listado, selector ni registro del panel `admin` y su edición da 404; registro de actividad (ingresos, fallos, cambios con antes y después, sin contraseñas); columnas guardadas por usuario; botones con ícono; versión visible |
| 2 | **Módulos y panel superadmin** | Encender y apagar; dependencias en ambos sentidos; un recurso, una ruta y un bloque Blade de prueba desaparecen con el módulo apagado; comandos CLI; solo el SuperAdmin entra al panel. Test de arquitectura: todo recurso, página y widget fuera del núcleo declara su módulo |
| 3 | **Núcleo comercial**: clientes, categorías, productos con variante implícita, medios de pago, venta de contado, cobro, anulación, dashboard | Código automático de producto; todo producto nace con su variante; cálculo del subtotal; recargo y descuento por medio de pago con redondeo a centavos; el cobro copia cuotas y porcentaje del medio; costo copiado al vender; venta de contado nace pagada con su cobro; validaciones de ítems; anular con motivo revierte todo; el vendedor ve solo lo suyo y no anula; numeración visible; vendedor por defecto único |
| 4 | **Stock y Variantes** | Carga de stock por grilla genera un ajuste por cada diferencia y ninguno si no cambió; Generación de variantes por combinación de atributos; precio propio o heredado; variante con movimientos no se borra; el stock es por variante; venta descuenta, anulación devuelve, compra suma (la parte de compras se prueba al hacer Compras); por defecto avisa y deja el stock en negativo, con el ajuste en bloquear rechaza la venta; no se anula una compra que deja stock negativo; historial; con el módulo apagado nada de esto ocurre ni se ve |
| 5 | **Sitio, apariencia y temas** | Contenido editable aparece en la landing; secciones activables; cambio de tema y de combinación de colores se refleja en landing y panel; logo y favicon cargados se usan y sin logo sale el nombre; "Colores" solo existe con el módulo encendido; el cliente no accede a "Apariencia". Hay dos temas reales |
| 6 | **Catálogo web** | Solo productos activos y publicados; categorías; precio oculto muestra "Consultar"; buscador; rutas 404 y sección ausente con el módulo apagado |
| 7 | **Instalación rápida** | El seeder de demostración arma datos con las acciones reales solo de los módulos encendidos y no hace nada si ya hay ventas; los seeders iniciales se pueden correr dos veces sin duplicar; README de despliegue |
| 8 | **Cuenta corriente** | Venta con anticipo y saldo; cliente obligatorio con saldo; cobro al vencimiento más viejo, parcial, adelantado y con vencimientos elegidos; no se cobra más que la deuda; saldo acumulado; la venta se cierra al pagarse todo; anular cobro y anular venta con todas sus restricciones; con el módulo apagado la venta solo admite contado |
| 9 | **Cuotas** | Redondeo hacia abajo y última cuota que absorbe; recargo; porción de costo; vencimientos con día fijo (incluido febrero) y con mismo día; la venta no cambia si cambia el plan; la vista previa coincide con la venta real; con el módulo apagado el saldo es un único vencimiento |
| 10 | **Compras** | Orden en todos sus estados; factura directa y desde orden; no llega más de lo pedido; factura duplicada rechazada (y permitida si la anterior está anulada); actualiza el costo sin tocar ventas hechas; anulaciones; impresión de la orden |
| 11 | **Informes** | Un test por servicio con datos creados por las acciones reales; exclusión de anulados; totales que coinciden con el dashboard; filtros; la impresión respeta los filtros; el vendedor recibe 403; informe inexistente 404; cada informe desaparece sin su módulo |
| 12 | **Login con Google** (Socialite simulado en los tests) | Entra un usuario existente por `google_id` y por email; no crea usuarios; rechaza inactivos y al SuperAdmin; botón y rutas ausentes con el módulo apagado; no se habilita sin credenciales |

---

**Primera entrega: fases 0 a 7.** Con eso un comercio tiene landing con catálogo, productos con
talles, stock y ventas con medios de pago. Las fases 8 a 12 se hacen cuando un cliente contrata
ese módulo, en el orden que haga falta; no dependen entre sí salvo Cuotas propias, que requiere
Cuenta corriente.

## 10. Alta de un cliente nuevo

1. Crear base de datos y `.env` (dominios, usuarios iniciales, credenciales de Google si aplica).
2. `composer setup`.
3. `php artisan db:seed` y, para mostrar el sistema con datos, `composer demo`.
4. Habilitar módulos según el abono: `php artisan modules:enable …` o desde el panel `superadmin`.
5. En el panel `superadmin`: elegir tema y colores, subir logotipos, favicon y recursos gráficos.
6. En el panel `admin`: nombre, textos y datos de contacto en "Configuración → Sitio".
7. Apuntar DNS y proxy a los tres dominios.

Actualizar una instalación: `git pull` del tag, `composer install --no-dev -o`,
`php artisan migrate --force`, `npm run build`, `php artisan optimize`. Como las migraciones son
comunes a todos los clientes, no hay variantes.

---

## 11. Forma de trabajo

- Antes de cada fase, un plan de ejecución corto de esa fase (qué archivos, qué tests), aprobado
  por el usuario. Este documento no se reescribe en cada fase.
- Una versión = una rama + un PR + entrada en `CHANGELOG.md` + `version` en config + tag `vX.Y.Z`.
  Mientras sea `0.x` puede haber cambios incompatibles; la `1.0.0` llega con el primer cliente en
  producción.
- Archivos nuevos con `php artisan make:*`; tests con `php artisan make:test --pest`.
- No se borran tests ni se agregan dependencias sin aprobación.
- Si una regla de este documento resulta ambigua al implementarla, se pregunta antes de decidir.
