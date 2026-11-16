<?php

/*
|--------------------------------------------------------------------------
| Dominios de la plataforma
|--------------------------------------------------------------------------
|
| Una aplicación, tres hosts. Ningún host va escrito en el código: todo sale
| de aquí. Cualquier otro host responde 404.
|
*/

return [
    'landing' => env('LANDING_DOMAIN', 'localhost'),
    'panel' => env('PANEL_DOMAIN', 'app.localhost'),
    'superadmin' => env('SUPERADMIN_DOMAIN', 'plataforma.localhost'),
];
