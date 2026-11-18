# Decisiones de arranque (PLAN-BASE §1)

Respuestas a las preguntas obligatorias, registradas el 2026-11-16.

| Tema | Decisión |
|---|---|
| Plataforma / repositorio | tiendaDemo |
| Primer cliente | "Tienda Demo", demostración, rubro indumentaria, sin logo (se muestra el nombre como texto) |
| Dominios (local) | `tiendademo.localhost`, `app.tiendademo.localhost`, `plataforma.tiendademo.localhost` (puerto 8000). Producción: a definir en `.env` |
| Módulos primera instalación | Todos. Login con Google no se puede habilitar hasta cargar credenciales |
| Medios de pago | Efectivo −10 %, Transferencia 0 %, Débito 0 %, Tarjeta 1 cuota 0 %, Tarjeta 3 cuotas +15 %, Tarjeta 6 cuotas +30 % |
| Pago combinado | Sí |
| Variantes | Sí; precio por variante, hereda del producto si está vacío |
| Tipo de venta | Contado y a cuenta |
| Cuotas propias | Vencen el día fijo 10; planes con recargo (ej. 3 cuotas +10 %, 6 cuotas +20 %) |
| Vendedor | No cambia el precio; ve solo sus ventas |
| Stock sin existencia | Avisa (no bloquea) |
| Servidor | Local con Laragon (`php artisan serve` + `npm run dev`, MySQL de Laragon). Producción sin definir: `TRUSTED_PROXIES=*`, compilación en el servidor |
| Login con Google | Sin credenciales por ahora (módulo apagado) |
| Defaults | `es_AR`, pesos argentinos, `America/Argentina/Buenos_Aires`, MySQL |

## Decisiones de diseño (Fase 3)

- **Pago combinado:** una venta admite varios cobros; la suma de los montos base tiene que dar el subtotal. Con un solo medio de pago el monto es el subtotal.
- **Redondeo:** los recargos y descuentos se redondean a centavos con la mitad hacia afuera del cero (3,33 × 15 % = 0,4995 → 0,50; −10,005 → −10,01).
- **Precio:** sale del catálogo (variante, o producto si la variante no tiene propio). Solo un administrador puede indicar otro precio al vender.
- **Vendedor ve solo sus ventas:** configurable por instalación con `SELLER_SEES_ALL_SALES` en el `.env` (por defecto `false`).
- **Medios de pago iniciales** (`PaymentMethodSeeder`, editables desde el panel): Efectivo −10 %, Transferencia 0 %, Débito 0 %, Tarjeta 1 cuota 0 %, Tarjeta 3 cuotas +15 %, Tarjeta 6 cuotas +30 %.
- **Cobros sueltos** (sin venta) no existen hasta el módulo Cuenta corriente (Fase 8): sin saldo no hay nada que cobrar aparte de la venta.
- **Clientes y productos con ventas** no se borran (se desactivan); lo mismo vale para usuarios con operaciones y medios de pago ya usados.
- **Variantes:** todo producto tiene su variante implícita. El formulario de venta ofrece solo las implícitas mientras el módulo Variantes esté apagado.

## Decisiones de diseño (Fase 4)

- **Eventos dentro de la transacción:** el stock se descuenta (y, en modo bloquear, se valida) junto con la venta; si no alcanza, no queda ni la venta, ni los cobros, ni movimientos.
- **Anular revierte lo registrado:** se devuelve exactamente lo que descontó esa venta. Una venta hecha con Stock apagado no suma stock al anularse.
- **Variante implícita:** con el módulo Variantes apagado es la única que se ofrece; con él encendido, solo se ofrece si el producto no tiene variantes con nombre.
- **Stock:** unidades enteras; lo contado o ajustado no puede ser negativo (solo las ventas en modo «avisar» lo dejan en negativo). Stock bajo = mínimo mayor que 0 y existencia menor o igual al mínimo. El movimiento guarda también el motivo.
- **Valor del stock y stock bajo** del escritorio solo los ve el administrador (muestran costos).

## Decisiones de diseño (Fase 5)

