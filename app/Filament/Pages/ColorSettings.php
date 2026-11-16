<?php

namespace App\Filament\Pages;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Services\Appearance;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Configuración → Colores (módulo Colores personalizables): el administrador elige
 * entre las combinaciones ya probadas del tema actual, con vista previa. No hay
 * selector de color libre ni cambio de tema, logos o recursos (eso es del SuperAdmin).
 */
class ColorSettings extends AdministratorPage
{
    use BelongsToModule;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Colores';

    protected static ?string $title = 'Colores';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'color-settings';

    protected string $view = 'filament.pages.color-settings';

    public string $palette = '';

    protected static function module(): Module
    {
        return Module::CustomColors;
    }

    public function mount(): void
    {
        $this->palette = app(Appearance::class)->paletteKey();
    }

    /**
     * Combinaciones del tema actual.
     *
     * @return array<string, array<string, string>>
     */
    public function getPalettes(): array
    {
        return app(Appearance::class)->palettes();
    }

    public function getThemeName(): string
    {
        return (string) app(Appearance::class)->theme()['name'];
    }

    public function save(): void
    {
        try {
            app(Appearance::class)->setPalette($this->palette);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Colores actualizados')->success()->send();
    }
}
