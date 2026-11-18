<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Últimas facturas de compra vigentes (módulo Compras, solo administrador).
 */
class LatestPurchases extends AdministratorTableWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Últimas compras';

    protected static function module(): Module
    {
        return Module::Purchases;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Purchase::query()->notCancelled()->with('supplier')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('invoice_number')->label('Factura'),
                TextColumn::make('date')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('supplier.name')->label('Proveedor'),
                TextColumn::make('total')->label('Total')->money('ARS', locale: 'es_AR'),
            ])
            ->recordUrl(fn (Purchase $record): string => PurchaseResource::getUrl('view', ['record' => $record]));
    }
}
