<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una venta confirmada no se edita ni se borra: se anula con motivo. No usa
 * LogsActivity porque registra sus eventos (alta y anulación) de forma explícita.
 */
#[Fillable([
    'customer_id', 'seller_id', 'date', 'subtotal', 'adjustment', 'total', 'total_cost',
    'down_payment', 'financed_amount', 'financing_plan_id', 'installments_count', 'first_due_date',
    'status', 'notes',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'first_due_date' => 'date',
            'subtotal' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'total' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'financed_amount' => 'decimal:2',
            'status' => SaleStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Numeración visible: V-00001 (el id con ceros a la izquierda; no hay contador aparte).
     */
    public function number(): string
    {
        return 'V-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleStatus::Cancelled;
    }

    /**
     * Ventas que el usuario puede ver: un vendedor solo ve las suyas (salvo que la
     * instalación configure SELLER_SEES_ALL_SALES); el administrador ve todas.
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSeller() && ! config('platform.sales.seller_sees_all')) {
            $query->where('seller_id', $user->getKey());
        }

        return $query;
    }

    /**
     * Ventas vigentes: sin anuladas (las que cuentan para totales y dashboard).
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', SaleStatus::Cancelled->value);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Vencimientos del saldo (módulo Cuenta corriente).
     *
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
