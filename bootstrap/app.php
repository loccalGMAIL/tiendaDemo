<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de un proxy con SSL, sin esto los enlaces y assets salen con http://.
        $proxies = env('TRUSTED_PROXIES');
        // Corte por módulo: ->middleware('module:stock') responde 404 con el módulo apagado.
        $middleware->alias(['module' => EnsureModuleEnabled::class]);

        $middleware->trustProxies(at: $proxies === '*' ? '*' : array_filter(array_map('trim', explode(',', (string) $proxies))));

        // Cabeceras de seguridad en toda respuesta web (landing, paneles e impresiones).
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