- **Dos temas reales:** *Clásico* (claro y limpio) y *Boutique* (portada oscura, tipografía con serifas), cada uno con cuatro combinaciones de colores. Ver `docs/temas.md`.
- **Contenido y apariencia en la base** (`settings`), no en el `.env`: el administrador edita el contenido y el SuperAdmin elige el tema sin tocar el servidor.
- **Sin descargas de fuentes ni paquetes nuevos:** cada tema declara su pila tipográfica; el panel aplica la del tema solo a los títulos.
- **Contraste verificado por test** (WCAG AA, 4,5) para cada combinación, en claro y en oscuro.
- **La sección Catálogo de la landing** muestra las categorías activas con el módulo Catálogo encendido; los productos y enlaces llegan con la Fase 6.
- **El logo del acceso** es opcional (si no hay, se usa el logo claro); sin ningún logo se muestra el nombre del negocio.
- Los cambios de contenido del sitio quedan en la actividad con antes y después; la apariencia (cosa del SuperAdmin) no.

## Decisiones de diseño (Fase 6)

- **Destacados:** `products.is_featured` (no estaba en el plan base) permite elegir qué productos aparecen en la sección Catálogo de la landing.
- **Slug estable:** se genera una vez desde el nombre y no cambia al renombrar, para no romper enlaces públicos. Se puede editar a mano.
- **Lo público es mínimo:** nunca se muestran costo, SKU interno ni cantidades; con Stock solo «Agotado». Lo no publicado, inactivo o inexistente responde 404.
- **Agotados:** el ajuste «Mostrar los productos agotados» del módulo Catálogo decide si las variantes agotadas se marcan o se ocultan y si los productos totalmente agotados se listan. La ficha de un producto agotado sigue accesible por enlace directo.
- **Precio con variantes:** si las variantes disponibles tienen precios distintos se muestra «Desde» el más bajo.
- **Categorías en la web:** solo las activas que tienen productos publicados.
- **El menú enlaza al catálogo** mientras haya productos publicados, aunque el administrador oculte la sección Catálogo de la landing.

## Decisiones de diseño (Login con Google)

- **No registra usuarios**: el administrador del cliente da de alta a las personas; Google solo es otra forma de entrar. Entra un usuario existente, activo, administrador o vendedor (`User::canAccessPanel(admin)`).
- **Vinculación más estricta que el plan base**: por correo solo si Google lo informa como verificado (`email_verified`); una cuenta ya vinculada a otro `google_id` no se pisa. Después de vinculada manda el `google_id`, aunque el correo cambie.
- **Un solo mensaje para todo rechazo** (el de las credenciales incorrectas): no se distingue inexistente, inactivo, SuperAdmin, sin verificar o cuenta ajena. El SuperAdmin tampoco puede entrar por Google al panel del negocio, y el panel `superadmin` no lleva el botón.
- **Lógica en una acción** (`FindUserForGoogleLogin`) y no en el controlador; el controlador solo habla con Socialite, inicia la sesión (`Auth::login` dispara el evento que ya registra el ingreso) y registra el fallo.
- **El fallo se atribuye al usuario si existe**, igual que `LogFailedLogin`: un intento sobre un SuperAdmin queda fuera de la actividad que ve el cliente.
- **La URI de redirección** sale de `GOOGLE_REDIRECT_URI` o, si está vacía, de la ruta del callback; se fija antes de crear el proveedor porque tiene que ser la misma al redirigir y al canjear el código. Se agrega `prompt=select_account` a la URL de Google para que siempre pida elegir la cuenta.
- **El botón es un render hook** del panel del negocio (`AUTH_LOGIN_FORM_AFTER`): la vista devuelve vacío con el módulo apagado, así que no hay ni rastro. Estilos propios en línea (no dependen de compilar).

## Decisiones de diseño (Cuotas propias)

