<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\TableWidget;

/**
 * Base de los listados del escritorio que solo ve el administrador.
 */
abstract class AdministratorTableWidget extends TableWidget
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
