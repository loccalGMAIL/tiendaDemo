<?php

namespace App\Actions\Accounts;

use App\Enums\AccountMovementType;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\User;
use App\Services\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra un movimiento en la cuenta corriente de un cliente y calcula su saldo
 * acumulado (positivo = debe). Es la única vía que escribe el libro. Hay que llamarla
 * dentro de una transacción: bloquea al cliente para que dos movimientos simultáneos
 * no calculen el saldo sobre el mismo anterior.
 */
class RecordAccountMovement
{
    public function handle(
        Customer $customer,
        AccountMovementType $type,
        string $debit,
        string $credit,
        string $description,
        ?Model $reference = null,
        ?User $user = null,
        ?string $date = null,
    ): AccountMovement {
        Customer::query()->lockForUpdate()->findOrFail($customer->getKey());

        $previous = (string) (AccountMovement::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('id')
            ->value('balance') ?? '0.00');

        $movement = new AccountMovement([
            'customer_id' => $customer->getKey(),
            'date' => $date ?? now()->toDateString(),
            'type' => $type,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => Money::sub(Money::add($previous, $debit), $credit),
            'user_id' => $user?->getKey(),
        ]);

        if ($reference !== null) {
            $movement->reference()->associate($reference);
        }

        $movement->save();

        return $movement;
    }
}
