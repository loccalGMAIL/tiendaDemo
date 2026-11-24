<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Por dónde entró la venta. El mostrador es la Caja; online es lo que se cierra a distancia
 * (WhatsApp, redes y, a futuro, el carrito del catálogo) y se carga desde «Nueva venta».
 */
enum SaleChannel: string implements HasColor, HasLabel
{
    case Counter = 'counter';
    case Online = 'online';

    public function getLabel(): string
    {
        return match ($this) {
            self::Counter => 'Mostrador',
            self::Online => 'Online',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Counter => 'gray',
            self::Online => 'info',
        };
    }
}