- **El recargo de financiación va aparte del total de la venta.** `sales.total` sigue siendo subtotal + ajustes de los medios de pago; el recargo se guarda en `financing_surcharge` (con su porcentaje) y se asienta como un débito propio en la cuenta del cliente. Ventas y ganancia no cambia: el interés se ve en la cuenta, en los vencimientos y en la ganancia futura. Invariante: `total − down_payment + financing_surcharge = financed_amount`.
- **Los planes los administra el administrador del cliente** (Configuración, como los medios de pago); la regla de vencimiento (día fijo o mismo día) sigue siendo un ajuste del SuperAdmin.
- **Un servicio puro** (`InstallmentSchedule`) arma el cronograma y lo usan la venta real, el listener de Cuenta corriente y la vista previa del formulario: los números coinciden por construcción. La cuota se redondea hacia abajo (`bcdiv` a 2 decimales) y la última absorbe la diferencia; lo mismo con la porción de costo.
- **La primera cuota vence justo en la fecha calculada** y las demás en el día de anclaje de su mes (el fijo, o el día de la venta), siempre desde el mes de la primera, así 31/1 → 28/2 → 31/3. Con día fijo el ajuste admite hasta el 28, por eso nunca se acorta.
- **La porción de costo de lo financiado** es `total_cost × base ÷ subtotal` (la base es lo que queda a cuenta sin recargo), repartida en proporción a las cuotas. El plan base dice «costo × cuota ÷ total»; con recargo esa fórmula superaría el costo, así que se reparte sobre la base. La **ganancia futura** de una cuota es su saldo menos la parte de costo proporcional a lo que falta cobrar: incluye el interés.
- **La venta copia el plan** (`financing_plan_id`, `installments_count`, recargo y su porcentaje); no hay clave foránea (la columna ya existía) y el plan puede cambiar o desactivarse sin tocar lo vendido.
- **Sin el módulo o sin elegir plan, el saldo es un único vencimiento** con la fecha indicada. Con el módulo apagado, un plan que llegue se ignora; el vencimiento sigue siendo obligatorio.
- **Gráfico**: ingresos proyectados a 12 meses por mes de vencimiento (por cobrar y ganancia). Lo ya vencido se suma al mes actual para que no desaparezca de la proyección. Solo para el administrador, porque muestra ganancias.

## Decisiones de diseño (Cuenta corriente)

- **Saldo = un único vencimiento.** Sin el módulo Cuotas propias (que todavía no existe), lo que queda a cuenta es un vencimiento con la fecha que se elige al vender. `installments` ya trae `number` y `cost_portion` para que Cuotas lo reparta en varios.
- **Los números de la venta**: `total − down_payment = financed_amount`. Lo cobrado en el acto es la base más el ajuste de cada medio; lo que queda a cuenta es `subtotal − Σ bases` (el ajuste de un medio solo grava lo que ese medio cobra).
- **Una venta de contado no toca el libro**, aunque tenga cliente y el módulo esté encendido: solo las ventas con saldo generan movimientos (débito por el total, crédito por cada cobro en el acto).
- **El cobro baja la deuda por su base**, no por lo que paga el cliente: el recargo o descuento del medio es resultado del cobro, no de la deuda. El crédito del libro es la base.
- **Imputación**: del más viejo al más nuevo por fecha de vencimiento y luego id, o solo a los vencimientos elegidos. Un cobro no puede superar la deuda total (ni el saldo de lo elegido). El libro nunca se edita: las anulaciones son ajustes. Invariante probada: saldo del libro = Σ saldos de vencimientos abiertos.
- **Anular una venta con cobros imputados se rechaza**: el evento `SaleCancelled` corre dentro de la transacción, así que el listener del módulo lanza `ValidationException` y no queda nada a medias. Anula primero los cobros, así cada devolución queda registrada.
- **El anticipo no se anula solo**: es parte de la venta. `PaymentPolicy::cancel` solo admite cobros a cuenta (`sale_id` vacío).
- **El vendedor cobra pero no anula.** Ve y registra cobros, y ve la ficha de los clientes (los clientes ya eran compartidos); el administrador anula. Para que un vendedor vea los cobros que cargó, `Payment::scopeVisibleTo` incluye los suyos además de los de sus ventas.
- **Un cliente inactivo con deuda puede pagar.** «Inactivo» solo impide venderle.
- **Nombres**: la relación de un cobro con sus vencimientos es `imputedInstallments()`, porque `payments.installments` es la cantidad de cuotas del medio de pago (una columna que tapa a la relación del mismo nombre).
- **Informes de cuenta corriente** (Saldos pendientes y Cobranza del mes): piden Informes y Cuenta corriente (`alsoRequires()`). «Vencido» es lo que ya pasó de su fecha; lo que vence hoy cuenta en el mes actual. No se filtran por Desde/Hasta: usan una foto de hoy y un mes propio.
- **Demostración**: las ventas a cuenta se marcan en el plan con `on_account` (porcentaje cobrado y días al vencimiento) y solo valen con el módulo encendido; la demo sigue teniendo 25 ventas.

## Decisiones de diseño (Compras)

