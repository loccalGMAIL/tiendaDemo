<?php

namespace App\Actions\CashRegister;

use App\Enums\ActivityEvent;
use App\Models\CashSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CashSessionSummary;
use App\Services\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierra un turno con el arqueo (módulo Control de caja): lo contado, a mano o billete por
 * billete, contra lo esperado según el libro del turno. Queda la diferencia (positiva si
 * sobra, negativa si falta) y el fondo que se deja en el cajón para el próximo turno. Un turno
 * cerrado no se reabre: si hay que corregir, se registra un movimiento en otro turno.
 *
 * No pregunta por el módulo: un turno abierto se tiene que poder cerrar aunque lo apaguen.
 */
class CloseCashSession
{
    /** Billetes en circulación, de mayor a menor, para el conteo. */
    public const BILLS = ['20000', '10000', '2000', '1000', '500', '200', '100', '50', '20', '10'];

    public function __construct(
        private ActivityLogger $logger,
        private CashSessionSummary $summary,
    ) {}

    /**
     * @param  array{
     *     counted_amount?: string|int|float|null,
     *     counted_bills?: array<int|string, mixed>|null,
     *     left_amount?: string|int|float|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CashSession $session, array $data): CashSession
    {
        if (! $actor->isAdmin() && ! $actor->isSeller()) {
            throw new AuthorizationException('No podés cerrar la caja.');
        }

        [$counted, $bills] = $this->resolveCount($data);
        $left = $this->resolveLeft($data['left_amount'] ?? null, $counted);

        return DB::transaction(function () use ($actor, $session, $counted, $bills, $left, $data): CashSession {
            $locked = CashSession::query()->with('register')->lockForUpdate()->findOrFail($session->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['cash' => "El turno {$locked->number()} ya está cerrado."]);
            }

            $expected = $this->summary->for($locked)['expected'];
            $difference = Money::sub($counted, $expected);

            $locked->forceFill([
                'closed_by' => $actor->getKey(),
                'closed_at' => now(),
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => $difference,
                'left_amount' => $left,
                'counted_bills' => $bills,
                'closing_notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ])->save();

            $this->logger->log(
                ActivityEvent::Updated,
                "Cierre de {$locked->register->name} ({$locked->number()}): esperado {$expected}, contado {$counted}, diferencia {$difference}",
                $locked,
                ['expected' => $expected, 'counted' => $counted, 'difference' => $difference, 'left' => $left],
                $actor,
            );

            return $locked;
        });
    }

    /**
     * Lo contado: la suma de los billetes y las monedas si se contó por billete; si no, el
     * monto indicado.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: array<string, int|string>|null}
     *
     * @throws ValidationException
     */
    private function resolveCount(array $data): array
    {
        $rawBills = $data['counted_bills'] ?? null;

        if (is_array($rawBills) && $rawBills !== []) {
            $bills = [];
            $total = '0.00';

            foreach (self::BILLS as $bill) {
                $quantity = $rawBills[$bill] ?? null;

                if (blank($quantity)) {
                    continue;
                }

                $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

                if ($quantity === false || $quantity < 0) {
                    throw ValidationException::withMessages(["counted_bills.{$bill}" => "La cantidad de billetes de {$bill} no es válida."]);
                }

                if ($quantity > 0) {
                    $bills[$bill] = $quantity;
                    $total = Money::add($total, Money::mul($bill, $quantity));
                }
            }

            $coins = $rawBills['coins'] ?? null;

            if (filled($coins)) {
                if (! is_numeric($coins) || Money::isNegative(Money::from($coins))) {
                    throw ValidationException::withMessages(['counted_bills.coins' => 'El monto en monedas no es válido.']);
                }

                $bills['coins'] = Money::from($coins);
                $total = Money::add($total, $bills['coins']);
            }

            return [$total, $bills];
        }

        $counted = $data['counted_amount'] ?? null;

        if (! is_numeric($counted) || Money::isNegative(Money::from($counted))) {
            throw ValidationException::withMessages(['counted_amount' => 'Indicá cuánto efectivo contaste.']);
        }

        return [Money::from($counted), null];
    }

    /**
     * @throws ValidationException
     */
    private function resolveLeft(mixed $value, string $counted): string
    {
        if (blank($value)) {
            return '0.00';
        }

        if (! is_numeric($value) || Money::isNegative(Money::from($value))) {
            throw ValidationException::withMessages(['left_amount' => 'El fondo que queda no puede ser negativo.']);
        }

        if (Money::compare(Money::from($value), $counted) > 0) {
            throw ValidationException::withMessages(['left_amount' => 'El fondo que queda no puede ser mayor que lo contado.']);
        }

        return Money::from($value);
    }
}
