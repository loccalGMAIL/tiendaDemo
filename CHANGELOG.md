# Changelog

## 0.17.0 (2026-11-23) — Códigos de barras

Requiere migración: no (el código es el SKU, que ya existía).

- **Módulo Códigos de barras**: etiquetas autoadhesivas con código de barras **Code 128** (encoder propio, sin dependencias) y venta con lector. Sin el módulo no aparece nada y las rutas responden 404.
- **El código es el SKU**: el de la variante, o el del producto si es la variante implícita. Una variante con nombre sin SKU lo recibe sola al crearse (`PROD-00012-01`, `-02`…). Para las que ya existían: botón «Completar los SKU que faltan» en Etiquetas o `php artisan barcodes:fill-skus`.
- **El SKU tiene que poder imprimirse** (ASCII sin tildes) y **no puede repetirse entre un producto y una variante** (si no, el lector no sabría cuál es). Se valida siempre, con o sin el módulo.
- **Etiquetas** (Catálogo → Etiquetas, solo administrador): cantidad por variante, «1 de cada una», «Cantidad = stock» (con Stock), «Empezar en la etiqueta N.º» para reutilizar una hoja ya empezada. Imprime en otra pestaña (`/etiquetas/imprimir`). También se llega con la selección ya cargada desde la acción masiva «Imprimir etiquetas» de Productos, desde las variantes de un producto y desde una factura de compra («Etiquetas de lo recibido»).
- **Formato configurable desde el panel de la plataforma**: plantillas (rollo 50×25, 40×30, 60×40, hoja A4 3×8 y 4×10) o medidas propias: rollo o hoja (A4/Carta), tamaño, columnas, filas, márgenes y separaciones; precio y nombre del negocio opcionales. Por defecto, rollo de 50×25 mm con precio.
- **Venta con lector**: en «Nueva venta», el campo «Escanear código» (con foco al abrir) agrega la variante con su precio, o suma una unidad si ya estaba. Un código desconocido, el de un producto que tiene variantes o el de un producto inactivo avisan sin tocar la venta.
- Para imprimir: elegir en el diálogo la impresora de etiquetas, el tamaño de papel de la etiqueta, escala 100 % y sin márgenes. El lector tiene que usar la **misma distribución de teclado** que la PC (si no, el `-` del SKU llega cambiado).
- Con etiquetas de 50 mm conviene SKU de hasta unos 12 caracteres para que las barras no queden demasiado finas; con SKU más largos, usar una etiqueta más ancha.

## 0.16.0 (2026-11-23) — Personalización de la landing

Requiere migración: no.

- **Estructura a cargo del SuperAdmin**: Apariencia permite elegir y ordenar las secciones. Se conserva la visibilidad anterior hasta configurar una estructura explícita; el cliente ya no modifica la estructura.
- **Contenido para distintos rubros**: presentación del negocio, servicios, galería con descripciones, novedades y horarios. El cliente edita textos y fotografías de las secciones habilitadas, sin acceder a temas, logos ni diseño. Las imágenes aceptan JPEG, PNG y WebP hasta 4 MB.
- **Con o sin catálogo**: las secciones sin contenido no aparecen y el catálogo sigue dependiendo de su módulo. La portada prioriza el catálogo cuando existe; sin él ofrece WhatsApp, contacto o servicios según disponibilidad.
- **Interfaz pública**: menú móvil sin dependencia de JavaScript, acceso al sistema trasladado al pie, portada sin espacios gráficos vacíos y mejoras de foco y movimiento reducido en ambos temas.
- **Compatibilidad**: las fotografías del tema siguen funcionando como alternativa si el cliente no carga una portada. No se borran archivos ni contenido al ocultar secciones.
- **Editor por pestañas**: secciones habilitadas en el orden del sitio, vista previa reactiva de la sección activa con el tema y los colores actuales, e imágenes temporales antes de guardar. Los elementos de servicios, galería, puntos destacados y preguntas frecuentes se muestran plegados y con títulos identificables.
- **Cambios sin publicar**: aviso de modificaciones pendientes; la vista previa acompaña la edición, el agregado y la eliminación de elementos sin modificar el sitio público hasta guardar.
- **Demostración gastronómica**: seeder `EatViandasSeeder` con contenido y fotografías de «eat! viandas saludables», reutilizando el armado de la demo existente.
- Fuera de esta entrega: borradores persistentes y variantes de composición. La vista previa no publica cambios; guardar contenido sigue actualizando el sitio público.

