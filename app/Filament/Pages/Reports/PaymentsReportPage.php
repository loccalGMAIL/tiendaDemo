<?php

namespace App\Filament\Pages\Reports;

use App\Models\PaymentMethod;
use App\Services\Money;
use App\Services\Reports\PaymentsReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class PaymentsReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Cobros realizados';

    protected static ?string $title = 'Cobros realizados';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'informe-cobros';

    public ?int $paymentMethodId = null;

    public ?int $collectedBy = null;

    public function reportKey(): string
    {
        return 'cobros';
    }

    public function filtersView(): string
    {
        return 'filament.pages.reports.payments-filters';
    }

    public function filters(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'payment_method_id' => $this->paymentMethodId, 'collected_by' => $this->collectedBy];
    }

    public function result(): array
    {
        return app(PaymentsReport::class)->run($this->filters());
    }

    public function summary(array $result): array
    {
        $totals = $result['totals'];

        return [
            ['label' => 'Cobrado', 'value' => Money::format($totals['amount'])],
            ['label' => 'Base', 'value' => Money::format($totals['base'])],
            ['label' => 'Ajustes', 'value' => Money::format($totals['adjustment'])],
            ['label' => 'Cobros', 'value' => (string) count($result['rows'])],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function methods(): Collection
    {
        return PaymentMethod::query()->orderBy('position')->orderBy('name')->pluck('name', 'id');
    }
}
