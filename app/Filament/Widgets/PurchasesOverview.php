<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Services\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Compras del mes y órdenes que esperan su factura (módulo Compras, solo administrador).
 */
class PurchasesOverview extends AdministratorStatsWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Compras';

    protected static function module(): Module
    {
        return Module::Purchases;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $month = Purchase::query()
            ->notCancelled()
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

        $awaiting = PurchaseOrder::query()->awaitingInvoice()->count();

        return [
            Stat::make('Compras del mes', Money::format(Money::from((clone $month)->sum('total'))))
                ->description((clone $month)->count().' facturas, sin anuladas')
                ->icon(Heroicon::OutlinedShoppingBag),
            Stat::make('Órdenes sin recibir', (string) $awaiting)
                ->description($awaiting > 0 ? 'Emitidas, esperando su factura' : 'No hay órdenes pendientes')
                ->color($awaiting > 0 ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedClipboardDocumentList),
        ];
    }
}
