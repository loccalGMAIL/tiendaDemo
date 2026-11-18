<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Orden de compra (opcional): se edita en borrador, se emite y se anula con motivo.
 * Al cargar su factura pasa a recibida. No usa LogsActivity: sus cambios de estado
 * se registran de forma explícita desde las acciones.
 */
#[Fillable(['supplier_id', 'date', 'status', 'notes', 'user_id', 'issued_at'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => PurchaseOrderStatus::class,
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Numeración visible: OC-00001 (el id con ceros a la izquierda).
     */
    public function number(): string
    {
        return 'OC-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseOrderStatus::Cancelled;
    }

    /**
     * Órdenes emitidas que todavía esperan su factura.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeAwaitingInvoice(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Issued->value);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    /**
     * La factura vigente (sin anular) de esta orden.
     *
     * @return HasOne<Purchase, $this>
     */
    public function purchase(): HasOne
    {
        return $this->hasOne(Purchase::class)->whereNull('cancelled_at');
    }
}
