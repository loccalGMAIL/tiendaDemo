<?php

namespace App\Filament\Superadmin\Pages;

use App\Models\User;
use App\Services\Appearance;
use App\Services\ImageTrimmer;
use App\Services\SiteContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Apariencia de la instalación: tema, combinación de colores y marca (logos,
 * favicon, imagen para compartir y recursos gráficos del tema). Solo el SuperAdmin:
 * el cliente nunca cambia de tema, logotipos ni recursos.
 *
 * @property-read Schema $form
 */
class ManageAppearance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $navigationLabel = 'Apariencia';

    protected static ?string $title = 'Apariencia';

    protected static ?string $slug = 'appearance';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->form->fill($this->currentState());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $themes = app(Appearance::class)->themes();

        return $schema->columns(1)->components([
            Section::make('Estructura de la landing')
                ->description('Elegí las secciones y arrastralas para ordenar el sitio. El cliente solo actualiza su contenido. Las secciones vacías y el catálogo sin módulo no se muestran.')
                ->schema([
                    Select::make('landing_sections')
                        ->label('Secciones y orden')
                        ->options(SiteContent::SECTIONS)
                        ->multiple()
                        ->reorderable(),
                ]),
            Section::make('Tema')
                ->description('Cambia todo el diseño de la landing y la tipografía y el logo del panel.')
                ->schema([
                    Radio::make('theme')
                        ->hiddenLabel()
                        ->options(array_map(fn (array $theme): string => $theme['name'], $themes))
                        ->descriptions(array_map(fn (array $theme): string => $theme['description'], $themes))
                        ->required()
                        ->live()
                        // Al cambiar de tema, la combinación vuelve a la primera del tema nuevo.
                        ->afterStateUpdated(fn (mixed $state, Set $set) => $set('palette', array_key_first($themes[$state]['palettes'] ?? []))),
                    Text::make(fn (Get $get): HtmlString => $this->themePreview((string) $get('theme'))),
                ]),
            Section::make('Combinación de colores')
                ->schema([
                    Select::make('palette')
                        ->hiddenLabel()
                        ->options(fn (Get $get): array => $this->paletteOptions((string) $get('theme')))
                        ->required()
                        ->native(false)
                        ->live(),
                    Text::make(fn (Get $get): HtmlString => $this->swatches((string) $get('theme'), (string) $get('palette'))),
                ]),
            Section::make('Modo de color de la landing')
                ->description('Cómo se ve el sitio público. No cambia el panel.')
                ->schema([
                    Radio::make('mode')
                        ->hiddenLabel()
                        ->options(Appearance::MODES)
                        ->descriptions([
                            'auto' => 'Sigue el modo claro u oscuro del dispositivo de cada visitante.',
                            'light' => 'Siempre claro, sin importar el dispositivo.',
                            'dark' => 'Siempre oscuro. Usa el logo para fondo oscuro (si no hay, el claro).',
                        ])
                        ->required(),
                ]),
            Section::make('Marca')
                ->description('Logos, favicon e imagen para compartir en redes. Sin logo se muestra el nombre del negocio como texto.')
                ->columns(2)
                ->schema([
                    $this->upload('logo_light', 'Logo para fondo claro'),
                    $this->upload('logo_dark', 'Logo para fondo oscuro'),
                    $this->upload('logo_login', 'Logo del acceso')
                        ->helperText('Opcional: si no hay, el acceso usa el logo para fondo claro.'),
                    $this->upload('favicon', 'Favicon', image: false)
                        ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/webp'])
                        ->maxSize(512),
                    $this->upload('share_image', 'Imagen para compartir en redes')
                        ->helperText('Se ve al compartir el enlace del sitio. Recomendado: 1200 × 630 px.'),
                ]),
            Section::make('Recursos gráficos del tema')
                ->description('Las imágenes propias del tema elegido (portada, propuesta, etc.).')
                ->columns(2)
                ->schema(fn (Get $get): array => $this->assetFields((string) $get('theme'))),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Guardar apariencia')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $appearance = app(Appearance::class);
        $state = $this->form->getState();

        app(SiteContent::class)->saveStructure($state['landing_sections'] ?? []);

        if ($state['theme'] !== $appearance->themeKey()) {
            $appearance->setTheme($state['theme']);
        }

        $appearance->setPalette($state['palette']);
        $appearance->setMode($state['mode']);

        foreach (Appearance::FILE_SLOTS as $slot) {
            $path = $this->path($state[$slot] ?? null);

            // Un logo con mucho margen se ve chico en la barra: se recorta el fondo sobrante.
            if ($path !== null && in_array($slot, Appearance::LOGO_SLOTS, true)) {
                app(ImageTrimmer::class)->trim($path);
            }

            $appearance->setFile($slot, $path);
        }

        // Solo los recursos del tema elegido: son los campos que se muestran.
        $theme = $appearance->themeKey();

        foreach (array_keys($appearance->theme()['assets']) as $key) {
            $appearance->setFile($appearance->assetSlot($key, $theme), $this->path($state['assets'][$theme][$key] ?? null));
        }

        Notification::make()->title('Apariencia actualizada')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function currentState(): array
    {
        $appearance = app(Appearance::class);
        $files = $appearance->files();

        $state = [
            'theme' => $appearance->themeKey(),
            'palette' => $appearance->paletteKey(),
            'mode' => $appearance->modeKey(),
            'assets' => [],
            'landing_sections' => app(SiteContent::class)->sectionOrder(),
        ];

        foreach (Appearance::FILE_SLOTS as $slot) {
            $state[$slot] = $files[$slot] ?? null;
        }

        foreach ($appearance->themes() as $themeKey => $theme) {
            foreach (array_keys($theme['assets']) as $key) {
                $state['assets'][$themeKey][$key] = $files[$appearance->assetSlot($key, $themeKey)] ?? null;
            }
        }

        return $state;
    }

    /**
     * Solo imágenes de píxeles: un SVG puede traer scripts y se serviría directo desde el disco
     * público. Los logos y las imágenes del tema se recortan y se validan como imagen real.
     */
    private const RASTER_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    private function upload(string $name, string $label, bool $image = true): FileUpload
    {
        $upload = FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory('branding')
            ->visibility('public')
            ->maxSize(2048);

        return $image ? $upload->acceptedFileTypes(self::RASTER_TYPES) : $upload;
    }

    /**
     * @return array<int, FileUpload>
     */
    private function assetFields(string $theme): array
    {
        $assets = app(Appearance::class)->themes()[$theme]['assets'] ?? [];

        return array_map(
            fn (string $key, array $asset): FileUpload => $this->upload("assets.{$theme}.{$key}", $asset['label'])->helperText($asset['help']),
            array_keys($assets),
            $assets,
        );
    }

    /**
     * @return array<string, string>
     */
    private function paletteOptions(string $theme): array
    {
        $palettes = app(Appearance::class)->themes()[$theme]['palettes'] ?? [];

        return array_map(fn (array $palette): string => $palette['name'], $palettes);
    }

    private function themePreview(string $theme): HtmlString
    {
        $preview = app(Appearance::class)->themes()[$theme]['preview'] ?? null;

        return $preview === null
            ? new HtmlString('')
            : new HtmlString('<img src="'.e(asset($preview)).'" alt="Vista previa del tema" style="display:block;width:20rem;max-width:100%;height:auto;border-radius:.5rem;border:1px solid rgba(0,0,0,.12)">');
    }

    private function swatches(string $theme, string $palette): HtmlString
    {
        $colors = app(Appearance::class)->themes()[$theme]['palettes'][$palette] ?? null;

        if ($colors === null) {
            return new HtmlString('');
        }

        $dots = collect(['primary', 'accent', 'surface', 'text'])
            ->map(fn (string $token): string => '<span style="display:inline-block;width:1.75rem;height:1.75rem;border-radius:9999px;border:1px solid rgba(0,0,0,.15);background:'.e($colors[$token]).'"></span>')
            ->implode(' ');

        return new HtmlString('<span style="display:flex;gap:.4rem">'.$dots.'</span>');
    }

    /**
     * La ruta guardada por FileUpload (string; si fuera lista, la primera).
     */
    private function path(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
