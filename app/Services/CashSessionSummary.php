<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Sale;

/**
 * Números de un turno de caja: el efectivo por tipo de movimiento, lo esperado en el cajón y
 * lo cobrado con cada medio de pago (también los que no son efectivo, para controlarlos
 * contra los cupones). Lo usan el cierre, la ficha del turno y su impresión.
 */
class CashSessionSummary
{
    /**
     * @return array{
     *     opening: string,
     *     sales: string,
     *     deposits: string,
     *     withdrawals: string,
     *     refunds: string,
     *     expected: string,
     *     sales_count: int,
     *     by_method: list<array{name: string, is_cash: bool, count: int, amount: string}>
     * }
     */
    public function for(CashSession $session): array
    {
        $byType = CashMovement::query()
            ->where('cash_session_id', $session->getKey())
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn (mixed $total): string => Money::from(is_numeric($total) ? $total : null))
            ->all();

        $amount = fn (CashMovementType $type): string => $byType[$type->value] ?? '0.00';

        $payments = Payment::query()
            ->with('method')
            ->where('cash_session_id', $session->getKey())
            ->whereNull('cancelled_at')
            ->get()
            ->groupBy('payment_method_id');

        $byMethod = $payments
            ->map(fn ($group): array => [
                'name' => (string) $group->first()?->method->name,
                'is_cash' => (bool) $group->first()?->method->is_cash,
                'count' => $group->count(),
                'amount' => Money::sum($group->map(fn (Payment $payment): string => (string) $payment->amount)->values()->all()),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'opening' => $amount(CashMovementType::Opening),
            'sales' => $amount(CashMovementType::Sale),
            'deposits' => $amount(CashMovementType::Deposit),
            'withdrawals' => $amount(CashMovementType::Withdrawal),
            'refunds' => $amount(CashMovementType::Refund),
            'expected' => Money::sum(array_values($byType)),
            'sales_count' => Sale::query()->where('cash_session_id', $session->getKey())->notCancelled()->count(),
            'by_method' => $byMethod,
        ];
    }
}
