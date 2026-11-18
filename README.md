# Plataforma de gestión comercial

Plataforma modular para comercios, hecha con Laravel y Filament. **Una instalación por cliente**: cada negocio tiene su propia base de datos, su `.env` y sus tres dominios, y se le encienden solo los módulos que contrató.

- Versión actual: ver `config/platform.php` y [`CHANGELOG.md`](CHANGELOG.md).
- Plan y reglas del producto: [`PLAN-BASE.md`](PLAN-BASE.md). Decisiones de diseño: [`docs/decisiones.md`](docs/decisiones.md), [`docs/modulos.md`](docs/modulos.md) y [`docs/temas.md`](docs/temas.md).

## Arquitectura en breve

**Tres dominios, una aplicación.** Ningún host está escrito en el código: salen del `.env`. Cualquier otro host responde 404.

| Variable | Qué sirve |
|---|---|
| `LANDING_DOMAIN` | Sitio público: landing y catálogo web |
| `PANEL_DOMAIN` | Panel del negocio (administrador y vendedores) |
| `SUPERADMIN_DOMAIN` | Panel de la plataforma: módulos, tema y colores |

**Roles.** Administrador y Vendedor son del cliente. El SuperAdmin es del proveedor: no aparece en ninguna lista del cliente.

**Módulos.** El núcleo (clientes, categorías, productos, medios de pago, ventas de contado, escritorio) siempre está. Todo lo demás se enciende o apaga por instalación desde el panel de superadmin o con `php artisan modules:enable`. Apagar un módulo oculta la función y no borra datos.

## Requisitos

- PHP 8.3 con las extensiones habituales de Laravel y `bcmath`. `gd` es opcional: con ella la demostración genera imágenes de ejemplo.
- MySQL 8 o similar.
- Composer. Node solo para desarrollar: **`public/build` está versionado**, así que un servidor no necesita Node.

## Instalación local (Laragon u otro)

1. Crear la base de datos vacía y clonar el repositorio.
2. Hosts de desarrollo: los dominios `*.localhost` resuelven solos en los navegadores modernos (por ejemplo `negocio.localhost`, `app.negocio.localhost`, `plataforma.negocio.localhost`). Si el sistema no los resuelve, agregarlos al archivo `hosts`.
3. `composer setup`: instala dependencias, crea el `.env` desde `.env.example`, genera la clave, migra, enlaza `storage` y compila los assets.
4. Completar el `.env`: `APP_NAME`, base de datos, los tres dominios, `APP_URL` (el del panel) y los usuarios iniciales (`ADMIN_*` y `SUPERADMIN_*`).
5. `php artisan db:seed`: crea el administrador, el superadmin y los medios de pago. Se puede repetir sin duplicar ni pisar lo que ya cambiaste.
6. `php artisan serve --port=8000` (y `npm run dev` si vas a tocar el frontend), o `composer dev`. Abrir `http://app.negocio.localhost:8000`.

### Demostración con datos

`composer demo` **borra la base**, la deja con datos de ejemplo de una tienda de ropa y enciende todos los módulos posibles:

1. `migrate:fresh --seed`
2. `modules:enable --all` (omite Login con Google si faltan sus credenciales)
3. `db:seed --class=DemoSeeder`

Deja 12 productos con variantes, stock, imágenes y catálogo publicado, 6 clientes, 25 ventas en tres meses (con pagos combinados y dos anuladas) y dos vendedores de prueba (`ana@demo.test` y `luis@demo.test`, contraseña `DEMO_PASSWORD`). El administrador es el del `.env`.

La demostración usa las acciones reales del sistema, solo arma lo de los módulos encendidos, **no corre en producción** y no hace nada si ya hay ventas. En producción además `migrate:fresh` y `db:wipe` quedan prohibidos.

## Variables del `.env`

El archivo [`.env.example`](.env.example) está comentado. Las propias de la plataforma:

