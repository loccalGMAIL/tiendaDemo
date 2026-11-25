<?php

namespace App\Actions\CashRegister;

use App\Enums\ActivityEvent;
use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Facades\Modules;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CashSessionSummary;
use App\Services\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso o retiro de efectivo en un turno abierto (módulo Control de caja), siempre con
 * motivo. Ingresa cualquiera del equipo; retira el administrador y, si el módulo lo permite,
 * también el vendedor. No se puede retirar más de lo que hay en el cajón.
 */
class RecordCashMovement
{
    public function __construct(
        private ActivityLogger $logger,
        private CashSessionSummary $summary,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CashSession $session, CashMovementType $type, mixed $amount, string $reason): CashMovement
    {
        if (! in_array($type, [CashMovementType::Deposit, CashMovementType::Withdrawal], true)) {
            throw ValidationException::withMessages(['type' => 'Solo se registran ingresos y retiros a mano.']);
        }

        if (! Modules::enabled(Module::CashControl)) {
            throw ValidationException::withMessages(['cash' => 'El módulo Control de caja está apagado.']);
        }

        if (! self::canRecord($actor, $type)) {
            throw new AuthorizationException('No podés retirar efectivo de la caja.');
        }

        if (! is_numeric($amount) || Money::compare(Money::from($amount), '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'El monto tiene que ser mayor que cero.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Indicá el motivo.']);
        }

        $amount = Money::from($amount);

        return DB::transaction(function () use ($actor, $session, $type, $amount, $reason): CashMovement {
            $locked = CashSession::query()->with('register')->lockForUpdate()->findOrFail($session->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['cash' => "El turno {$locked->number()} ya está cerrado."]);
            }

            if ($type === CashMovementType::Withdrawal) {
                $available = $this->summary->for($locked)['expected'];

                if (Money::compare($amount, $available) > 0) {
                    throw ValidationException::withMessages(['amount' => 'En la caja hay '.Money::format($available).': no se puede retirar más.']);
                }
            }

            $movement = $locked->movements()->create([
                'type' => $type,
                'amount' => $type === CashMovementType::Withdrawal ? Money::sub('0', $amount) : $amount,
                'description' => $reason,
                'user_id' => $actor->getKey(),
            ]);

            $this->logger->log(
                ActivityEvent::Created,
                "{$type->getLabel()} de {$amount} en {$locked->register->name} ({$locked->number()}): {$reason}",
                $locked,
                ['type' => $type->value, 'amount' => $amount, 'reason' => $reason],
                $actor,
            );

            return $movement;
        });
    }

    /**
     * Ingresar puede cualquiera del equipo; retirar, el administrador o el vendedor si el
     * módulo lo permite.
     */
    public static function canRecord(User $actor, CashMovementType $type): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if (! $actor->isSeller()) {
            return false;
        }

        return $type === CashMovementType::Deposit
            || (bool) Modules::setting(Module::CashControl, 'seller_can_withdraw', false);
    }
}
