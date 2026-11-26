<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\BarcodeLabels;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\PrintableSku;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    Tab::make('Variantes y stock')
                        ->icon(Heroicon::OutlinedSquares2x2)
                        ->columns(2)
                        ->visibleOn('create')
                        ->visible(fn (): bool => Modules::enabled(Module::Variants) || Modules::enabled(Module::Stock) || self::offersLabels())
                        ->schema(self::loadFields()),
                    Tab::make('Catálogo')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->columns(2)
                        ->visible(fn (): bool => Modules::enabled(Module::Catalog))
                        ->schema(self::catalogFields()),
                ]),
        ]);
    }

    /**
     * Carga del alta: variantes (con sus atributos), stock inicial y etiquetas, todo en un mismo paso.
     *
     * @return array<int, Component>
     */
    public static function loadFields(): array
    {
        $attributes = self::variantAttributes();
        $variantsOn = fn (): bool => Modules::enabled(Module::Variants) && $attributes !== [];
        $stockOn = fn (): bool => Modules::enabled(Module::Stock);

        $fields = [];

        if (Modules::enabled(Module::Variants)) {
            $fields[] = Text::make('Todavía no hay atributos configurados. Se cargan en Catálogo → Atributos.')
                ->visible(fn (): bool => $attributes === [])
                ->columnSpanFull();

            foreach ($attributes as $index => $attribute) {
                $fields[] = CheckboxList::make("attribute_{$index}")
                    ->label($attribute['name'])
                    ->options(array_combine($attribute['values'], $attribute['values']))
                    ->columns(4)
                    ->columnSpanFull()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncVariantRows($get, $set))
                    ->helperText($index === 0 ? 'Si no elegís ningún valor, el producto se crea sin variantes.' : null);
            }
        }

        $columns = [
            TableColumn::make('Variante'),
            TableColumn::make('Precio propio')->width('9rem'),
        ];

        $cells = [
            Hidden::make('attributes'),
            TextInput::make('label')->hiddenLabel()->readOnly()->dehydrated(false),
            TextInput::make('price')->hiddenLabel()->numeric()->minValue(0)->step('0.01')->prefix('$')->placeholder('Del producto'),
        ];

        if (Modules::enabled(Module::Stock)) {
            $columns[] = TableColumn::make('Stock inicial')->width('8rem');
            $columns[] = TableColumn::make('Mínimo')->width('7rem');
            $cells[] = TextInput::make('initial_stock')->hiddenLabel()->numeric()->integer()->minValue(0)->default(null);
            $cells[] = TextInput::make('min_stock')->hiddenLabel()->numeric()->integer()->minValue(0)->default(null);
        }

        $fields[] = Repeater::make('variants')
            ->label('Variantes')
            ->table($columns)
            ->schema($cells)
            ->addable(false)
            ->reorderable(false)
            ->visible(fn (Get $get): bool => $variantsOn() && filled($get('variants')))
            ->columnSpanFull();

        $fields[] = TextInput::make('initial_stock')
            ->label('Stock inicial')
            ->numeric()
            ->integer()
            ->minValue(0)
            ->visible(fn (Get $get): bool => $stockOn() && blank($get('variants')))
            ->helperText('Queda registrado como «Carga inicial».');

        $fields[] = TextInput::make('min_stock')
            ->label('Stock mínimo')
            ->numeric()
            ->integer()
            ->minValue(0)
            ->visible(fn (Get $get): bool => $stockOn() && blank($get('variants')))
            ->helperText('Por debajo de este número el producto aparece como stock bajo.');

        $fields[] = Toggle::make('print_labels')
            ->label('Imprimir etiquetas al guardar')
            ->helperText('Al guardar se abre Etiquetas con este producto cargado.')
            ->default(true)
            ->dehydrated(false)
            ->visible(fn (): bool => self::offersLabels())
            ->columnSpanFull();

        return $fields;
    }

    /**
     * Si el alta ofrece la casilla de etiquetas (módulo Códigos de barras, ajuste «ofrecer»).
     */
    public static function offersLabels(): bool
    {
        return Modules::enabled(Module::Barcodes)
            && BarcodeLabels::canAccess()
            && Modules::setting(Module::Barcodes, 'labels_on_create', 'ask') === 'ask';
    }

    /**
     * Arma de nuevo la grilla con las combinaciones elegidas, conservando lo ya escrito.
     */
    public static function syncVariantRows(Get $get, Set $set): void
    {
        $selection = [];

        foreach (self::variantAttributes() as $index => $attribute) {
            $selection[$attribute['name']] = array_values((array) ($get("attribute_{$index}") ?? []));
        }

        $generator = app(GenerateVariants::class);
        $previous = [];

        foreach ((array) $get('variants') as $row) {
            $previous[$generator->signature((array) ($row['attributes'] ?? []))] = $row;
        }

        $rows = [];

        foreach ($generator->combinationsFor(array_filter($selection)) as $combination) {
            $old = $previous[$generator->signature($combination)] ?? [];

            $rows[] = [
                'attributes' => $combination,
                'label' => implode(' / ', $combination),
                'price' => $old['price'] ?? null,
                'initial_stock' => $old['initial_stock'] ?? null,
                'min_stock' => $old['min_stock'] ?? null,
            ];
        }

        $set('variants', $rows);
    }

    /**
     * Atributos configurados en el módulo Variantes que tienen valores.
     *
     * @return list<array{name: string, values: list<string>}>
     */
    public static function variantAttributes(): array
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
