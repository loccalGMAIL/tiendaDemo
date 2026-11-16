# Changelog

## 0.5.0 — Fase 4: Stock y Variantes

Requiere migración: sí (`product_variants.stock` y `min_stock`; tabla `stock_movements`).

- **Variantes** (módulo): tabla de variantes en el producto con generación por combinación de atributos (S, M, L × Negro, Blanco = 6; no duplica las existentes), SKU y precio propio o heredado, y activar/desactivar. Una variante con ventas o movimientos no se borra. Con el módulo apagado cada producto tiene solo su variante implícita; con él encendido la implícita no se ofrece si el producto tiene variantes con nombre (`ProductVariant::sellable()`).
- **Stock** (módulo): existencias por variante, historial inmutable de movimientos (`stock_movements`, con motivo), ajuste manual y **Carga de stock** por grilla (stock inicial y recuentos, un ajuste por cada diferencia), stock mínimo, valor del stock y productos con stock bajo en el escritorio (solo administrador), existencia visible al vender, y columna Stock en Productos.
- La venta descuenta el stock y la anulación lo devuelve con movimientos propios. Por defecto **avisa** y deja el stock en negativo; con el ajuste **bloquear** rechaza la venta que no alcanza sin dejar nada a medias. El panel superadmin advierte cuántas variantes están en negativo antes de bloquear.
- Los eventos `SaleCreated` y `SaleCancelled` se disparan **dentro de la transacción** de la venta (con el usuario que actúa): si un módulo rechaza, se revierte todo. Anular revierte exactamente lo que descontó la venta, aunque el módulo se haya apagado después.
- `LogsActivity` puede omitir registros y atributos que ya tienen historial propio (el stock no llena la actividad).
- Trait `RelationManagerBelongsToModule` y bases `AdministratorPage`, `AdministratorStatsWidget` y `AdministratorTableWidget` para lo que solo ve el administrador. El test de arquitectura ahora recorre también los relation managers.

## 0.4.0 — Fase 3: núcleo comercial

Requiere migración: sí (`customers`, `categories`, `products`, `product_variants`, `payment_methods`, `sales`, `sale_items`, `payments`). `db:seed` carga los medios de pago iniciales.

- Clientes, categorías (slug automático), productos y medios de pago, con su actividad antes y después y las policies del plan (el vendedor ve clientes y productos, da de alta clientes y no ve costos).
- Productos: SKU automático `PROD-00001…` cuando viene vacío y variante implícita en todo producto (`CreateProduct`).
- Ventas de contado (`CreateSale`): precio, costo y descripción copiados al vender; cada cobro copia cuotas y porcentaje del medio de pago; recargo o descuento redondeado a centavos (mitad hacia afuera del cero); pago combinado con la suma de bases igual al subtotal; la venta nace pagada. Aritmética con `bcmath` (`Money`) y cálculo puro compartido con la vista previa del formulario (`SaleCalculator`).
- Anulación con motivo obligatorio (`CancelSale`): solo administradores, una sola vez, revierte los cobros y deja quién, cuándo y por qué. Eventos `SaleCreated` y `SaleCancelled` para los módulos.
- Una venta confirmada no se edita ni se borra. Numeración visible `V-00001` y `C-00001`.
- El vendedor ve solo sus ventas (`SELLER_SEES_ALL_SALES` para verlas todas), no cambia el precio ni anula.
- Formulario de venta con alta rápida de cliente (y de producto para administradores), vendedor por defecto propuesto y resumen en vivo. Vista de venta con la acción Anular.
- Escritorio con ventas del mes contra el anterior, cobrado del mes, últimas ventas y acceso a Nueva venta.
- Un usuario con ventas, cobros o anulaciones no se borra: se desactiva (cierra el pendiente de la Fase 1).
- `PaymentMethodSeeder`: Efectivo −10 %, Transferencia, Débito, Tarjeta 1, 3 (+15 %) y 6 cuotas (+30 %).

## 0.3.0 — Fase 2: módulos y panel superadmin

Requiere migración: sí (tabla `modules`).

