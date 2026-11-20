<?php

namespace App\Filament\Resources\Customers\Pages\Concerns;

use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\CustomerAccountStats;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Cabecera de la ficha del cliente con el módulo Cuenta corriente: indicadores de saldo y
 * acción «Cobrar» (lleva al formulario de cobro con el cliente elegido).
 */
trait HasAccountHeader
{
    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [CustomerAccountStats::class];
    }

    protected function collectAction(): Action
    {
        return Action::make('collect')
            ->label('Cobrar')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (Customer $record): bool => Modules::enabled(Module::CurrentAccount)
                && auth()->user() instanceof User
                && auth()->user()->can('create', Payment::class))
            ->url(fn (Customer $record): string => PaymentResource::getUrl('create', ['customer' => $record->getKey()]));
    }
}
