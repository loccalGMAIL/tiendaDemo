# Temas, colores y marca

La landing y el panel toman su aspecto de un **tema** (diseño completo, común a toda instalación), una **combinación de colores** de ese tema y la **marca** del cliente (logos, favicon, imagen para compartir y recursos gráficos del tema).

| Qué | Quién lo elige | Dónde |
|---|---|---|
| Tema, combinación, logos, favicon, imagen para compartir, recursos del tema | SuperAdmin | Panel `superadmin` → Apariencia (siempre disponible) |
| Combinación de colores del tema actual | Administrador del cliente, solo con el módulo *Colores personalizables* | Panel `admin` → Configuración → Colores |
| Textos, contacto, redes y secciones visibles | Administrador del cliente | Panel `admin` → Configuración → Sitio |

El cliente nunca cambia de tema, logotipos ni recursos gráficos, y no hay selector de color libre: solo elige entre combinaciones ya probadas.

## Cómo está armado

- `config/themes.php` declara cada tema: nombre, descripción, vista previa (`public/images/themes/*.svg`), vistas de la landing (`resources/views/landings/{tema}`), hoja de estilos (`resources/css/themes/{tema}.css`), tipografía, recursos gráficos y combinaciones.
- Todos los temas se compilan siempre (`vite.config.js`): cambiar de tema no requiere compilar ni desplegar.
- Los colores de la combinación se inyectan como variables CSS (`--brand`, `--on-brand`, `--accent`, `--surface`, `--text` y sus pares oscuros) y las hojas de estilos los usan con `bg-brand`, `text-on-brand`, `text-ink`, etc. Cambiar de combinación no recompila nada.
- En el panel, un único tema de Filament (`resources/css/filament/admin/theme.css`): el tema elegido cambia colores (`Panel::colors()` con el color de marca), tipografía de los títulos y logo; no la estructura.
- La elección y el contenido viven en la tabla `settings` (`App\Services\Settings`); los archivos, en el disco `public` (`storage/app/public/branding`), por lo que hace falta `php artisan storage:link` (lo hace `composer setup`).
- Sin logo, la landing y el panel muestran el nombre del negocio como texto.

## Agregar una combinación de colores

En `config/themes.php`, dentro de `palettes` del tema:

```php
'cielo' => [
    'name' => 'Cielo',
    'primary' => '#0369a1', 'on_primary' => '#ffffff', 'accent' => '#38bdf8',
    'surface' => '#ffffff', 'text' => '#0f172a',
    'surface_dark' => '#0f172a', 'text_dark' => '#f8fafc',
],
```

Un test (`TemasTest`) verifica que `on_primary` sobre `primary`, `text` sobre `surface` y `text_dark` sobre `surface_dark` tengan un contraste de al menos 4,5 (WCAG AA). Si una combinación no lo cumple, el test falla.

## Agregar un tema

1. Declararlo en `config/themes.php` (vistas, hoja de estilos, vista previa, tipografía, recursos y al menos dos combinaciones).
2. Crear `resources/views/landings/{tema}/` con `index`, `header`, `hero`, `value`, `catalog`, `faq`, `contact` y `footer` (la landing incluye cada sección visible con `@include('landings.{tema}.'.$seccion)`); el `<head>` común es `landings.partials.head`.
3. Crear `resources/css/themes/{tema}.css` (importa Tailwind y declara los colores con `@theme inline` apuntando a las variables) y agregarla a `vite.config.js`.
4. Crear la vista previa en `public/images/themes/{tema}.svg`.
5. Los tests recorren todos los temas y combinaciones: comprueban que existan los archivos, que cumplan el contraste y que la landing responda.
