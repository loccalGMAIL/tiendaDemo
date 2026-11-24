<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Stock\AdjustStock;
use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Concerns\RelationManagerBelongsToModule;
use App\Filament\Pages\BarcodeLabels;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rules\PrintableSku;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Variantes del producto (módulo Variantes): talle, color u otras presentaciones,
 * cada una con su SKU, precio propio (o el del producto) y, con Stock, su existencia.
 */
class VariantsRelationManager extends RelationManager
{
    use RelationManagerBelongsToModule;

    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variantes';

    protected static ?string $modelLabel = 'variante';

    protected static ?string $pluralModelLabel = 'variantes';

    protected static function module(): Module
    {
        return Module::Variants;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->placeholder('M / Negro')
                ->required()
                ->maxLength(255),
            TextInput::make('sku')
                ->label('SKU')
                ->maxLength(255)
                ->unique(ProductVariant::class, 'sku', ignoreRecord: true)
                ->rules([PrintableSku::forVariant()])
                ->placeholder('Automático')
                ->helperText('Es el código de barras de la etiqueta. Vacío se asigna solo (PROD-00001-01…).'),
            TextInput::make('price')
                ->label('Precio propio')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix('$')
                ->placeholder('Hereda el del producto')
                ->helperText('Si lo dejás vacío vale el precio del producto.'),
            TextInput::make('min_stock')
                ->label('Stock mínimo')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0)
                ->visible(fn (): bool => Modules::enabled(Module::Stock)),
            Toggle::make('active')
                ->label('Activa')
                ->helperText('Una variante inactiva no se puede vender. Las que tienen ventas o movimientos no se borran: se desactivan.')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            // La variante implícita no se ve: es la que usa el producto cuando no tiene variantes.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('name', '!=', ''))
            ->columns([
                TextColumn::make('name')
                    ->label('Variante')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('—'),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('Hereda del producto'),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)
                    ->visible(fn (): bool => Modules::enabled(Module::Stock)),
                TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->visible(fn (): bool => Modules::enabled(Module::Stock)),
                IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->headerActions([
                $this->generateAction(),
                $this->printLabelsAction(),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                $this->adjustStockAction(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Abre las etiquetas con una de cada variante del producto (módulo Códigos de barras).
     */
    private function printLabelsAction(): Action
    {
        return Action::make('printLabels')
            ->label('Imprimir etiquetas')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (): bool => BarcodeLabels::canAccess())
            ->url(fn (): string => BarcodeLabels::urlFor(BarcodeLabels::selectionForProducts([$this->productOwner()])));
    }

    /**
     * Genera las combinaciones de los valores elegidos (S, M, L × Negro, Blanco = 6).
     */
    private function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generar variantes')
            ->icon(Heroicon::Sparkles)
            ->modalHeading('Generar variantes')
            ->modalDescription('Elegí los valores de cada atributo: se crean todas las combinaciones que todavía no existan.')
            ->modalSubmitActionLabel('Generar')
            ->schema(fn (): array => $this->attributeFields())
            ->action(function (array $data): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                $selection = [];

                foreach ($this->attributes() as $index => $attribute) {
                    $selection[$attribute['name']] = array_values($data["attribute_{$index}"] ?? []);
                }

                try {
                    $created = app(GenerateVariants::class)->handle($this->productOwner(), array_filter($selection), $actor);
                } catch (ValidationException $e) {
                    Notification::make()->title($e->validator->errors()->first())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($created === 0 ? 'Todas esas combinaciones ya existían' : "Se crearon {$created} variantes")
                    ->success()
                    ->send();
            });
    }

    /**
     * Ajuste manual de la existencia (módulo Stock, solo administrador).
     */
    private function adjustStockAction(): Action
    {
        return Action::make('adjustStock')
            ->label('Ajustar stock')
            ->icon(Heroicon::AdjustmentsHorizontal)
            ->visible(fn (): bool => Modules::enabled(Module::Stock) && auth()->user() instanceof User && auth()->user()->isAdmin())
            ->modalHeading('Ajustar stock')
            ->fillForm(fn (ProductVariant $record): array => ['quantity' => $record->stock, 'reason' => 'Recuento'])
            ->schema([
                TextInput::make('quantity')
                    ->label('Cantidad real')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->required(),
                TextInput::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, ProductVariant $record): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    $movement = app(AdjustStock::class)->handle($record, (int) $data['quantity'], $data['reason'], $actor);
                } catch (ValidationException $e) {
                    Notification::make()->title($e->validator->errors()->first())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($movement === null ? 'El stock ya era ese: no hubo cambios' : 'Stock ajustado')
                    ->success()
                    ->send();
            });
    }

    /**
     * Una lista de casillas por atributo configurado en el módulo.
     *
     * @return array<int, mixed>
     */
    private function attributeFields(): array
    {
        $fields = [];

        foreach ($this->attributes() as $index => $attribute) {
            $fields[] = CheckboxList::make("attribute_{$index}")
                ->label($attribute['name'])
                ->options(array_combine($attribute['values'], $attribute['values']))
                ->columns(4);
        }

        return $fields === []
            ? [Text::make('Todavía no hay atributos configurados. Se cargan en Catálogo → Atributos.')]
            : $fields;
    }

    /**
     * @return list<array{name: string, values: list<string>}>
     */
    private function attributes(): array
    {
        $attributes = [];

        foreach ((array) Modules::setting(Module::Variants, 'attributes', []) as $attribute) {
            $name = (string) ($attribute['name'] ?? '');
            $values = array_values(array_map('strval', (array) ($attribute['values'] ?? [])));

            if ($name !== '' && $values !== []) {
                $attributes[] = ['name' => $name, 'values' => $values];
            }
        }

        return $attributes;
    }

    private function productOwner(): Product
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Product) {
            throw new LogicException('Las variantes pertenecen a un producto.');
        }

        return $owner;
    }
}
