<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Resources\Sales\Schemas\SaleForm;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Datos de la orden')
                ->columns(2)
                ->schema([
                    Select::make('supplier_id')
                        ->label('Proveedor')
                        ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->native(false),
                    DatePicker::make('date')
                        ->label('Fecha')
                        ->default(fn (): string => now()->toDateString())
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Section::make('Productos')
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Agregar producto')
                        ->columns(12)
                        ->schema([
                            Select::make('variant_id')
                                ->label('Producto')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => SaleForm::searchVariants($search))
                                ->options(fn (): array => SaleForm::searchVariants(''))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => ProductVariant::query()->with('product')->find($value)?->fullName())
                                ->columnSpan(9),
                            TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->columnSpan(3),
                        ]),
                ]),
        ]);
    }
}
