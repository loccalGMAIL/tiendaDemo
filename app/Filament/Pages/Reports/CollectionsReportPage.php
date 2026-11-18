<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Module;
use App\Services\Money;
use App\Services\Reports\CollectionsReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Cobranza del mes: requiere los módulos Informes y Cuenta corriente.
 */
class CollectionsReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Cobranza del mes';

    protected static ?string $title = 'Cobranza del mes';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'informe-cobranza';

    /** Mes que se cobra, AAAA-MM. */
    public string $month = '';

    public bool $includeOverdue = false;

    /**
     * @return list<Module>
     */
    protected static function alsoRequires(): array
    {
        return [Module::CurrentAccount];
    }

    public function mount(): void
    {
        parent::mount();

        $this->month = now()->format('Y-m');
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function reportKey(): string
    {
        return 'cobranza';
    }

    public function filtersView(): string
    {
        return 'filament.pages.reports.collections-filters';
    }

    public function filters(): array
    {
        return ['month' => $this->month, 'include_overdue' => $this->includeOverdue ? 1 : 0];
    }

    public function result(): array
    {
        return app(CollectionsReport::class)->run($this->filters());
    }

    public function sectionDescription(): string
    {
        return app(CollectionsReport::class)->describe($this->filters()).' · Importes en pesos';
    }

    public function summary(array $result): array
    {
        return [
            ['label' => 'A cobrar', 'value' => Money::format($result['totals']['balance'])],
            ['label' => 'Vencimientos', 'value' => (string) count($result['rows'])],
            ['label' => 'Clientes', 'value' => (string) count(array_unique(array_column($result['rows'], 'label')))],
        ];
    }
}
