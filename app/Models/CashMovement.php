<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Libro del efectivo de un turno (módulo Control de caja): inmutable. El monto lleva signo
 * (entra positivo, sale negativo); lo esperado en el cajón es la suma del turno. Una
 * corrección es un movimiento nuevo.
 */
#[Fillable(['cash_session_id', 'type', 'amount', 'description', 'user_id'])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de caja no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de caja no se borran.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La venta que lo originó, si hay.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
