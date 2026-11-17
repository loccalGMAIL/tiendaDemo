<?php

namespace App\Providers\Filament\Concerns;

use App\Services\Appearance;
use App\Services\SiteContent;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

/**
 * Marca del panel desde la base: nombre del negocio, logos (claro, oscuro y de
 * acceso), favicon y colores del tema. Todo se resuelve con closures en cada
 * request, así un cambio de apariencia se ve sin recompilar. Sin logo, Filament
 * muestra el nombre del negocio como texto.
 */
trait ConfiguresBranding
{
    protected function branded(Panel $panel): Panel
    {
        return $panel
            ->brandName(fn (): string => app(SiteContent::class)->name())
            ->brandLogo(fn (): ?string => app(Appearance::class)->logoUrl($this->onLoginScreen() ? 'login' : 'light'))
            ->darkModeBrandLogo(fn (): ?string => app(Appearance::class)->logoUrl('dark'))
            // Filament muestra el logo a 1.5rem: ilegible. En el acceso hay lugar para uno bien grande.
            ->brandLogoHeight(fn (): string => $this->onLoginScreen() ? '7rem' : '2.5rem')
            ->favicon(fn (): ?string => app(Appearance::class)->faviconUrl())
            ->colors(fn (): array => app(Appearance::class)->filamentColors())
            // La tipografía de los títulos viene del tema; la estructura del panel es siempre la misma.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('<style>:root{--theme-heading-font: {!! $font !!};}</style>', [
                    'font' => app(Appearance::class)->theme()['font']['heading'],
                ]),
            );
    }

    /**
     * La pantalla de acceso usa su propio logo. Las acciones de Livewire de esa
     * pantalla (escribir, enviar) se reconocen por el origen de la petición.
     */
    private function onLoginScreen(): bool
    {
        $request = request();

        if ($request->routeIs('filament.*.auth.login')) {
            return true;
        }

        return $request->hasHeader('X-Livewire')
            && parse_url((string) $request->headers->get('referer'), PHP_URL_PATH) === '/login';
    }
}
