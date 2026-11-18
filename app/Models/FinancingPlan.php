<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\FinancingPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan de financiación (módulo Cuotas propias): en cuántas cuotas se divide el saldo y con
 * qué recargo. La venta copia sus datos al vender: cambiarlo después no la afecta.
 */
#[Fillable(['name', 'installments_count', 'surcharge_percent', 'active', 'position'])]
class FinancingPlan extends Model
{
    /** @use HasFactory<FinancingPlanFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installments_count' => 'integer',
            'surcharge_percent' => 'decimal:2',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * «6 cuotas (+12 %)» o «3 cuotas» si no tiene recargo.
     */
    public function label(): string
    {
        $percent = (float) $this->surcharge_percent;
        $text = $this->name.' · '.$this->installments_count.' cuotas';

        if ($percent === 0.0) {
            return $text;
        }

        return $text.' (+'.rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',').' %)';
    }

    protected function activitySubjectLabel(): string
    {
        return 'plan de financiación';
    }
}
