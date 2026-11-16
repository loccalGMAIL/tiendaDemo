<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    /**
     * Los campos se reutilizan en el alta rápida de producto desde la venta.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('sku')
                ->label('SKU')
                ->maxLength(255)
                ->unique(Product::class, 'sku', ignoreRecord: true)
                ->placeholder('Automático')
                ->helperText('Si lo dejás vacío se asigna PROD-00001, PROD-00002…'),
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('brand')
                ->label('Marca')
                ->maxLength(255),
            Select::make('category_id')
                ->label('Categoría')
                ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->native(false),
            TextInput::make('price')
                ->label('Precio de venta')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix('$')
                ->required()
                ->default('0.00'),
            TextInput::make('cost')
                ->label('Costo')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix('$')
                ->required()
                ->default('0.00')
                ->helperText('Último costo. Se copia a cada venta; los cambios no afectan las ventas ya hechas.'),
            Textarea::make('description')
                ->label('Descripción')
                ->columnSpanFull(),
            Toggle::make('active')
                ->label('Activo')
                ->helperText('Un producto inactivo no se puede vender.')
                ->default(true),
        ];
    }
}
