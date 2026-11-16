# Changelog

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