## 0.15.0 (2026-11-18) — Revisión de calidad, sin funciones nuevas

Requiere migración: no.

- **Pruebas más rápidas y sin cortes**: `composer test` corre en paralelo y sin el límite de 300 s de composer (867 pruebas en 72 s con los módulos apagados y 83 s con todos encendidos, antes 100 a 285 s por pasada). `composer test:seq` las corre una por una para depurar.
- **Consultas**: fuera de producción, cargar una relación «a demanda» ahora lanza una excepción (`preventLazyLoading`). Se corrigieron los N+1 que aparecieron: variantes y su producto, la grilla de stock y las listas de **clientes** y **productos** (la policy de borrar consultaba una vez por fila; ahora usa un dato precargado). Pruebas de presupuesto de consultas por pantalla y de que las listas no hacen más consultas con más filas.
- **Seguridad**:
  - Cabeceras en todas las respuestas (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`; HSTS solo por HTTPS).
  - **Impresión de informes y de órdenes de compra: un usuario desactivado con la sesión abierta ya no puede imprimir** (antes solo se miraba el rol).
  - Logos, favicon e imagen para compartir ya no aceptan **SVG** (podía llevar scripts y se sirve del disco público).
  - El catálogo público limita las peticiones por IP (120 por minuto).
  - **Matriz de autorización** como prueba: cada ruta propia contra invitado, vendedor, administrador, SuperAdmin y administrador inactivo; falla si aparece una ruta sin declarar. Pruebas de que un administrador no puede crear ni ascender a SuperAdmin.
  - `composer audit` y `npm audit`: sin vulnerabilidades.
- **Calidad estática**: Larastan sube al nivel 6 (0 errores).
- **Recorrida en el navegador** con datos reales (ventas a cuenta, vencida y en cuotas, cobro, orden y factura de compra, informes e impresiones): sin errores de consola y con los números cuadrando a mano.
## 0.14.0 (2026-11-18) — Login con Google (Fase 12)

Requiere migración: no (`users.google_id` ya existía).

- **Botón «Ingresar con Google»** en el login del panel del negocio, solo con el módulo encendido (el login de la plataforma no lo lleva). Sin credenciales (`GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET`) el módulo no se puede encender, como hasta ahora.
- **No registra usuarios**: solo entra quien ya existe, está **activo** y es **administrador o vendedor**. Se lo busca por su `google_id` y, si no hay, por su correo (sin distinguir mayúsculas) y se guarda el `google_id`.
- **Vinculación segura**: solo se vincula por correo si Google lo informa como **verificado**, y una cuenta ya vinculada a otra de Google no se pisa.
- **Mismo aviso para todo rechazo**: usuario inexistente, inactivo, SuperAdmin, correo sin verificar o cuenta de otra persona vuelven al login con «Estas credenciales no coinciden con nuestros registros.», para no revelar quién existe. Si el proveedor falla o se deniega el permiso: «No pudimos completar el ingreso con Google».
- **Actividad**: el ingreso queda como cualquier otro; el fallido, como «Intento de ingreso con Google fallido» con el correo (y, si el usuario existe, como su autor: un intento sobre un SuperAdmin queda oculto al cliente).
- Rutas `GET /auth/google/redirect` y `GET /auth/google/callback` en el dominio del panel, con límite de intentos y `404` con el módulo apagado. Siempre se pide elegir la cuenta de Google. Sin `GOOGLE_REDIRECT_URI` se usa la ruta del callback.
- Nuevos: `GoogleLoginController` y la acción `FindUserForGoogleLogin`. README con el alta de las credenciales en Google Cloud Console.
- Con esto están construidas todas las fases del plan base (0 a 12). Fuera de alcance: vincular o desvincular la cuenta de Google desde el perfil, forzar Google como único acceso, login con Google en la plataforma.

## 0.13.0 (2026-11-18) — Cuotas propias (Fase 9)

Requiere migración: sí (tabla `financing_plans` y `sales.financing_surcharge` / `financing_surcharge_percent`; `financing_plan_id`, `installments_count` y `first_due_date` ya existían). Las tablas y columnas existen siempre; con el módulo apagado una venta a cuenta sigue teniendo un único vencimiento.

- **Planes de financiación** (Configuración → Planes de financiación, el administrador del cliente): nombre, cantidad de cuotas (2 a 60) y recargo (0 a 100 %). Un plan usado en ventas no se borra, se desactiva.
- **Ventas en cuotas**: con saldo a cuenta, el formulario de venta ofrece «Plan de pago» (o un solo vencimiento) y muestra en vivo lo que se financia, el recargo, la cuota y el **cronograma**, con el mismo servicio que arma las cuotas de la venta real.
  - **A financiar** = lo que queda a cuenta × (1 + recargo del plan). La cuota es lo financiado dividido la cantidad, **redondeada hacia abajo** a centavos, y **la última absorbe la diferencia** (1.000 en 3 → 333,33 · 333,33 · 333,34).
  - **Vencimientos mensuales** según la regla de Plataforma: **día fijo** (se elige el mes de la primera cuota, por defecto el siguiente) o **el mismo día de la venta** (31/1 → 28/2 → 31/3; en los meses cortos vence el último día).
  - La venta **copia el plan** (cuotas y recargo): cambiarlo o desactivarlo después no la afecta.
  - Cada cuota lleva la **porción de costo** que le corresponde (la última absorbe el redondeo).
- **El recargo va aparte del total de la venta**: es un débito propio en la cuenta del cliente («Recargo de financiación»), así que el saldo del libro sigue siendo la suma de los vencimientos. Al anular la venta se acredita también el recargo y la cuenta queda en cero. Los cobros siguen imputándose del más viejo al más nuevo.
- **Vista de la venta**: el plan elegido, el recargo y el cronograma de cuotas con lo pagado y su estado.
- **Escritorio** (administrador): **ganancia futura** (lo que falta cobrar menos su costo, con el interés), lo que vence el mes próximo y el gráfico de **ingresos proyectados a 12 meses** (por cobrar y ganancia; lo vencido cuenta en el mes actual).
- **Demostración**: dos planes («3 cuotas» y «6 cuotas» +12 %) y dos de las ventas a cuenta se financian en cuotas; sin el módulo, la demo queda como en la 0.12.0.
- Nuevos: `FinancingPlan`, `InstallmentSchedule` (servicio puro), `InstallmentsOverview`, `ProjectedIncomeChart`, `AdministratorChartWidget`. Invariante nueva: `total − cobrado en el acto + recargo = financiado`.
- Pendiente fuera del plan: refinanciar o reprogramar cuotas, recargos por mora, planes por producto o cliente.

## 0.12.0 (2026-11-18) — Cuenta corriente (Fase 8)

Requiere migración: sí (`installments`, `installment_payment` y `account_movements`; las columnas de `sales` y `payments` ya existían). Las tablas existen siempre; con el módulo apagado todo sigue como antes (venta de contado) y no se pierde ningún dato.

- **Ventas a cuenta**: con el módulo, una venta admite un **anticipo opcional** (los cobros de siempre, hasta el subtotal) y el resto queda **a cuenta del cliente**; también puede quedar entera a cuenta, sin cobros. Con saldo, el cliente y la fecha de vencimiento son obligatorios (no puede ser anterior a la venta). La venta nace «Con saldo» y se arma **un único vencimiento** por el saldo. Sin el módulo, toda venta es de contado, como hasta ahora.
- **Cobros** (menú Ventas → Cobros; los registran el administrador y el vendedor): se imputan a los vencimientos del cliente **del más viejo al más nuevo**, o solo a los que se elijan; admiten pagos parciales y adelantados y **no pueden superar la deuda**. El recargo o descuento del medio de pago no cambia la deuda: la baja la base. La venta pasa a «Pagada» cuando se saldan todos sus vencimientos.
- **Cuenta corriente del cliente**: historial inmutable con el saldo acumulado. Una venta con saldo deja un débito por el total y un crédito por lo cobrado en el acto; cada cobro, un crédito por su base. Una venta de contado no genera movimientos.
- **Anular un cobro** (solo administrador, con motivo): devuelve lo imputado, reabre las ventas que se habían saldado y deja un débito de ajuste. El cobro hecho en el acto de una venta no se anula solo: se anula con la venta.
- **Anular una venta con saldo**: no se puede si tiene cobros imputados (hay que anularlos antes). Anula sus vencimientos y deja ajustes que dejan la cuenta en cero.
- **Ficha del cliente** (la ven el administrador y el vendedor): saldo y vencido en la cabecera, acción **Cobrar** y pestañas de **vencimientos**, **cuenta corriente** y **productos comprados**. Los clientes tienen ahora una vista; el vendedor, que no edita, entra por ahí.
- **Escritorio** (administrador): total por cobrar, vencido, clientes con deuda y los vencimientos atrasados con acceso directo a cobrar. La vista de la venta suma su sección de cuenta corriente.
- **Informes** (piden Informes y Cuenta corriente): **Saldos pendientes por cliente** (vencido, una columna por mes con selector de 6, 12, 18 o 24, más adelante y total) y **Cobranza del mes** (vencimientos del mes con teléfono, dirección, días de atraso y saldo; opcionalmente los atrasados). Con impresión. Los informes que no se filtran por Desde/Hasta ya no muestran esos campos (`ReportPage::usesPeriod()`).
- **Demostración**: cinco ventas a cuenta (algunas ya vencidas), tres cobros de ejemplo (uno parcial, una venta saldada y uno anulado) y los saldos del libro. La demo sigue teniendo 25 ventas y no cambia sin el módulo.
- `Payment::scopeVisibleTo` suma los cobros que cargó el propio vendedor. La relación de un cobro con sus vencimientos se llama `imputedInstallments()` porque `payments.installments` ya es la cantidad de cuotas del medio de pago.
- Pendiente: **Cuotas propias** (planes y varias cuotas), que se apoya en este módulo.

## 0.11.0 (2026-11-18) — Informe de compras por proveedor

Requiere migración: no.

- **Compras por proveedor** (grupo «Informes»): facturas de compra vigentes del período, sin anuladas, **agrupadas por proveedor** (facturas, total comprado y factura promedio) o **en detalle**, una fila por factura con su orden de compra. Filtros por fechas y proveedor. Con indicadores resumen, tabla compacta y versión imprimible (`/informes/compras/imprimir`) como los otros informes.
- **Pide los módulos Informes y Compras a la vez**: con cualquiera de los dos apagado, la pantalla y la impresión responden 404 y no aparece en el menú. El vendedor recibe 403.
- `BelongsToModule` suma el gancho opcional `alsoRequires()` para las páginas que dependen de más de un módulo (menú, acceso y ruta lo respetan); la clase no tiene que redefinir `canAccess()`. Ver `docs/modulos.md`.
- Servicio `PurchasesReport`. El informe comparte pantalla, filtros y estilos con los de Ventas y Cobros.
- Con esto los informes de Cuenta corriente (Saldos pendientes y Cobranza del mes) son los únicos que faltan: esperan a ese módulo.

## 0.10.0 (2026-11-17) — Compras (Fase 10)

Requiere migración: sí (`suppliers`, `purchase_orders`, `purchase_order_items`, `purchases`, `purchase_items` y `products.supplier_id`). Las tablas existen siempre; con el módulo apagado no se muestra nada y no se pierde ningún dato.

- **Módulo Compras** (grupo «Compras» del panel, solo administrador; el vendedor no ve costos de compra):
  - **Proveedores**: alta, edición y baja. Con órdenes o facturas no se borran: se desactivan.
  - **Órdenes de compra** (`OC-00001`): borrador → emitida → recibida, o anulada. Se editan solo en borrador, se emiten, se imprimen (sin costos) y se anulan con motivo.
  - **Facturas de compra**: directas o desde una orden emitida, con lo pedido precargado para corregir lo que llegó (0 si no llegó). No llega más de lo pedido ni un producto que no estaba en la orden, y tiene que llegar algo. El número no se repite por proveedor entre las vigentes (se permite si la anterior está anulada).
  - **Anular una factura** pide motivo, deja la orden otra vez emitida y no cambia el costo de los productos.
- **El costo de la factura queda como costo del producto** (la última gana). Las ventas ya hechas no cambian: copiaron su costo al vender.
- **Con Stock**: la factura suma a cada variante (movimiento «Compra») y anularla resta lo que sumó. **No se puede anular si dejaría el stock en negativo**; la anulación se rechaza completa. Con Stock apagado no se toca el stock, y anular revierte exactamente lo que esa factura sumó aunque el módulo se haya apagado después.
- **Proveedor habitual** en el producto (campo, columna y filtro, solo con Compras encendido).
- **Escritorio** (administrador): compras del mes, órdenes sin recibir y últimas compras.
- **Impresión de la orden** en otra pestaña (`/compras/ordenes/{id}/imprimir`); el vendedor recibe 403 y con el módulo apagado, 404.
- Acciones `CreatePurchaseOrder`, `UpdatePurchaseOrder`, `IssuePurchaseOrder`, `CancelPurchaseOrder`, `CreatePurchase` y `CancelPurchase`; eventos `PurchaseCreated` y `PurchaseCancelled` (dentro de la transacción, como los de venta) que escucha Stock. Altas, emisiones y anulaciones quedan en la actividad.
- Pendiente para una próxima versión: el informe «Compras por proveedor» (llegó en la 0.11.0) y las compras en la demostración.

## 0.9.0 (2026-11-17) — Informes (Fase 11, parte núcleo)

Requiere migración: no.

- **Módulo Informes** (grupo «Informes» en el panel, solo administrador): dos informes que no dependen de otros módulos.
  - **Ventas y ganancia**: ventas del período sin anuladas, con subtotal, ajustes de los medios de pago, total, costo, ganancia y margen. Filtros por fechas y vendedor; en detalle, agrupadas por vendedor o por producto.
  - **Cobros realizados**: cobros del período sin anulados, con subtotales por medio de pago y por cobrador. Filtros por fechas, medio de pago y cobrador.
- **La ganancia no incluye el recargo ni el descuento del medio de pago**: es el subtotal de los ítems menos su costo. El ajuste se muestra aparte.
- **Versión imprimible** de cada informe en otra pestaña (`/informes/{ventas|cobros}/imprimir`, HTML con estilos de impresión, sin librería de PDF), con los mismos filtros que la pantalla. El vendedor recibe 403; con el módulo apagado, 404.
- Servicios `SalesReport` y `PaymentsReport` (`app/Services/Reports`): pantalla e impresión salen del mismo resultado, y los totales coinciden con los del escritorio.
- **Distribución**: filtros en una fila, indicadores resumen arriba (ventas, costo, ganancia y margen; cobrado, base, ajustes y cobros), botón «Imprimir» en el encabezado y tablas compactas que no parten el texto, con los importes en pesos sin repetir el símbolo en cada celda. Los subtotales de cobros van lado a lado.
- La tabla de los informes es simple (no se ordena ni se ocultan columnas, a diferencia de las listas del panel).
- Pendiente: Saldos pendientes y Cobranza del mes (requieren Cuenta corriente) y Compras por proveedor (requiere Compras).

## 0.8.4 (2026-11-17) — Modo de color de la landing

Requiere migración: no.

- **Modo de color** en Plataforma → Apariencia: la landing y el catálogo pueden verse siempre en claro, siempre en oscuro o en automático (según el dispositivo de cada visitante). Sin elegir, queda en automático, como hasta ahora. No cambia el panel.
- En modo claro la landing no lleva la regla de modo oscuro; en modo oscuro usa siempre el fondo y el texto oscuros de la combinación. Se declara también `color-scheme` para que los controles del navegador acompañen.
- **Logos según el modo** (tema Clásico): en claro usa solo el logo claro, aunque haya uno oscuro; en oscuro usa el logo para fondo oscuro (o el claro si no hay); en automático lleva los dos y el dispositivo elige.
- `Appearance` suma `modeKey()`, `setMode()` y `MODES`.

## 0.8.3 (2026-11-17) — Usuarios y actividad en la plataforma

Requiere migración: no.

- **Plataforma (`plataforma.`)**: nuevas secciones **Usuarios** y **Actividad**, solo para el SuperAdmin. Usuarios lista a todos, SuperAdmin incluido, y permite crearlos con cualquier rol, editarlos (nombre, correo, contraseña, rol y estado) y borrarlos si no tienen operaciones. Actividad muestra el registro completo, también lo hecho por y sobre los SuperAdmin, con filtros por usuario, evento y período; es de solo lectura.
- En la plataforma nadie puede cambiar el rol ni desactivar su propia cuenta, para no quedarse sin SuperAdmin.
- El panel del cliente no cambia: sigue sin mostrar ni ofrecer el rol SuperAdmin ni su actividad. `UserPolicy` y `ActivityLogPolicy` admiten al SuperAdmin; `Role::assignable()` y las acciones `CreateUser` y `UpdateUser` reciben `withSuperAdmin` (solo lo usa la plataforma).
- El formulario y las tablas de Usuarios y Actividad se reusan entre los dos paneles.
- **Textos**: el acceso dice «Ingrese a su cuenta» y el inicio de los dos paneles se llama «Dashboard» (traducciones propias en `lang/vendor/filament-panels/es`).

## 0.8.2 (2026-11-17) — Recorte automático de logos

Requiere migración: no.

- **Los logos se recortan solos** al guardar Apariencia: se quita el fondo liso o transparente que rodea al dibujo y se deja un poco de aire. Un logo apaisado dentro de un cuadrado blanco ya no se ve diminuto en la barra. Aplica a los logos para fondo claro, para fondo oscuro y del acceso; no al favicon ni a la imagen para compartir.
- Es idempotente y reemplaza el archivo en el mismo lugar: para arreglar un logo ya subido alcanza con abrir Apariencia y guardar. Sin la extensión GD, o con un archivo que no es una imagen, no hace nada.
- Nuevo servicio `ImageTrimmer`.

## 0.8.1 (2026-11-16) — Portada y marca

Requiere migración: no.

- **Logos más grandes**: en el panel el logo pasa de 24 a 40 px de alto y en la pantalla de acceso a 112 px; en la landing, de 32 a 48 px (36 px en el celular) en los dos temas.
- **«Ingresar al sistema» solo en la barra**: se quitó de la portada y del pie. En la barra pasa a ser un botón de borde y en el celular dice «Ingresar».
- **Botón «Catálogo» destacado**: es el botón principal de la barra y se ve también en el celular (antes se ocultaba con el resto del menú).
- **Portada del tema Clásico**: en lugar del recuadro de color, pasa las fotos de los productos destacados (hasta 8) con un fundido y un acercamiento suave, solo con CSS; cada foto muestra el nombre y enlaza a la ficha. Con una sola foto queda fija; si hay una imagen de portada cargada en Apariencia se muestra esa; respeta «reducir movimiento».
- `public/build` recompilado.

## 0.8.0 (2026-11-16) — Fase 7: instalación rápida

Requiere migración: no.

- **`DemoSeeder`** (`composer demo`): tienda de ropa de ejemplo armada con las acciones reales del sistema — 12 productos en 5 categorías, 6 clientes, 25 ventas en tres meses con pagos combinados y dos anuladas, dos vendedores de prueba, contenido del sitio «Casa Lila». Solo arma lo de los módulos encendidos: variantes con Variantes; existencias, mínimos y movimientos con Stock; publicación, destacados, precio oculto, borrador e imágenes con Catálogo.
- La demostración **no corre en producción** y **no hace nada si ya hay ventas**. En producción `migrate:fresh`, `db:wipe` y similares quedan prohibidos.
- `composer demo` ahora es `migrate:fresh --seed` → `modules:enable --all` → `DemoSeeder`. Nuevo `composer deploy` para actualizar una instalación sin Node.
- Nueva variable `DEMO_PASSWORD` (contraseña de los usuarios de demostración).
- **`public/build` se versiona**: el servidor no compila.
- **README de despliegue**: arquitectura, instalación local, variables, alta de un cliente, producción y actualización.

## 0.7.0 (2026-11-16) — Fase 6: catálogo web

Requiere migración: sí (`products` suma `slug`, `is_published`, `is_featured`, `show_price`, `images`; los productos existentes reciben su slug).

- **Catálogo web** (módulo): páginas públicas `/catalogo`, `/catalogo/{categoría}` y `/producto/{slug}` con el diseño del tema elegido, buscador, filtro por categoría, paginación y consulta por WhatsApp con el nombre y el enlace del producto. Con el módulo apagado responden 404.
- Solo se muestran productos **activos y publicados**; lo demás responde 404. Nunca se expone costo, SKU interno ni cantidades de stock.
- **Pestaña Catálogo** en el producto (solo con el módulo): publicado, destacado en la landing, mostrar precio («Consultar precio» si no), enlace (slug) e imágenes (hasta 6, en el disco público; se borran al quitarlas o al borrar el producto). Columna y filtro «Publicado» en la lista de productos.
- **Slug** automático, único y estable al renombrar; editable a mano.
- **Variantes y Stock** en la ficha: con Variantes se listan las variantes activas y «Desde» si los precios difieren; con Stock se marcan «Agotado» (producto y variantes) o se ocultan según el ajuste «Mostrar los productos agotados» del módulo.
- **Landing**: la sección Catálogo muestra las categorías con productos publicados (con enlace) y los destacados, más «Ver catálogo completo». El menú enlaza al catálogo aunque la sección de la landing esté oculta.
- `LandingController` y `CatalogController` comparten el armado de datos del tema (`BuildsLandingData`).

## 0.6.0 (2026-11-16) — Fase 5: sitio, apariencia y temas

Requiere migración: sí (tabla `settings`). Requiere `php artisan storage:link` (lo hace `composer setup`) y `npm run build`.

- **Temas** (`config/themes.php`): *Clásico* y *Boutique*, con vistas de landing, hoja de estilos y vista previa propias, y cuatro combinaciones de colores cada uno. Se compilan siempre: cambiar de tema no requiere compilar. Contraste de cada combinación verificado por test (WCAG AA).
- **Landing** por secciones (portada, por qué elegirnos, catálogo, preguntas frecuentes, contacto), cada una activable y oculta si no tiene contenido. Botón «Ingresar al sistema» hacia el panel conservando esquema y puerto; título, favicon e imagen para compartir.
- **Apariencia** (panel `superadmin`): tema, combinación de colores, logos (fondo claro, fondo oscuro y acceso), favicon, imagen para compartir y recursos gráficos del tema. Los archivos van al disco `public`.
- **Sitio** (panel `admin` → Configuración → Sitio): nombre, lema, portada, puntos a destacar, preguntas frecuentes, contacto, redes y secciones visibles. Los cambios quedan en la actividad con antes y después.
- **Colores** (módulo *Colores personalizables*, Configuración → Colores): el administrador elige entre las combinaciones del tema actual, con vista previa; sin selector libre.
- Los paneles toman el nombre, los logos, el favicon, el color principal y la tipografía de los títulos de la base, sin recompilar. Sin logo se muestra el nombre del negocio.
- `Settings` (ajustes cacheados), `Appearance`, `SiteContent` y `Contrast` (servicios); `docs/temas.md`.

## 0.5.0 (2026-11-16) — Fase 4: Stock y Variantes

Requiere migración: sí (`product_variants.stock` y `min_stock`; tabla `stock_movements`).

- **Variantes** (módulo): tabla de variantes en el producto con generación por combinación de atributos (S, M, L × Negro, Blanco = 6; no duplica las existentes), SKU y precio propio o heredado, y activar/desactivar. Una variante con ventas o movimientos no se borra. Con el módulo apagado cada producto tiene solo su variante implícita; con él encendido la implícita no se ofrece si el producto tiene variantes con nombre (`ProductVariant::sellable()`).
- **Stock** (módulo): existencias por variante, historial inmutable de movimientos (`stock_movements`, con motivo), ajuste manual y **Carga de stock** por grilla (stock inicial y recuentos, un ajuste por cada diferencia), stock mínimo, valor del stock y productos con stock bajo en el escritorio (solo administrador), existencia visible al vender, y columna Stock en Productos.
- La venta descuenta el stock y la anulación lo devuelve con movimientos propios. Por defecto **avisa** y deja el stock en negativo; con el ajuste **bloquear** rechaza la venta que no alcanza sin dejar nada a medias. El panel superadmin advierte cuántas variantes están en negativo antes de bloquear.
- Los eventos `SaleCreated` y `SaleCancelled` se disparan **dentro de la transacción** de la venta (con el usuario que actúa): si un módulo rechaza, se revierte todo. Anular revierte exactamente lo que descontó la venta, aunque el módulo se haya apagado después.
- `LogsActivity` puede omitir registros y atributos que ya tienen historial propio (el stock no llena la actividad).
- Trait `RelationManagerBelongsToModule` y bases `AdministratorPage`, `AdministratorStatsWidget` y `AdministratorTableWidget` para lo que solo ve el administrador. El test de arquitectura ahora recorre también los relation managers.

## 0.4.0 (2026-11-16) — Fase 3: núcleo comercial

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

## 0.3.0 (2026-11-16) — Fase 2: módulos y panel superadmin

Requiere migración: sí (tabla `modules`).

- Módulos habilitables: enum `App\Enums\Module` (9 módulos, con dependencias y configuración requerida), tabla `modules` y servicio `App\Services\Modules` (fachada `App\Facades\Modules`) con caché. Fila ausente = módulo apagado.
- Dependencias validadas en ambos sentidos: no se enciende sin lo que requiere ni con configuración faltante (Login con Google sin credenciales), y no se apaga si otro encendido lo usa.
- Puntos de corte: traits `BelongsToModule` (recursos y páginas: acceso, navegación y 404 en la ruta) y `WidgetBelongsToModule`, middleware `module:{key}`, directiva `@module`, y convenciones para campos (`->visible`) y tareas (`->when`). Ver `docs/modulos.md`.
- Panel `superadmin`: página Módulos con interruptores (bloqueados con el motivo cuando corresponde) y los ajustes de cada módulo (Stock, Variantes, Cuotas propias, Catálogo web).
- Comandos `modules:list`, `modules:enable {key|--all}` y `modules:disable {key}`. `composer demo` enciende todos los módulos posibles.
- La suite corre con solo el núcleo y con todos los módulos encendidos (`composer test`, `TEST_MODULES=none|all`). Test de arquitectura: todo recurso, página y widget fuera del núcleo declara su módulo.
- La versión en la barra superior tiene separación respecto del menú de usuario.

## 0.2.0 (2026-11-16) — Fase 1: usuarios y base del panel

Requiere migración: sí (`users` suma `role`, `active`, `is_default_seller`, `google_id`; tablas `activity_logs` y `table_column_preferences`).

- Roles `superadmin`, `admin` y `seller`; acceso por panel con `User::canAccessPanel()`. Un usuario inactivo no entra a ningún panel y un rol equivocado recibe el mismo mensaje que con credenciales incorrectas.
- SuperAdmin invisible para el cliente: scope `User::visibleToClient()` (listados, selectores y filtros), edición con 404, rol no asignable y sus acciones y las hechas sobre él fuera de la actividad (`ActivityLog::visibleToClient()`).
- Recurso Usuarios (panel `admin`) con acciones `CreateUser`, `UpdateUser`, `DeleteUser`: vendedor por defecto único, nadie se borra a sí mismo, contraseña vacía al editar no se cambia.
- Registro de actividad inmutable: ingresos, salidas e intentos fallidos; altas, cambios y bajas con valores antes y después (trait `LogsActivity`), sin contraseñas. Pantalla Actividad con filtros por usuario, evento y período.
- Columnas de todas las tablas ocultables, guardadas en la base por usuario y por tabla (`PersistsTableColumns`).
- Botones de crear, editar, ver y borrar con solo ícono y tooltip; tablas compactas; versión visible en la barra superior y en el login; edición del propio perfil en ambos paneles.
- `InitialUsersSeeder` (admin y superadmin desde `.env`), repetible sin duplicar ni pisar contraseñas.

## 0.1.0 (2026-11-16) — Fase 0: preguntas y arranque

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
