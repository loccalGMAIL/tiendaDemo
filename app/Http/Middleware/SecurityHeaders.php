<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de todas las respuestas web (landing, paneles, impresiones).
 *
 * No incluye Content-Security-Policy: Filament, Livewire y Alpine necesitan scripts en línea y
 * evaluación dinámica, y una política que no los permita rompería los paneles. HSTS solo se manda
 * cuando la petición ya llegó por HTTPS (también detrás del proxy de confianza).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // El navegador no adivina el tipo de un archivo.
            'X-Content-Type-Options' => 'nosniff',
            // Nada de esta aplicación se muestra dentro de un marco de otro sitio.
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // La aplicación no usa cámara, micrófono, ubicación ni pagos del navegador.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000';
        }

        foreach ($headers as $name => $value) {
            // Si una respuesta ya trae la suya (por ejemplo un archivo con otra política), se respeta.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
