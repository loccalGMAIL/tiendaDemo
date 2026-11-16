<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Facades\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('module:stock'). Con el módulo apagado (o una clave que
 * no existe) la ruta responde 404, como si la función no existiera.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        $module = Module::tryFrom($key);

        abort_unless($module !== null && Modules::enabled($module), 404);

        return $next($request);
    }
}
