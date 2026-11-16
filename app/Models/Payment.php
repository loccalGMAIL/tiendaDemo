<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id', 'sale_id', 'date', 'payment_method_id', 'installments', 'adjustment_percent',
    'base_amount', 'adjustment_amount', 'amount', 'collected_by', 'notes',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'installments' => 'integer',
            'adjustment_percent' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Numeración visible: C-00001.
     */
    public function number(): string
    {
        return 'C-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Cobros que el usuario puede ver: un vendedor solo los de sus ventas (salvo
     * SELLER_SEES_ALL_SALES); el administrador ve todos, incluso los sueltos.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSeller() && ! config('platform.sales.seller_sees_all')) {
            $query->whereHas('sale', fn (Builder $sales) => $sales->where('seller_id', $user->getKey()));
        }

        return $query;
    }

    /**
     * Cobros vigentes (sin anular).
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
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
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
