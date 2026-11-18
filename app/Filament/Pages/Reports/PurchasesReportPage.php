<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Module;
use App\Models\Supplier;
use App\Services\Money;
use App\Services\Reports\PurchasesReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Compras por proveedor: requiere los módulos Informes y Compras.
 */
class PurchasesReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Compras por proveedor';

    protected static ?string $title = 'Compras por proveedor';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'informe-compras';

    public ?int $supplierId = null;

    public string $group = 'supplier';

    /**
     * @return list<Module>
     */
    protected static function alsoRequires(): array
    {
        return [Module::Purchases];
    }

    public function reportKey(): string
    {
        return 'compras';
    }

    public function filtersView(): string
    {
        return 'filament.pages.reports.purchases-filters';
    }

    public function filters(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'supplier_id' => $this->supplierId, 'group' => $this->group];
    }

    public function result(): array
    {
        return app(PurchasesReport::class)->run($this->filters());
    }

    public function summary(array $result): array
    {
        $totals = $result['totals'];
        $invoices = $result['group'] === 'detail' ? count($result['rows']) : (int) ($totals['count'] ?? 0);

        return [
            ['label' => 'Total comprado', 'value' => Money::format($totals['total'])],
            ['label' => 'Facturas', 'value' => (string) $invoices],
            ['label' => 'Proveedores', 'value' => (string) $this->suppliersCount($result)],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function suppliers(): Collection
    {
        return Supplier::query()->orderBy('name')->pluck('name', 'id');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function suppliersCount(array $result): int
    {
        if ($result['group'] === 'detail') {
            return count(array_unique(array_column($result['rows'], 'supplier')));
        }

        return count($result['rows']);
    }
}
