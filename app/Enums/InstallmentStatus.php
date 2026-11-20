<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Partial => 'Pago parcial',
            self::Paid => 'Pagado',
            self::Cancelled => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Vencimientos que todavía tienen deuda.
     *
     * @return list<string>
     */
    public static function open(): array
    {
        return [self::Pending->value, self::Partial->value];
    }
}
