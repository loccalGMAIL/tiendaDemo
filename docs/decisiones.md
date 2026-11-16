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

## Entorno local

- PHP 8.3.30 (Laragon), Composer 2.10, Node 24, MySQL 8.4.3 (Laragon).
- Base de datos: `tiendademo` (utf8mb4_unicode_ci).
- Versiones instaladas: Laravel 13.42, Filament 5.11, Pest 4.7, Larastan 3.15, Boost 2.10, Socialite 5.32.