| Variable | Para qué |
|---|---|
| `LANDING_DOMAIN`, `PANEL_DOMAIN`, `SUPERADMIN_DOMAIN` | Los tres hosts |
| `TRUSTED_PROXIES` | Proxies de confianza (`*` detrás de un proxy propio) |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Administrador inicial |
| `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | SuperAdmin inicial |
| `SELLER_SEES_ALL_SALES` | `false`: el vendedor ve solo sus ventas; `true`: ve todas |
| `DEMO_PASSWORD` | Contraseña de los usuarios de demostración |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Módulo Login con Google; sin ellas no se puede habilitar |

### Login con Google

El módulo agrega el botón «Ingresar con Google» al login del panel del negocio (no al de la plataforma). **No registra usuarios**: solo entra quien ya existe, está activo y es administrador o vendedor; se lo busca por su cuenta de Google ya vinculada o por su correo (que Google tiene que haber verificado), y en el primer ingreso se vincula la cuenta. Cualquier otro caso vuelve al login con el mismo aviso que una contraseña incorrecta.

Alta de las credenciales (una por cliente), en [Google Cloud Console](https://console.cloud.google.com/) → *APIs y servicios* → *Credenciales*:

1. Crear un **ID de cliente de OAuth** de tipo *Aplicación web*.
2. En *URI de redireccionamiento autorizados* cargar `https://{PANEL_DOMAIN}/auth/google/callback` (en local, la misma ruta con `http://` y el puerto).
3. Copiar el ID y el secreto al `.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y, si hace falta, `GOOGLE_REDIRECT_URI`) y correr `php artisan config:cache` si la configuración está cacheada.
4. Encender el módulo: `php artisan modules:enable google-login` o desde el panel de superadmin. Sin credenciales no se puede encender.

## Alta de un cliente nuevo

1. Crear la base de datos y el `.env` (dominios, usuarios iniciales, credenciales de Google si aplica).
2. `composer setup`.
3. `php artisan db:seed`. Para mostrar el sistema con datos, `composer demo` (solo en un entorno de prueba).
4. Habilitar los módulos según el abono: `php artisan modules:enable <módulo>` (o `--all`), o desde el panel de superadmin. `php artisan modules:list` muestra el estado.
5. En el panel de superadmin: elegir tema y colores, subir logotipos, favicon y recursos gráficos.
6. En el panel del negocio: nombre, textos y datos de contacto en *Configuración → Sitio*.
7. Apuntar el DNS y el proxy a los tres dominios.

## Despliegue en producción

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`, los tres dominios reales y `APP_URL` con `https`.
- Un proxy o servidor web con SSL delante de los tres dominios, todos apuntando a `public/`. Con proxy, configurar `TRUSTED_PROXIES`.
- `php artisan storage:link` para servir las imágenes subidas (el servidor web tiene que seguir enlaces simbólicos).
- Permisos de escritura en `storage/` y `bootstrap/cache/`.
- Respaldos: la base de datos y `storage/app/public` (imágenes y logotipos) son lo único que no está en el repositorio.
- No cargar la demostración: se niega a correr y `migrate:fresh` queda prohibido.

## Actualizar una instalación

Sin Node en el servidor, porque `public/build` viaja con el repositorio:

```
git fetch --tags && git checkout vX.Y.Z
composer deploy
```

`composer deploy` hace `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `storage:link`, `optimize` y `filament:optimize`. Las migraciones son comunes a todos los clientes: no hay variantes por instalación. El `CHANGELOG.md` indica en cada versión si requiere migración.

## Pruebas y calidad

| Comando | Qué hace |
|---|---|
| `composer test` | La suite completa dos veces: solo con el núcleo y con todos los módulos encendidos |
| `composer analyse` | Larastan, nivel 5 |
| `composer lint` | Pint (formato) |

Un cambio no se cierra con tests en rojo en ninguno de los dos modos.

## Forma de trabajo

- Cada fase o versión va en una rama `fase-N`, con su entrada en `CHANGELOG.md`, la `version` de `config/platform.php` y un tag `vX.Y.Z`. Se mergea a `master` al cerrarla.
- Antes del tag: `npm run build` y commitear `public/build`.
- Archivos nuevos con `php artisan make:*`; tests con Pest en `tests/Feature/FaseN`.
- No se borran tests ni se agregan dependencias sin aprobación.
