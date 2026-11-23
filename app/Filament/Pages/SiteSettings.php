<?php

namespace App\Filament\Pages;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Concerns\BuildsLandingData;
use App\Services\ActivityLogger;
use App\Services\SiteContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Configuración → Sitio: textos y fotografías de las secciones habilitadas.
 * El diseño, los logos, los temas y la estructura los maneja el SuperAdmin.
 *
 * @property-read Schema $form
 */
class SiteSettings extends AdministratorPage
{
    use BuildsLandingData;

    protected string $view = 'filament.pages.site-settings';

    public string $activeSection = 'business';

    #[Locked]
    public string $savedStateHash = '';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Sitio';

    protected static ?string $title = 'Sitio';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'site-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SiteContent::class)->all());
        $this->savedStateHash = $this->stateHash();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data')->live(debounce: 500);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('Secciones del sitio')
                ->livewireProperty('activeSection')
                ->tabs($this->sectionTabs()),
        ]);
    }

    /**
     * @return array<string, Tab>
     */
    private function sectionTabs(): array
    {
        $tabs = [
            'business' => Tab::make('Negocio')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del negocio')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('tagline')
                        ->label('Lema')
                        ->maxLength(160),
                ]),
            'hero' => Tab::make('Portada')
                ->schema([
                    TextInput::make('hero_title')
                        ->label('Título principal')
                        ->helperText('Si lo dejás vacío se muestra el nombre del negocio.')
                        ->maxLength(120),
                    Textarea::make('hero_subtitle')
                        ->label('Texto de apoyo')
                        ->rows(2)
                        ->maxLength(300),
                    $this->imageUpload('hero_image', 'Imagen de portada')
                        ->helperText('Horizontal, recomendado 1600 × 1000 px. Si no cargás una, se usa la imagen del diseño.'),
                    TextInput::make('hero_image_alt')
                        ->label('Descripción de la imagen')
                        ->maxLength(160),
                ]),
            'about' => Tab::make('Nuestro negocio')
                ->schema([
                    TextInput::make('about_title')->label('Título')->maxLength(80),
                    Textarea::make('about_text')->label('Presentación')->rows(5)->maxLength(2000),
                    $this->imageUpload('about_image', 'Fotografía del negocio'),
                ]),
            'services' => Tab::make('Servicios')
                ->schema([
                    TextInput::make('services_title')->label('Título')->maxLength(80),
                    Repeater::make('service_items')
                        ->collapsed()
                        ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? $state['title'] : 'Nuevo servicio')
                        ->label('Servicios o propuestas')
                        ->addActionLabel('Agregar servicio')
                        ->maxItems(12)
                        ->defaultItems(0)
                        ->schema([
                            TextInput::make('title')->label('Nombre')->required()->maxLength(80),
                            Textarea::make('text')->label('Descripción')->maxLength(500),
                        ]),
                ]),
            'gallery' => Tab::make('Galería')
                ->schema([
                    TextInput::make('gallery_title')->label('Título')->maxLength(80),
                    Repeater::make('gallery_items')
                        ->collapsed()
                        ->itemLabel(fn (array $state): string => filled($state['alt'] ?? null) ? $state['alt'] : 'Nueva fotografía')
                        ->label('Fotografías')
                        ->maxItems(12)
                        ->defaultItems(0)
                        ->addActionLabel('Agregar fotografía')
                        ->schema([
                            $this->imageUpload('image', 'Imagen')->required(),
                            TextInput::make('alt')->label('Descripción de la imagen')->required()->maxLength(160),
                        ]),
                ]),
            'banner' => Tab::make('Novedades')
                ->schema([
                    TextInput::make('banner_title')->label('Título')->maxLength(120),
                    Textarea::make('banner_text')->label('Texto')->maxLength(500),
                ]),
            'value' => Tab::make('Por qué elegirnos')
                ->schema([
                    TextInput::make('value_title')
                        ->label('Título de la sección')
                        ->maxLength(80),
                    Repeater::make('value_items')
                        ->collapsed()
                        ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? $state['title'] : 'Nuevo punto')
                        ->label('Puntos a destacar')
                        ->addActionLabel('Agregar punto')
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->schema([
                            TextInput::make('title')
                                ->label('Título')
                                ->required()
                                ->maxLength(80),
                            Textarea::make('text')
                                ->label('Texto')
                                ->rows(2)
                                ->maxLength(300),
                        ]),
                ]),
            'faq' => Tab::make('Preguntas frecuentes')
                ->schema([
                    Repeater::make('faq_items')
                        ->collapsed()
                        ->itemLabel(fn (array $state): string => filled($state['question'] ?? null) ? $state['question'] : 'Nueva pregunta')
                        ->hiddenLabel()
                        ->addActionLabel('Agregar pregunta')
                        ->maxItems(12)
                        ->defaultItems(0)
                        ->schema([
                            TextInput::make('question')
                                ->label('Pregunta')
                                ->required()
                                ->maxLength(160),
                            Textarea::make('answer')
                                ->label('Respuesta')
                                ->required()
                                ->rows(2)
                                ->maxLength(600),
                        ]),
                ]),
            'contact' => Tab::make('Contacto')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(40),
                    TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->helperText('Con código de país, por ejemplo +54 9 11 1234-5678.')
                        ->regex('/^[0-9+()\s-]*$/')
                        ->maxLength(40),
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->maxLength(160),
                    TextInput::make('address')
                        ->label('Dirección')
                        ->maxLength(200),
                    Textarea::make('hours')
                        ->label('Horarios de atención')
                        ->rows(3)
                        ->maxLength(500),
                    TextInput::make('instagram')
                        ->label('Instagram')
                        ->url()
                        ->placeholder('https://instagram.com/…')
                        ->maxLength(200),
                    TextInput::make('facebook')
                        ->label('Facebook')
                        ->url()
                        ->placeholder('https://facebook.com/…')
                        ->maxLength(200),
                    TextInput::make('tiktok')
                        ->label('TikTok')
                        ->url()
                        ->placeholder('https://tiktok.com/@…')
                        ->maxLength(200),
                ]),
        ];

        $ordered = ['business' => $tabs['business']];

        foreach (app(SiteContent::class)->sectionOrder() as $section) {
            if (isset($tabs[$section])) {
                $ordered[$section] = $tabs[$section];
            }
        }

        return $ordered;
    }

    #[Computed]
    public function hasUnsavedChanges(): bool
    {
        return $this->stateHash() !== $this->savedStateHash;
    }

    private function stateHash(): string
    {
        $state = $this->data ?? [];
        array_walk_recursive($state, function (mixed &$value): void {
            if ($value instanceof TemporaryUploadedFile) {
                $value = $value->getFilename();
            }
        });

        return hash('sha256', serialize($state));
    }

    #[Computed]
    public function previewHtml(): string
    {
        $siteContent = app(SiteContent::class);
        $content = array_replace($siteContent->all(), $this->data ?? []);
        $imageUrls = [];

        foreach (['hero_image', 'about_image'] as $field) {
            $content[$field] = $this->previewImage($content[$field], $imageUrls);
        }

        foreach ([
            'service_items' => ['title' => '', 'text' => ''],
            'gallery_items' => ['image' => null, 'alt' => ''],
            'value_items' => ['title' => '', 'text' => ''],
            'faq_items' => ['question' => '', 'answer' => ''],
        ] as $field => $defaults) {
            $content[$field] = array_values(array_map(
                fn (array $item): array => array_replace($defaults, array_filter($item, fn (mixed $value): bool => $value !== null)),
                $content[$field] ?? [],
            ));
        }

        foreach ($content['gallery_items'] as &$item) {
            $item['image'] = $this->previewImage($item['image'] ?? null, $imageUrls);
        }
        unset($item);

        foreach ($siteContent->defaults() as $field => $default) {
            if (is_string($default)) {
                $content[$field] ??= '';
            }
        }

        $preview = $siteContent->withPreviewContent($content, $imageUrls);
        $section = $this->activeSection !== 'catalog' && $siteContent->sectionEnabled($this->activeSection)
            ? $this->activeSection
            : 'business';

        return view('filament.pages.site-section-preview', [
            ...$this->landingData(request(), $preview),
            'previewSection' => $section,
        ])->render();
    }

    /**
     * @param  array<string, string>  $imageUrls
     */
    private function previewImage(mixed $image, array &$imageUrls): ?string
    {
        if (is_array($image)) {
            $image = reset($image);
        }

        if ($image instanceof TemporaryUploadedFile) {
            if (! in_array($image->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }

            $url = $image->temporaryUrl();

            if (! is_string($url)) {
                return null;
            }

            $key = 'site/preview/'.count($imageUrls);
            $imageUrls[$key] = $url;

            return $key;
        }

        return is_string($image) ? $image : null;
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
                            ->label('Guardar cambios')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $site = app(SiteContent::class);
        $before = $site->all();

        $content = $this->form->getState();
        unset($content['sections']);
        $site->save(array_merge($before, $content));
        $this->savedStateHash = $this->stateHash();
        unset($this->hasUnsavedChanges, $this->previewHtml);

        $changes = $this->changes($before, $site->all());

        if ($changes['new'] !== []) {
            app(ActivityLogger::class)->log(
                ActivityEvent::Updated,
                'Cambio en el contenido del sitio',
                properties: $changes,
            );
        }

        Notification::make()->title('Sitio actualizado')->success()->send();
    }

    private function imageUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory('site')
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096);
    }

    /**
     * Solo lo que cambió, con antes y después.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function changes(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            if (json_encode($before[$key] ?? null) !== json_encode($value)) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }

        return ['old' => $old, 'new' => $new];
    }
}