- Módulos habilitables: enum `App\Enums\Module` (9 módulos, con dependencias y configuración requerida), tabla `modules` y servicio `App\Services\Modules` (fachada `App\Facades\Modules`) con caché. Fila ausente = módulo apagado.
- Dependencias validadas en ambos sentidos: no se enciende sin lo que requiere ni con configuración faltante (Login con Google sin credenciales), y no se apaga si otro encendido lo usa.
- Puntos de corte: traits `BelongsToModule` (recursos y páginas: acceso, navegación y 404 en la ruta) y `WidgetBelongsToModule`, middleware `module:{key}`, directiva `@module`, y convenciones para campos (`->visible`) y tareas (`->when`). Ver `docs/modulos.md`.
- Panel `superadmin`: página Módulos con interruptores (bloqueados con el motivo cuando corresponde) y los ajustes de cada módulo (Stock, Variantes, Cuotas propias, Catálogo web).
- Comandos `modules:list`, `modules:enable {key|--all}` y `modules:disable {key}`. `composer demo` enciende todos los módulos posibles.
- La suite corre con solo el núcleo y con todos los módulos encendidos (`composer test`, `TEST_MODULES=none|all`). Test de arquitectura: todo recurso, página y widget fuera del núcleo declara su módulo.
- La versión en la barra superior tiene separación respecto del menú de usuario.

## 0.2.0 — Fase 1: usuarios y base del panel

Requiere migración: sí (`users` suma `role`, `active`, `is_default_seller`, `google_id`; tablas `activity_logs` y `table_column_preferences`).

- Roles `superadmin`, `admin` y `seller`; acceso por panel con `User::canAccessPanel()`. Un usuario inactivo no entra a ningún panel y un rol equivocado recibe el mismo mensaje que con credenciales incorrectas.
- SuperAdmin invisible para el cliente: scope `User::visibleToClient()` (listados, selectores y filtros), edición con 404, rol no asignable y sus acciones y las hechas sobre él fuera de la actividad (`ActivityLog::visibleToClient()`).
- Recurso Usuarios (panel `admin`) con acciones `CreateUser`, `UpdateUser`, `DeleteUser`: vendedor por defecto único, nadie se borra a sí mismo, contraseña vacía al editar no se cambia.
- Registro de actividad inmutable: ingresos, salidas e intentos fallidos; altas, cambios y bajas con valores antes y después (trait `LogsActivity`), sin contraseñas. Pantalla Actividad con filtros por usuario, evento y período.
- Columnas de todas las tablas ocultables, guardadas en la base por usuario y por tabla (`PersistsTableColumns`).
- Botones de crear, editar, ver y borrar con solo ícono y tooltip; tablas compactas; versión visible en la barra superior y en el login; edición del propio perfil en ambos paneles.
- `InitialUsersSeeder` (admin y superadmin desde `.env`), repetible sin duplicar ni pisar contraseñas.

## 0.1.0 — Fase 0: preguntas y arranque

Requiere migración: sí (tablas base de Laravel: users, cache, jobs, sessions).

- Proyecto Laravel 13 con Filament 5 (paneles `admin` y `superadmin`), Socialite, Pest 4, Larastan (nivel 5), Pint y Laravel Boost.
- Tres hosts configurables desde `.env` (`config/domains.php`): landing, panel `admin` y panel `superadmin`, estos dos en la raíz. Cualquier otro host responde 404.
- Landing mínima con botón "Ingresar al sistema" hacia el panel, conservando esquema y puerto.
- `trustProxies` desde `TRUSTED_PROXIES`: detrás de un proxy con SSL los enlaces salen con `https`.
- Health check `/up` solo en el host del panel.
- `config/platform.php` (versión y usuarios iniciales), bloque `google` en `config/services.php`, zona horaria de Buenos Aires, idioma `es` con `lang/es`.
- Scripts de composer: `analyse`, `lint`, `test`, `setup`, `demo`.
- Tema de Filament `admin` (compartido con `superadmin`).
- Decisiones de arranque en `docs/decisiones.md`.
