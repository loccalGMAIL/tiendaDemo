<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;

/**
 * Base de los indicadores que solo ve el administrador (por ejemplo, los que
 * muestran costos).
 */
abstract class AdministratorStatsWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
