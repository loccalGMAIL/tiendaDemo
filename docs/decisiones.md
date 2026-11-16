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

## Entorno local

- PHP 8.3.30 (Laragon), Composer 2.10, Node 24, MySQL 8.4.3 (Laragon).
- Base de datos: `tiendademo` (utf8mb4_unicode_ci).
- Versiones instaladas: Laravel 13.42, Filament 5.11, Pest 4.7, Larastan 3.15, Boost 2.10, Socialite 5.32.
