<?php

return [

    /*
    | Versión semántica de la plataforma. Debe coincidir con CHANGELOG.md y el
    | tag de git.
    */
    'version' => '0.22.0',

    /*
    | Contraseña de los usuarios de demostración (DemoSeeder). Solo para entornos de prueba.
    */
    'demo_password' => env('DEMO_PASSWORD', 'password'),

    /*
    | Ventas. Por defecto un vendedor ve solo sus ventas; con SELLER_SEES_ALL_SALES=true ve todas.
    */
    'sales' => [
        'seller_sees_all' => (bool) env('SELLER_SEES_ALL_SALES', false),
    ],

    /*
    | Usuarios iniciales que crea el seeder (se puede correr varias veces).
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'superadmin' => [
        'name' => env('SUPERADMIN_NAME'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

];
