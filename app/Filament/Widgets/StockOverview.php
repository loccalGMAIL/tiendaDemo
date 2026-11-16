<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\ProductVariant;
use App\Services\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Valor del stock y productos con stock bajo (módulo Stock, solo administrador: muestra costos).
 */
class StockOverview extends AdministratorStatsWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Stock';

    protected static function module(): Module
    {
        return Module::Stock;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $lowStock = ProductVariant::query()->lowStock()->count();
        $negative = ProductVariant::query()->where('stock', '<', 0)->count();

        $stats = [
            Stat::make('Valor del stock', Money::format($this->stockValue()))
                ->description('Existencias × último costo')
                ->icon(Heroicon::OutlinedCube),
            Stat::make('Productos con stock bajo', (string) $lowStock)
                ->description($lowStock > 0 ? 'Llegaron a su stock mínimo' : 'Ninguno llegó a su mínimo')
                ->color($lowStock > 0 ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];

        if ($negative > 0) {
            $stats[] = Stat::make('Variantes con stock negativo', (string) $negative)
                ->description('Se vendió sin existencias: conviene hacer un recuento')
                ->color('danger')
                ->icon(Heroicon::OutlinedArrowTrendingDown);
        }

        return $stats;
    }

    /**
     * Σ existencia positiva × costo del producto.
     */
    private function stockValue(): string
    {
        return Money::from(
            DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('product_variants.stock', '>', 0)
                ->sum(DB::raw('product_variants.stock * products.cost')),
        );
    }
}
