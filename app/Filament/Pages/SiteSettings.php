<?php

namespace App\Filament\Pages;

use App\Enums\ActivityEvent;
use App\Services\ActivityLogger;
use App\Services\SiteContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Configuración → Sitio: el contenido de la landing (solo texto). Siempre
 * disponible para el administrador; el diseño, los logos y los temas los
 * maneja el SuperAdmin.
 *
 * @property-read Schema $form
 */
class SiteSettings extends AdministratorPage
{
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
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Negocio')
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
            Section::make('Portada')
                ->schema([
                    TextInput::make('hero_title')
                        ->label('Título principal')
                        ->helperText('Si lo dejás vacío se muestra el nombre del negocio.')
                        ->maxLength(120),
                    Textarea::make('hero_subtitle')
                        ->label('Texto de apoyo')
                        ->rows(2)
                        ->maxLength(300),
                ]),
            Section::make('Por qué elegirnos')
                ->collapsible()
                ->schema([
                    TextInput::make('value_title')
                        ->label('Título de la sección')
                        ->maxLength(80),
                    Repeater::make('value_items')
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
            Section::make('Preguntas frecuentes')
                ->collapsible()
                ->schema([
                    Repeater::make('faq_items')
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
            Section::make('Contacto')
                ->collapsible()
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
            Section::make('Secciones visibles')
                ->description('Las secciones sin contenido no se muestran aunque estén activas.')
                ->columns(3)
                ->schema(array_map(
                    fn (string $section, string $label): Toggle => Toggle::make("sections.{$section}")->label($label),
                    array_keys(SiteContent::SECTIONS),
                    SiteContent::SECTIONS,
                )),
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

        $site->save($this->form->getState());

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
