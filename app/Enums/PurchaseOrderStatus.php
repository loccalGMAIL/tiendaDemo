<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    /** Se puede editar. */
    case Draft = 'draft';

    /** Emitida al proveedor: esperando la factura. */
    case Issued = 'issued';

    /** Con su factura de compra cargada. */
    case Received = 'received';

    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Emitida',
            self::Received => 'Recibida',
            self::Cancelled => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }
}
