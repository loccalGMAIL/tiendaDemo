<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\PrintableSku;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductForm
{
    /**
     * Pestañas: General y, con el módulo Catálogo web encendido, Catálogo.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('Producto')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('General')
                        ->columns(2)
                        ->schema(self::fields()),
                    Tab::make('Catálogo')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->columns(2)
                        ->visible(fn (): bool => Modules::enabled(Module::Catalog))
                        ->schema(self::catalogFields()),
                ]),
        ]);
    }

    /**
     * Campos del módulo Catálogo web: qué se publica y cómo se muestra en la landing.
     *
     * @return array<int, Component>
     */
    public static function catalogFields(): array
    {
        return [
            Toggle::make('is_published')
                ->label('Publicado en el catálogo')
                ->helperText('Solo los productos activos y publicados se ven en la web.'),
            Toggle::make('is_featured')
                ->label('Destacado en la landing')
                ->helperText('Aparece en la sección Catálogo de la página principal.'),
            Toggle::make('show_price')
                ->label('Mostrar el precio')
                ->helperText('Si lo apagás, la web muestra «Consultar precio».')
                ->default(true),
            TextInput::make('slug')
                ->label('Enlace')
                ->prefix('/producto/')
                ->maxLength(255)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(Product::class, 'slug', ignoreRecord: true)
                ->helperText('Se arma solo a partir del nombre y no cambia si después lo renombrás. Solo minúsculas, números y guiones.'),
            FileUpload::make('images')
                ->label('Imágenes')
                ->multiple()
                ->image()
                ->disk('public')
                ->directory('products')
                ->visibility('public')
                ->maxFiles(6)
                ->maxSize(2048)
                ->reorderable()
                ->helperText('Hasta 6, de hasta 2 MB. La primera es la principal.')
                ->columnSpanFull(),
        ];
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
                ->rules([PrintableSku::forProduct()])
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
            // Módulo Compras: a quién se le compra habitualmente.
            Select::make('supplier_id')
                ->label('Proveedor habitual')
                ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->native(false)
                ->visible(fn (): bool => Modules::enabled(Module::Purchases)),
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
