<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountMovementType: string implements HasColor, HasLabel
{
    case Sale = 'sale';
    case Payment = 'payment';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sale => 'Venta',
            self::Payment => 'Cobro',
            self::Adjustment => 'Ajuste',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sale => 'warning',
            self::Payment => 'success',
            self::Adjustment => 'gray',
        };
    }
}
