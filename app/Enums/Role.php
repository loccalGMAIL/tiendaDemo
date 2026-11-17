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
     * Roles que se pueden asignar a un usuario. El cliente no conoce al SuperAdmin:
     * solo el panel de la plataforma lo ofrece.
     *
     * @return array<string, string>
     */
    public static function assignable(bool $withSuperAdmin = false): array
    {
        $roles = [
            self::Admin->value => self::Admin->getLabel(),
            self::Seller->value => self::Seller->getLabel(),
        ];

        return $withSuperAdmin
            ? [self::SuperAdmin->value => self::SuperAdmin->getLabel(), ...$roles]
            : $roles;
    }
}
