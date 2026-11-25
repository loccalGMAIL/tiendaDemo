<?php

namespace App\Models;

use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de una caja (módulo Control de caja): se abre con un fondo, junta el efectivo de las
 * ventas, los ingresos, los retiros y las devoluciones, y se cierra con el arqueo. Lo abren y
 * lo cierran las acciones de App\Actions\CashRegister; no usa LogsActivity porque registra
 * sus eventos de forma explícita.
 */
#[Fillable([
    'cash_register_id', 'opened_by', 'opened_at', 'opening_amount',
    'closed_by', 'closed_at', 'expected_amount', 'counted_amount', 'difference',
    'left_amount', 'counted_bills', 'closing_notes',
])]
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'left_amount' => 'decimal:2',
            'counted_bills' => 'array',
        ];
    }

    /**
     * Numeración visible: T-00001.
     */
    public function number(): string
    {
        return 'T-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * @param  Builder<CashSession>  $query
     * @return Builder<CashSession>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * «Caja 1 · T-00012 · abierta por Ana a las 09:05».
     */
    public function label(): string
    {
        return $this->register->name.' · '.$this->number().' · abierta por '.$this->openedBy->name.' a las '.$this->opened_at->format('H:i');
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
