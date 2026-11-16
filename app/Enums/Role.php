<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasLabel
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
    case Seller = 'seller';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'SuperAdmin',
            self::Admin => 'Administrador',
            self::Seller => 'Vendedor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'primary',
            self::Seller => 'gray',
        };
    }

    /**
     * Roles que el cliente puede asignar a sus usuarios (SuperAdmin no existe para él).
     *
     * @return array<string, string>
     */
    public static function assignable(): array
    {
        return [
            self::Admin->value => self::Admin->getLabel(),
            self::Seller->value => self::Seller->getLabel(),
        ];
    }
}
