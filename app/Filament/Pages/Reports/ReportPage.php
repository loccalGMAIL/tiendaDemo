<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Filament\Pages\AdministratorPage;
use App\Models\User;
use App\Services\Reports\Report;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Base de los informes del panel (módulo Informes, solo administrador): filtros en
 * vivo, tabla con los totales y enlace a la versión imprimible con los mismos filtros.
 */
abstract class ReportPage extends AdministratorPage
{
    use BelongsToModule;

    protected static string|\UnitEnum|null $navigationGroup = 'Informes';

    protected string $view = 'filament.pages.report';

    public string $from = '';

    public string $to = '';

    protected static function module(): Module
    {
        return Module::Reports;
    }

    /**
     * Clave del informe en la ruta de impresión (`reports.print`).
     */
    abstract public function reportKey(): string;

    /**
     * Resultado del informe con los filtros actuales.
     *
     * @return array<string, mixed>
     */
    abstract public function result(): array;

    /**
     * Nombre del partial con los filtros propios del informe.
     */
    abstract public function filtersView(): string;

    /**
     * Filtros actuales, tal cual los entiende el servicio y la ruta de impresión.
     *
     * @return array<string, mixed>
     */
    abstract public function filters(): array;

    /**
     * Indicadores de arriba (etiqueta y valor ya formateado), calculados del resultado.
     *
     * @param  array<string, mixed>  $result
     * @return list<array{label: string, value: string}>
     */
    abstract public function summary(array $result): array;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Imprimir')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => $this->printUrl())
                ->openUrlInNewTab(),
        ];
    }

    public function periodLabel(): string
    {
        [$from, $to] = Report::period(['from' => $this->from, 'to' => $this->to]);

        return $from->format('d/m/Y').' al '.$to->format('d/m/Y');
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function printUrl(): string
    {
        return route('reports.print', ['report' => $this->reportKey(), ...array_filter($this->filters(), fn (mixed $value): bool => $value !== '' && $value !== null)]);
    }

    /**
     * @return Collection<int, string>
     */
    public function sellers(): Collection
    {
        return User::query()->visibleToClient()->orderBy('name')->pluck('name', 'id');
    }
}
