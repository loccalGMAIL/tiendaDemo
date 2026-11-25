<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Movimientos del efectivo de un turno de caja (módulo Control de caja).
 */
enum CashMovementType: string implements HasColor, HasLabel
{
    /** El fondo con que abre el turno. */
    case Opening = 'opening';
    /** Lo cobrado en efectivo en una venta. */
    case Sale = 'sale';
    /** Efectivo que se agrega (cambio, por ejemplo). */
    case Deposit = 'deposit';
    /** Efectivo que se saca (pago a un proveedor, retiro del dueño…). */
    case Withdrawal = 'withdrawal';
    /** Lo que se devuelve al anular una venta cobrada en efectivo. */
    case Refund = 'refund';

    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Apertura',
            self::Sale => 'Venta',
            self::Deposit => 'Ingreso',
            self::Withdrawal => 'Retiro',
            self::Refund => 'Devolución',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Opening => 'gray',
            self::Sale, self::Deposit => 'success',
            self::Withdrawal, self::Refund => 'danger',
        };
    }
}
