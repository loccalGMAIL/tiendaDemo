<?php

namespace App\Models;

use App\Enums\AccountMovementType;
use Database\Factories\AccountMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Historial de la cuenta corriente de un cliente: inmutable (sin updated_at; no se
 * modifica ni se borra). Cada movimiento guarda el saldo acumulado.
 */
#[Fillable(['customer_id', 'date', 'type', 'description', 'debit', 'credit', 'balance', 'user_id'])]
class AccountMovement extends Model
{
    /** @use HasFactory<AccountMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La cuenta corriente es inmutable.'));
        static::deleting(fn () => throw new LogicException('La cuenta corriente es inmutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => AccountMovementType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
            'created_at' => 'datetime',
        ];
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
