<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;

/**
 * Base de las páginas que solo usa el administrador. Las páginas de un módulo la
 * extienden y agregan su corte de módulo con BelongsToModule.
 */
abstract class AdministratorPage extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
