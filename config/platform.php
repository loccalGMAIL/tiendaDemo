<?php

return [

    /*
    | Versión semántica de la plataforma. Debe coincidir con CHANGELOG.md y el
    | tag de git.
    */
    'version' => '0.3.0',

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
