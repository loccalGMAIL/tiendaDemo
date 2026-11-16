<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case Adjustment = 'adjustment';
    case Cancellation = 'cancellation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sale => 'Venta',
            self::Purchase => 'Compra',
            self::Adjustment => 'Ajuste',
            self::Cancellation => 'Anulación',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sale => 'danger',
            self::Purchase => 'success',
            self::Adjustment => 'info',
            self::Cancellation => 'warning',
        };
    }
}
