<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\Concerns\HasAccountHeader;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Ficha del cliente: sus datos y, con Cuenta corriente, saldo, vencimientos, cuenta corriente y
 * productos comprados. La ven el administrador y el vendedor (el vendedor no edita).
 */
class ViewCustomer extends ViewRecord
{
    use HasAccountHeader;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->collectAction(),
            EditAction::make(),
        ];
    }
}
