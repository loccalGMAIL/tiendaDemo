<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Module;
use App\Services\Money;
use App\Services\Reports\AccountBalancesReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Saldos pendientes por cliente: requiere los módulos Informes y Cuenta corriente.
 */
class AccountBalancesReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Saldos pendientes';

    protected static ?string $title = 'Saldos pendientes por cliente';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'informe-saldos';

    public int $months = 6;

    /**
     * @return list<Module>
     */
    protected static function alsoRequires(): array
    {
        return [Module::CurrentAccount];
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function reportKey(): string
    {
        return 'saldos';
    }

    public function filtersView(): string
    {
        return 'filament.pages.reports.balances-filters';
    }

    public function filters(): array
    {
        return ['months' => $this->months];
    }

    public function result(): array
    {
        return app(AccountBalancesReport::class)->run($this->filters());
    }

    public function sectionDescription(): string
    {
        return app(AccountBalancesReport::class)->describe($this->filters()).' · Importes en pesos';
    }

    public function summary(array $result): array
    {
        return [
            ['label' => 'Total por cobrar', 'value' => Money::format($result['totals']['total'])],
            ['label' => 'Vencido', 'value' => Money::format($result['totals']['overdue'])],
            ['label' => 'Clientes con deuda', 'value' => (string) count($result['rows'])],
        ];
    }
}
