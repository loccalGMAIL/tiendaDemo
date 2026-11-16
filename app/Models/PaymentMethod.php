<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'installments', 'adjustment_percent', 'active', 'position'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installments' => 'integer',
            'adjustment_percent' => 'decimal:2',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * "Tarjeta 3 cuotas (+15 %)", "Efectivo (−10 %)" o solo el nombre si no tiene ajuste.
     */
    public function label(): string
    {
        $percent = (float) $this->adjustment_percent;

        if ($percent === 0.0) {
            return $this->name;
        }

        $formatted = rtrim(rtrim(number_format(abs($percent), 2, ',', ''), '0'), ',');

        return $this->name.' ('.($percent > 0 ? '+' : '−').$formatted.' %)';
    }

    protected function activitySubjectLabel(): string
    {
        return 'medio de pago';
    }
}