- **Todo es del administrador.** Proveedores, órdenes y facturas tienen policies solo para administrador: el vendedor no ve costos de compra.
- **Operaciones como acciones** (`app/Actions/Purchases`), una por operación, con `DB::transaction` y `lockForUpdate`, y las reglas compartidas en el trait `GuardsPurchasing`. Las pantallas solo las llaman.
- **El costo no se revierte al anular una factura.** El costo del producto queda como el de la última factura; si hay que corregirlo se edita el producto. Las ventas ya hechas no cambian porque copian su costo al vender.
- **Stock por evento, no por llamada.** La factura dispara `PurchaseCreated` / `PurchaseCancelled` dentro de su transacción; el listener de Stock suma y resta. Anular revierte lo que esa factura sumó (sus movimientos), no lo que diga el módulo hoy, y se rechaza si dejaría la variante en negativo.
- **Orden y factura**: una orden tiene a lo sumo una factura vigente. Anular la factura reabre la orden (emitida, con lo recibido en cero); una orden con factura vigente no se anula. Una línea en cero en la factura de una orden es lo que no llegó y no se guarda.
- **Número de factura**: único por proveedor entre las facturas vigentes (no es una restricción de la base, porque una anulada libera el número).
- **Sin ajustes propios** en Plataforma: el módulo no tiene configuración.
- **Informe «Compras por proveedor»**: llegó en la 0.11.0 (ver Informes). Pide dos módulos a la vez (Informes y Compras).

## Decisiones de diseño (Informes)

- **Compras por proveedor** (0.11.0): cuenta las facturas vigentes por su fecha de factura, sin anuladas. Agrupa por proveedor (por defecto) o muestra el detalle. Pide Informes **y** Compras: el menú, el acceso, la ruta de la pantalla y la de impresión responden 404 si falta cualquiera de los dos.
- **Más de un módulo por página**: `BelongsToModule` acepta `alsoRequires()` (lista vacía por defecto) en lugar de que cada página redefina `canAccess()`, así el test de arquitectura sigue garantizando que nadie tapa el corte.

- **Ganancia = subtotal de ítems − costo.** El recargo o descuento de los medios de pago (`sales.adjustment`) se informa en su propia columna y no entra en la ganancia, como pide el plan base. El margen es la ganancia sobre el subtotal.
- **Solo los informes sin dependencias.** Ventas y ganancia y Cobros realizados se hicieron ahora; Saldos pendientes y Cobranza del mes esperan a Cuenta corriente, y Compras por proveedor a Compras. Cada uno se suma al grupo «Informes».
- **Un servicio por informe** (`app/Services/Reports`): recibe filtros y devuelve columnas, filas y totales. La pantalla de Filament y la impresión consumen el mismo resultado.
- **Tabla propia, no de Filament.** Los datos son agregados en PHP (no una consulta de un modelo), así que se muestran con una tabla simple compartida por pantalla e impresión. No se puede ordenar ni ocultar columnas.
- **La impresión va en el dominio del panel** con la sesión del administrador; los filtros viajan en la URL (fechas e ids, sin datos personales). Un visitante sin sesión es redirigido al acceso; un vendedor recibe 403.

## Decisiones de diseño (Fase 7)

- **La demostración usa las acciones reales** (`CreateProduct`, `GenerateVariants`, `CountStock`, `CreateSale`, `CancelSale`, `CreateUser`): totales, stock, movimientos, eventos y actividad son los del sistema, no filas insertadas a mano. Solo los maestros sin regla de negocio (categorías, clientes) se crean directo.
- **Solo lo de los módulos encendidos**, por eso corre después de `modules:enable --all`. Los módulos de las fases 8–12 suman su parte al mismo seeder.
- **Sin azar**: catálogo y ventas son una lista fija, de modo que dos instalaciones de demostración quedan iguales. El stock inicial se calcula como lo que se va a vender más lo que debe quedar (con agotados y stock bajo a propósito), así nunca queda negativo.
- **Seguridad**: se niega en producción (crea usuarios de prueba) y no corre si ya hay ventas. `DB::prohibitDestructiveCommands()` en producción.
- **Fechas relativas a hoy** (mes actual, anterior y el previo), sin días futuros, para que el escritorio compare meses.
- **Administrador de la demo**: el de `.env` si existe; si no, `admin@demo.test`. Vendedores `ana@demo.test` y `luis@demo.test` con `DEMO_PASSWORD`.
- **`public/build` versionado**: actualizar un servidor no requiere Node. Hay que correr `npm run build` y commitear antes de cada versión.

## Entorno local

- PHP 8.3.30 (Laragon), Composer 2.10, Node 24, MySQL 8.4.3 (Laragon).
- Base de datos: `tiendademo` (utf8mb4_unicode_ci).
- Versiones instaladas: Laravel 13.42, Filament 5.11, Pest 4.7, Larastan 3.15, Boost 2.10, Socialite 5.32.
