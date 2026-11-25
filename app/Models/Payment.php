<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'customer_id', 'sale_id', 'cash_session_id', 'date', 'payment_method_id', 'installments', 'adjustment_percent',
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
     * Cobros que el usuario puede ver: un vendedor solo los de sus ventas y los que cobró
     * él mismo (salvo SELLER_SEES_ALL_SALES); el administrador ve todos, incluso los sueltos.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSeller() && ! config('platform.sales.seller_sees_all')) {
            $query->where(fn (Builder $own) => $own
                ->whereHas('sale', fn (Builder $sales) => $sales->where('seller_id', $user->getKey()))
                ->orWhere('collected_by', $user->getKey()));
        }

        return $query;
    }

    /**
     * Cobros sueltos a cuenta de un cliente (sin venta propia): los que carga Cuenta corriente.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeOnAccount(Builder $query): Builder
    {
        return $query->whereNull('sale_id')->whereNotNull('customer_id');
    }

    public function isOnAccount(): bool
    {
        return $this->sale_id === null && $this->customer_id !== null;
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
     * Vencimientos a los que se imputó este cobro (con lo imputado en el pivot).
     *
     * @return BelongsToMany<Installment, $this>
     */
    public function imputedInstallments(): BelongsToMany
    {
        return $this->belongsToMany(Installment::class, 'installment_payment')
            ->withPivot('amount')
            ->withTimestamps();
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
     * Turno de caja en que se cobró (módulo Control de caja), si pasó por una caja.
     *
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
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
