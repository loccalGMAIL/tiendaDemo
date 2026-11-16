<?php

namespace App\Filament\Superadmin\Pages;

use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Superadmin\Schemas\ModuleSettingsForm;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Habilita módulos y configura sus ajustes. Solo el SuperAdmin.
 *
 * @property-read Schema $form
 */
class ManageModules extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Módulos';

    protected static ?string $title = 'Módulos';

    protected static ?string $slug = 'modules';

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
        $this->form->fill(['modules' => $this->currentState()]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(
            array_map(fn (Module $module): Section => $this->moduleSection($module), Module::cases()),
        );
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
                            ->label('Guardar ajustes')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (Module::cases() as $module) {
            $settings = data_get($state, "modules.{$module->name}.settings", []);

            if (is_array($settings) && $settings !== []) {
                Modules::updateSettings($module, $settings);
            }
        }

        Notification::make()->title('Ajustes guardados')->success()->send();
    }

    /**
     * Enciende o apaga un módulo desde su interruptor. Si la regla no se cumple,
     * avisa y deja el interruptor como estaba.
     */
    public function toggle(Module $module, bool $enabled): void
    {
        try {
            $enabled ? Modules::enable($module) : Modules::disable($module);

            Notification::make()
                ->title($enabled ? "{$module->getLabel()} encendido" : "{$module->getLabel()} apagado")
                ->success()
                ->send();
        } catch (ValidationException $e) {
            Notification::make()->title($e->validator->errors()->first())->danger()->send();
        }

        $this->data['modules'][$module->name]['enabled'] = Modules::enabled($module);
    }

    /**
     * Por qué el interruptor no se puede mover ahora; null si se puede.
     */
    public function blockedReason(Module $module): ?string
    {
        if (Modules::enabled($module)) {
            $inUse = array_filter($module->dependents(), fn (Module $dependent): bool => Modules::enabled($dependent));

            return $inUse === []
                ? null
                : 'Lo usa '.$this->labels($inUse).'. Apagalo primero para poder apagar este.';
        }

        $missingConfiguration = $module->missingConfiguration();

        if ($missingConfiguration !== null) {
            return $missingConfiguration;
        }

        $notEnabled = array_filter($module->requires(), fn (Module $required): bool => ! Modules::enabled($required));

        return $notEnabled === []
            ? null
            : 'Requiere '.$this->labels($notEnabled).' encendido.';
    }

    private function moduleSection(Module $module): Section
    {
        $settingsFields = ModuleSettingsForm::fields($module);

        return Section::make($module->getLabel())
            ->description($module->getDescription())
            ->collapsible()
            ->schema([
                Group::make([
                    Toggle::make('enabled')
                        ->label('Encendido')
                        ->live()
                        ->disabled(fn (): bool => $this->blockedReason($module) !== null)
                        ->helperText(fn (): ?string => $this->blockedReason($module))
                        ->afterStateUpdated(fn (bool $state) => $this->toggle($module, $state)),
                    ...($settingsFields === [] ? [] : [
                        Group::make($settingsFields)->statePath('settings'),
                    ]),
                ])->statePath("modules.{$module->name}"),
            ]);
    }

    /**
     * @return array<string, array{enabled: bool, settings: array<string, mixed>}>
     */
    private function currentState(): array
    {
        $state = [];

        foreach (Module::cases() as $module) {
            $state[$module->name] = [
                'enabled' => Modules::enabled($module),
                'settings' => Modules::settings($module),
            ];
        }

        return $state;
    }

    /**
     * @param  array<int, Module>  $modules
     */
    private function labels(array $modules): string
    {
        return implode(', ', array_map(fn (Module $module): string => $module->getLabel(), $modules));
    }
}
