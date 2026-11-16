<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Historial de stock: inmutable. Sin updated_at; no se modifica ni se borra.
 */
#[Fillable(['product_variant_id', 'type', 'quantity', 'balance', 'user_id', 'reason'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de stock es inmutable.'));
        static::deleting(fn () => throw new LogicException('El historial de stock es inmutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
