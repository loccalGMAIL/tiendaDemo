# Changelog

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
