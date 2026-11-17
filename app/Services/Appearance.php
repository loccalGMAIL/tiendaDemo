<?php

namespace App\Services;

use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Apariencia de la instalación: tema, combinación de colores y archivos de marca
 * (logos, favicon, imagen para compartir y recursos gráficos del tema). El tema
 * y los archivos los elige el SuperAdmin; la combinación, también el
 * administrador del cliente si tiene el módulo Colores personalizables.
 */
class Appearance
{
    /** Archivos de marca que carga el SuperAdmin. */
    public const FILE_SLOTS = ['logo_light', 'logo_dark', 'logo_login', 'favicon', 'share_image'];

    /** Los que son logos: se les recorta el margen sobrante al guardar. */
    public const LOGO_SLOTS = ['logo_light', 'logo_dark', 'logo_login'];

    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function themes(): array
    {
        return config('themes.themes');
    }

    public function themeKey(): string
    {
        $stored = $this->settings->get('appearance.theme');

        return is_string($stored) && array_key_exists($stored, $this->themes())
            ? $stored
            : (string) config('themes.default');
    }

    /**
     * @return array<string, mixed>
     */
    public function theme(): array
    {
        return $this->themes()[$this->themeKey()];
    }

    /**
     * Combinaciones de colores del tema actual.
     *
     * @return array<string, array<string, string>>
     */
    public function palettes(): array
    {
        return $this->theme()['palettes'];
    }

    public function paletteKey(): string
    {
        $stored = $this->settings->get('appearance.palette');

        return is_string($stored) && array_key_exists($stored, $this->palettes())
            ? $stored
            : (string) array_key_first($this->palettes());
    }

    /**
     * @return array<string, string>
     */
    public function palette(): array
    {
        return $this->palettes()[$this->paletteKey()];
    }

    /**
     * Cambia de tema; la combinación vuelve a la primera del tema nuevo.
     */
    public function setTheme(string $key): void
    {
        if (! array_key_exists($key, $this->themes())) {
            throw new InvalidArgumentException("El tema «{$key}» no existe.");
        }

        $this->settings->set('appearance.theme', $key);
        $this->settings->set('appearance.palette', array_key_first($this->themes()[$key]['palettes']));
    }

    /**
     * Elige una combinación del tema actual (no se aceptan las de otros temas).
     */
    public function setPalette(string $key): void
    {
        if (! array_key_exists($key, $this->palettes())) {
            throw new InvalidArgumentException("La combinación «{$key}» no pertenece al tema actual.");
        }

        $this->settings->set('appearance.palette', $key);
    }

    /**
     * @return array<string, string>
     */
    public function files(): array
    {
        $files = $this->settings->get('appearance.files', []);

        return is_array($files) ? array_filter($files, 'is_string') : [];
    }

    /**
     * Guarda (o quita, con null) la ruta de un archivo de marca y borra el anterior.
     */
    public function setFile(string $slot, ?string $path): void
    {
        if (! in_array($slot, self::FILE_SLOTS, true) && ! str_starts_with($slot, 'asset:')) {
            throw new InvalidArgumentException("El archivo «{$slot}» no es válido.");
        }

        $files = $this->files();
        $previous = $files[$slot] ?? null;

        if ($path === null || $path === '') {
            unset($files[$slot]);
        } else {
            $files[$slot] = $path;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $this->settings->set('appearance.files', $files);
    }

    /**
     * Clave de un recurso gráfico de un tema (por ejemplo la imagen del hero).
     */
    public function assetSlot(string $key, ?string $theme = null): string
    {
        return 'asset:'.($theme ?? $this->themeKey()).':'.$key;
    }

    public function assetUrl(string $key): ?string
    {
        return $this->url($this->assetSlot($key));
    }

    /**
     * Logo del panel y la landing: `light` (fondo claro), `dark` (fondo oscuro) o
     * `login` (pantalla de acceso, con el claro como respaldo). Sin archivo, null:
     * se muestra el nombre del negocio como texto.
     */
    public function logoUrl(string $kind = 'light'): ?string
    {
        return match ($kind) {
            'dark' => $this->url('logo_dark'),
            'login' => $this->url('logo_login') ?? $this->url('logo_light'),
            default => $this->url('logo_light'),
        };
    }

    public function faviconUrl(): ?string
    {
        return $this->url('favicon');
    }

    public function shareImageUrl(): ?string
    {
        return $this->url('share_image');
    }

    /**
     * Variables CSS de la combinación y la tipografía del tema (la landing las usa
     * con `bg-brand`, `text-ink`, etc.; se aplican sin recompilar).
     */
    public function cssVariables(): string
    {
        $palette = $this->palette();
        $font = $this->theme()['font'];

        return ':root{'
            ."--brand:{$palette['primary']};--on-brand:{$palette['on_primary']};--accent:{$palette['accent']};"
            ."--surface:{$palette['surface']};--text:{$palette['text']};"
            ."--surface-dark:{$palette['surface_dark']};--text-dark:{$palette['text_dark']};"
            ."--font-body:{$font['body']};--font-heading:{$font['heading']};"
            .'}'
            .'@media (prefers-color-scheme: dark){:root{'
            ."--surface:{$palette['surface_dark']};--text:{$palette['text_dark']};"
            .'}}';
    }

    /**
     * Colores de Filament derivados de la combinación (escala generada desde el color de marca).
     *
     * @return array<string, array<int|string, string>>
     */
    public function filamentColors(): array
    {
        return ['primary' => Color::hex($this->palette()['primary'])];
    }

    private function url(string $slot): ?string
    {
        $path = $this->files()[$slot] ?? null;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        // Relativa al host actual: la landing, el panel y el superadmin comparten el mismo almacenamiento.
        return asset('storage/'.ltrim($path, '/'));
    }
}
