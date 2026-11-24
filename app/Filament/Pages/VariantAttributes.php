<?php

namespace App\Filament\Pages;

use App\Enums\ActivityEvent;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Concerns\BelongsToModule;
use App\Filament\Superadmin\Schemas\ModuleSettingsForm;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Catálogo → Atributos (módulo Variantes): el administrador define los atributos de sus
 * variantes (talle, color…) y los valores posibles de cada uno. Cambiarlos no toca las
 * variantes ya creadas: solo afecta a las que se generen después.
 *
 * @property-read Schema $form
 */
class VariantAttributes extends AdministratorPage
{
    use BelongsToModule;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Atributos';

    protected static ?string $title = 'Atributos de las variantes';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'variant-attributes';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static function module(): Module
    {
        return Module::Variants;
    }

    public function mount(): void
    {
        $this->form->fill(['attributes' => $this->savedAttributes()]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Son las opciones que se ofrecen al generar las variantes de un producto (por ejemplo, Talle: S, M, L). Cambiar o quitar un valor no modifica las variantes que ya creaste: solo afecta a las que generes después.'),
            ModuleSettingsForm::variantAttributesRepeater(),
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
                            ->label('Guardar atributos')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $before = $this->savedAttributes();

        $attributes = $this->cleanAttributes((array) ($this->form->getState()['attributes'] ?? []));

        Modules::updateSettings(Module::Variants, ['attributes' => $attributes]);

        if ($attributes !== $before) {
            app(ActivityLogger::class)->log(
                ActivityEvent::Updated,
                'Cambio en los atributos de las variantes',
                properties: ['old' => $before, 'new' => $attributes],
            );
        }

        $this->form->fill(['attributes' => $attributes]);

        Notification::make()->title('Atributos guardados')->success()->send();
    }

    /**
     * @return list<array{name: string, values: list<string>}>
     */
    private function savedAttributes(): array
    {
        return $this->cleanAttributes((array) Modules::setting(Module::Variants, 'attributes', []));
    }

    /**
     * Deja solo nombre y valores, sin espacios sobrantes ni valores repetidos, y descarta lo vacío.
     *
     * @param  array<int|string, mixed>  $attributes
     * @return list<array{name: string, values: list<string>}>
     */
    private function cleanAttributes(array $attributes): array
    {
        $clean = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $values = array_values(array_unique(array_filter(
                array_map(fn (mixed $value): string => trim((string) $value), (array) ($attribute['values'] ?? [])),
                fn (string $value): bool => $value !== '',
            )));

            if ($name !== '' && $values !== []) {
                $clean[] = ['name' => $name, 'values' => $values];
            }
        }

        return $clean;
    }
}
