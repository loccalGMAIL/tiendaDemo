<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Filament\Resources\Sales\Schemas\SaleForm;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Factura de compra, directa o desde una orden. Con orden, el proveedor y los productos
 * vienen fijos (de la orden) y se corrige lo que llegó (de 0 a lo pedido) y el costo.
 */
class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Datos de la factura')
                ->columns(2)
                ->schema([
                    Hidden::make('purchase_order_id'),
                    Select::make('supplier_id')
                        ->label('Proveedor')
                        ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => Supplier::query()->find($value)?->name)
                        ->searchable()
                        ->required()
                        ->native(false)
                        // Con orden, el proveedor es el de la orden.
                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')))
                        ->dehydrated(),
                    TextInput::make('invoice_number')
                        ->label('Número de factura')
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('date')
                        ->label('Fecha')
                        ->default(fn (): string => now()->toDateString())
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ]),
            Section::make('Productos')
                ->description(fn (Get $get): ?string => filled($get('purchase_order_id'))
                    ? 'Corregí la cantidad de lo que llegó (0 si no llegó) y el costo según la factura.'
                    : null)
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Agregar producto')
                        ->addable(fn (Get $get): bool => blank($get('purchase_order_id')))
                        ->deletable(fn (Get $get): bool => blank($get('purchase_order_id')))
                        ->reorderable(false)
                        ->live()
                        ->columns(12)
                        ->schema([
                            Select::make('variant_id')
                                ->label('Producto')
                                ->required()
                                ->searchable()
                                ->live()
                                ->getSearchResultsUsing(fn (string $search): array => SaleForm::searchVariants($search))
                                ->options(fn (): array => SaleForm::searchVariants(''))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => ProductVariant::query()->with('product')->find($value)?->fullName())
                                ->disabled(fn (Get $get): bool => filled($get('../../purchase_order_id')))
                                ->dehydrated()
                                // El costo se propone con el último que tiene el producto.
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $set('unit_cost', ProductVariant::query()->with('product')->find($state)?->product->cost);
                                })
                                ->columnSpan(5),
                            TextInput::make('ordered_quantity')
                                ->label('Pedido')
                                ->disabled()
                                ->dehydrated(false)
                                ->visible(fn (Get $get): bool => filled($get('../../purchase_order_id')))
                                ->columnSpan(1),
                            TextInput::make('quantity')
                                ->label('Llegó')
                                ->numeric()
                                ->integer()
                                ->minValue(fn (Get $get): int => filled($get('../../purchase_order_id')) ? 0 : 1)
                                ->maxValue(fn (Get $get): ?int => filled($get('../../purchase_order_id')) && is_numeric($get('ordered_quantity')) ? (int) $get('ordered_quantity') : null)
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2),
                            TextInput::make('unit_cost')
                                ->label('Costo unitario')
                                ->numeric()
                                ->minValue(0)
                                ->step('0.01')
                                ->prefix('$')
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2),
                            Text::make(fn (Get $get): string => 'Subtotal: '.Money::format(self::lineSubtotal($get('quantity'), $get('unit_cost'))))
                                ->columnSpan(2),
                        ]),
                ]),
            Section::make('Total')
                ->schema([
                    Text::make(fn (Get $get): string => 'Total de la factura: '.Money::format(self::total($get('items') ?? [])))
                        ->size('lg'),
                ]),
        ]);
    }

    private static function lineSubtotal(mixed $quantity, mixed $cost): string
    {
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if ($quantity === false || $quantity < 0 || ! is_numeric($cost)) {
            return '0.00';
        }

        return Money::mul(Money::from($cost), $quantity);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     */
    private static function total(array $items): string
    {
        return Money::sum(array_map(
            fn (array $item): string => self::lineSubtotal($item['quantity'] ?? null, $item['unit_cost'] ?? null),
            array_values($items),
        ));
    }
}
