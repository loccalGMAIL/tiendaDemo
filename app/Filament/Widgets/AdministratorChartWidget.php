<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

/**
 * Base de los gráficos del escritorio que solo ve el administrador (por ejemplo, los que
 * muestran ganancias).
 */
abstract class AdministratorChartWidget extends ChartWidget
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
