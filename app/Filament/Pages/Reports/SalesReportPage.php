<?php

namespace App\Filament\Pages\Reports;

use App\Services\Money;
use App\Services\Reports\SalesReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class SalesReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Ventas y ganancia';

    protected static ?string $title = 'Ventas y ganancia';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'informe-ventas';

    public ?int $sellerId = null;

    public string $group = 'detail';

    public function reportKey(): string
    {
        return 'ventas';
    }

    public function filtersView(): string
    {
        return 'filament.pages.reports.sales-filters';
    }

    public function filters(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'seller_id' => $this->sellerId, 'group' => $this->group];
    }

    public function result(): array
    {
        return app(SalesReport::class)->run($this->filters());
    }

    public function summary(array $result): array
    {
        $totals = $result['totals'];

        return [
            ['label' => 'Ventas', 'value' => Money::format($totals['total'] ?? $totals['subtotal'])],
            ['label' => 'Costo', 'value' => Money::format($totals['cost'])],
            ['label' => 'Ganancia', 'value' => Money::format($totals['profit'])],
            ['label' => 'Margen', 'value' => number_format((float) $totals['margin'], 2, ',', '.').' %'],
        ];
    }
}
