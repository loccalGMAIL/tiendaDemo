<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\Schemas\SaleForm;
use App\Filament\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $modelLabel = 'venta';

    protected static ?string $pluralModelLabel = 'ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 1;

    /**
     * El vendedor ve solo sus ventas (salvo SELLER_SEES_ALL_SALES). Lo que queda
     * fuera de esta consulta responde 404, no 403.
     *
     * @return Builder<Sale>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->scopes(['visibleTo' => [auth()->user()]])
            ->with(['customer', 'seller']);
    }

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}
