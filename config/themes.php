<?php

/*
|--------------------------------------------------------------------------
| Temas de la landing y del panel
|--------------------------------------------------------------------------
|
| Los temas viven en el repositorio y son comunes a toda instalación; solo el
| SuperAdmin elige cuál usa cada cliente (panel superadmin → Apariencia). Todos
| se compilan siempre, así cambiar de tema no requiere compilar ni desplegar.
|
| Cada tema declara su diseño de landing (vistas y hoja de estilos), su
| tipografía, los recursos gráficos que admite y sus combinaciones de colores.
| Las combinaciones están probadas: un test verifica que el texto se lea
| (contraste >= 4,5) sobre el color principal y sobre el fondo, en claro y en
| oscuro. En el panel, el tema cambia colores, tipografía y logo; no la estructura.
|
| Colores de cada combinación:
|   primary      color de marca (botones, enlaces, panel)
|   on_primary   texto sobre el color de marca
|   accent       detalle decorativo
|   surface/text fondo y texto en modo claro
|   surface_dark/text_dark   fondo y texto en modo oscuro
|
*/

return [

    'default' => 'clasico',

    'themes' => [

        'clasico' => [
            'name' => 'Clásico',
            'description' => 'Claro, limpio y con mucho aire. Sirve para cualquier rubro.',
            'preview' => 'images/themes/clasico.svg',
            'views' => 'landings.clasico',
            'css' => 'resources/css/themes/clasico.css',
            'font' => [
                'body' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
                'heading' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            ],
            'assets' => [
                'hero' => ['label' => 'Imagen del hero', 'help' => 'Se muestra junto al título principal. Horizontal, de 1200 × 800 px o más.'],
                'value' => ['label' => 'Imagen de la propuesta', 'help' => 'Acompaña la sección «Por qué elegirnos».'],
            ],
            'palettes' => [
                'ambar' => [
                    'name' => 'Ámbar',
                    'primary' => '#b45309', 'on_primary' => '#ffffff', 'accent' => '#f59e0b',
                    'surface' => '#ffffff', 'text' => '#1c1917',
                    'surface_dark' => '#1c1917', 'text_dark' => '#fafaf9',
                ],
                'oceano' => [
                    'name' => 'Océano',
                    'primary' => '#0369a1', 'on_primary' => '#ffffff', 'accent' => '#38bdf8',
                    'surface' => '#ffffff', 'text' => '#0f172a',
                    'surface_dark' => '#0f172a', 'text_dark' => '#f8fafc',
                ],
                'bosque' => [
                    'name' => 'Bosque',
                    'primary' => '#15803d', 'on_primary' => '#ffffff', 'accent' => '#84cc16',
                    'surface' => '#ffffff', 'text' => '#14201a',
                    'surface_dark' => '#14201a', 'text_dark' => '#f7fee7',
                ],
                'rosa' => [
                    'name' => 'Rosa',
                    'primary' => '#be185d', 'on_primary' => '#ffffff', 'accent' => '#f472b6',
                    'surface' => '#ffffff', 'text' => '#1f1721',
                    'surface_dark' => '#1f1721', 'text_dark' => '#fdf2f8',
                ],
            ],
        ],

        'boutique' => [
            'name' => 'Boutique',
            'description' => 'Elegante, con portada oscura y tipografía con serifas. Para marcas con carácter.',
            'preview' => 'images/themes/boutique.svg',
            'views' => 'landings.boutique',
            'css' => 'resources/css/themes/boutique.css',
            'font' => [
                'body' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
                'heading' => "Georgia, 'Times New Roman', Times, serif",
            ],
            'assets' => [
                'hero' => ['label' => 'Imagen de fondo de la portada', 'help' => 'Se muestra detrás del título, con una capa oscura. Horizontal, de 1600 × 900 px o más.'],
                'about' => ['label' => 'Imagen de la propuesta', 'help' => 'Acompaña la sección «Por qué elegirnos».'],
            ],
            'palettes' => [
                'noche' => [
                    'name' => 'Noche dorada',
                    'primary' => '#d4af37', 'on_primary' => '#1a1a1a', 'accent' => '#f3e5ab',
                    'surface' => '#faf7f2', 'text' => '#1f1a17',
                    'surface_dark' => '#14110f', 'text_dark' => '#f5efe6',
                ],
                'vino' => [
                    'name' => 'Vino',
                    'primary' => '#7f1d1d', 'on_primary' => '#ffffff', 'accent' => '#e8b4b4',
                    'surface' => '#fbf6f5', 'text' => '#2a1515',
                    'surface_dark' => '#1c0f0f', 'text_dark' => '#f9ecec',
                ],
                'salvia' => [
                    'name' => 'Salvia',
                    'primary' => '#3f6212', 'on_primary' => '#ffffff', 'accent' => '#bef264',
                    'surface' => '#f7f9f2', 'text' => '#1a2410',
                    'surface_dark' => '#121a0b', 'text_dark' => '#f1f8e4',
                ],
                'tinta' => [
                    'name' => 'Tinta',
                    'primary' => '#1e3a8a', 'on_primary' => '#ffffff', 'accent' => '#93c5fd',
                    'surface' => '#f6f8fc', 'text' => '#111a33',
                    'surface_dark' => '#0b1226', 'text_dark' => '#eef3ff',
                ],
            ],
        ],

    ],

];
