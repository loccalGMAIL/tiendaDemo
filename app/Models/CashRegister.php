<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un puesto de cobro del mostrador (módulo Control de caja). Tiene a lo sumo un turno
 * abierto. Con turnos no se borra: se desactiva.
 */
#[Fillable(['name', 'paper_width', 'active', 'position'])]
class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paper_width' => 'integer',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<CashSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * El turno abierto, si hay.
     *
     * @return HasOne<CashSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->whereNull('closed_at')->latestOfMany();
    }

    protected function activitySubjectLabel(): string
    {
        return 'caja';
    }
}
