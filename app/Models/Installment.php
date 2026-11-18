<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Services\Money;
use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Vencimiento de una venta a cuenta (módulo Cuenta corriente). Lo que debe es
 * `amount - paid_amount`; los cobros lo reducen por imputación.
 */
#[Fillable(['sale_id', 'customer_id', 'number', 'due_date', 'amount', 'paid_amount', 'cost_portion', 'status'])]
class Installment extends Model
{
    /** @use HasFactory<InstallmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'cost_portion' => 'decimal:2',
            'status' => InstallmentStatus::class,
        ];
    }

    /**
     * Lo que todavía se debe de este vencimiento.
     */
    public function balance(): string
    {
        return $this->status === InstallmentStatus::Cancelled
            ? '0.00'
            : Money::sub((string) $this->amount, (string) $this->paid_amount);
    }

    /**
     * Vencido: con deuda y la fecha ya pasó (el día del vencimiento todavía no lo está).
     */
    public function isOverdue(): bool
    {
        return in_array($this->status->value, InstallmentStatus::open(), true)
            && $this->due_date->lt(now()->startOfDay());
    }

    /**
     * Días de atraso (0 si no está vencido).
     */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()->startOfDay()) : 0;
    }

    /**
     * Con deuda: pendientes o con pago parcial.
     *
     * @param  Builder<Installment>  $query
     * @return Builder<Installment>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', InstallmentStatus::open());
    }

    /**
     * Con deuda y vencidos (la fecha ya pasó).
     *
     * @param  Builder<Installment>  $query
     * @return Builder<Installment>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * Del más viejo al más nuevo: el orden en que se imputan los cobros.
     *
     * @param  Builder<Installment>  $query
     * @return Builder<Installment>
     */
    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('due_date')->orderBy('id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Cobros imputados a este vencimiento (con lo imputado en el pivot).
     *
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'installment_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }
}
