<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ventas del mes contra el anterior y cobrado del mes. Excluye lo anulado; un
 * vendedor ve solo lo suyo.
 */
class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Este mes';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $month = now();
        $previousMonth = now()->subMonthNoOverflow();

        $sales = $this->salesTotal($user, $month);
        $previousSales = $this->salesTotal($user, $previousMonth);

        $count = $this->salesQuery($user, $month)->count();

        return [
            $this->comparison(Stat::make('Ventas del mes', Money::format($sales)), $sales, $previousSales),
            Stat::make('Cobrado del mes', Money::format($this->collectedTotal($user, $month)))
                ->description('Cobros vigentes, sin anulados')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Cantidad de ventas', (string) $count)
                ->description('Sin contar las anuladas')
                ->icon(Heroicon::OutlinedShoppingCart),
        ];
    }

    private function comparison(Stat $stat, string $current, string $previous): Stat
    {
        if (Money::compare($previous, '0') === 0) {
            return $stat->description('Sin ventas el mes anterior');
        }

        // Variación porcentual redondeada a un decimal (mitad hacia afuera del cero).
        $change = bcdiv(bcmul(Money::sub($current, $previous), '100', 8), $previous, 8);
        $up = bccomp($change, '0', 8) >= 0;
        $rounded = bcadd(bcadd($change, $up ? '0.05' : '-0.05', 8), '0', 1);
        $formatted = number_format((float) ltrim($rounded, '-'), 1, ',', '.');

        return $stat
            ->description($formatted.' % '.($up ? 'más' : 'menos').' que el mes anterior ('.Money::format($previous).')')
            ->descriptionIcon($up ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
            ->color($up ? 'success' : 'danger');
    }

    private function salesTotal(User $user, Carbon $month): string
    {
        return Money::from($this->salesQuery($user, $month)->sum('total'));
    }

    private function collectedTotal(User $user, Carbon $month): string
    {
        return Money::from(
            Payment::query()
                ->scopes(['visibleTo' => [$user], 'notCancelled'])
                ->whereBetween('date', $this->range($month))
                ->sum('amount'),
        );
    }

    /**
     * @return Builder<Sale>
     */
    private function salesQuery(User $user, Carbon $month): Builder
    {
        return Sale::query()
            ->scopes(['visibleTo' => [$user], 'notCancelled'])
            ->whereBetween('date', $this->range($month));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Carbon $month): array
    {
        return [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];
    }
}
