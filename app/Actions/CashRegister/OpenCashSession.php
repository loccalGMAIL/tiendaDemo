<?php

namespace App\Actions\CashRegister;

use App\Enums\ActivityEvent;
use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Facades\Modules;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abre un turno en una caja con el fondo que hay en el cajón (módulo Control de caja). Una
 * caja tiene a lo sumo un turno abierto; en él pueden vender varios usuarios.
 */
class OpenCashSession
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CashRegister $register, mixed $openingAmount): CashSession
    {
        if (! $actor->isAdmin() && ! $actor->isSeller()) {
            throw new AuthorizationException('No podés abrir la caja.');
        }

        if (! Modules::enabled(Module::CashControl)) {
            throw ValidationException::withMessages(['cash' => 'El módulo Control de caja está apagado.']);
        }

        $amount = blank($openingAmount) ? '0.00' : $openingAmount;

        if (! is_numeric($amount) || Money::isNegative(Money::from($amount))) {
            throw ValidationException::withMessages(['opening_amount' => 'El fondo inicial no puede ser negativo.']);
        }

        $amount = Money::from($amount);

        return DB::transaction(function () use ($actor, $register, $amount): CashSession {
            $locked = CashRegister::query()->lockForUpdate()->findOrFail($register->getKey());

            if (! $locked->active) {
                throw ValidationException::withMessages(['cash_register_id' => "La caja «{$locked->name}» está desactivada."]);
            }

            if (CashSession::query()->where('cash_register_id', $locked->getKey())->open()->exists()) {
                throw ValidationException::withMessages(['cash_register_id' => "La caja «{$locked->name}» ya está abierta."]);
            }

            $session = CashSession::query()->create([
                'cash_register_id' => $locked->getKey(),
                'opened_by' => $actor->getKey(),
                'opened_at' => now(),
                'opening_amount' => $amount,
            ]);

            $session->movements()->create([
                'type' => CashMovementType::Opening,
                'amount' => $amount,
                'description' => 'Fondo inicial',
                'user_id' => $actor->getKey(),
            ]);

            $this->logger->log(
                ActivityEvent::Created,
                "Apertura de {$locked->name} ({$session->number()}) con fondo {$amount}",
                $session,
                ['register' => $locked->name, 'opening_amount' => $amount],
                $actor,
            );

            return $session;
        });
    }

    /**
     * El fondo que quedó al cerrar el último turno de la caja: lo que se sugiere al abrir.
     */
    public function suggestedOpening(CashRegister $register): string
    {
        $last = CashSession::query()
            ->where('cash_register_id', $register->getKey())
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->latest('id')
            ->first();

        return Money::from($last === null ? null : (string) $last->left_amount);
    }
}
