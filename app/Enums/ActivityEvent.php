<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActivityEvent: string implements HasColor, HasLabel
{
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Login => 'Ingreso',
            self::Logout => 'Salida',
            self::LoginFailed => 'Ingreso fallido',
            self::Created => 'Alta',
            self::Updated => 'Cambio',
            self::Deleted => 'Baja',
            self::Cancelled => 'Anulación',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Login, self::Created => 'success',
            self::Logout => 'gray',
            self::LoginFailed, self::Deleted => 'danger',
            self::Updated => 'info',
            self::Cancelled => 'warning',
        };
    }
}
