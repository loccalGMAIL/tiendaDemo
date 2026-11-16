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

## Entorno local

- PHP 8.3.30 (Laragon), Composer 2.10, Node 24, MySQL 8.4.3 (Laragon).
- Base de datos: `tiendademo` (utf8mb4_unicode_ci).
- Versiones instaladas: Laravel 13.42, Filament 5.11, Pest 4.7, Larastan 3.15, Boost 2.10, Socialite 5.32.
