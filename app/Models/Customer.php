<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Models\Concerns\LogsActivity;
use App\Services\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'document_number', 'phone', 'email', 'address', 'city', 'notes', 'active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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
     * Vencimientos de sus ventas a cuenta (módulo Cuenta corriente).
     *
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * Historial de su cuenta corriente, del más nuevo al más viejo.
     *
     * @return HasMany<AccountMovement, $this>
     */
    public function accountMovements(): HasMany
    {
        return $this->hasMany(AccountMovement::class)->latest('id');
    }

    /**
     * Ítems de todo lo que compró (ficha del cliente).
     *
     * @return HasManyThrough<SaleItem, Sale, $this>
     */
    public function saleItems(): HasManyThrough
    {
        return $this->hasManyThrough(SaleItem::class, Sale::class)
            ->where('sales.status', '!=', SaleStatus::Cancelled->value);
    }

    /**
     * Lo que debe: suma de los saldos de sus vencimientos con deuda.
     */
    public function accountBalance(): string
    {
        return Money::sum(
            $this->installments()->open()->get()->map(fn (Installment $installment): string => $installment->balance())->all(),
        );
    }

    /**
     * Lo que debe y ya venció.
     */
    public function overdueBalance(): string
    {
        return Money::sum(
            $this->installments()->overdue()->get()->map(fn (Installment $installment): string => $installment->balance())->all(),
        );
    }

    protected function activitySubjectLabel(): string
    {
        return 'cliente';
    }
}
