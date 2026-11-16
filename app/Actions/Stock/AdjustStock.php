<?php

namespace App\Actions\Stock;

use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Facades\Modules;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStock
{
    public function __construct(private RecordMovement $record) {}

    /**
     * Ajuste manual: deja la variante con la cantidad indicada. Solo administrador,
     * con motivo, y sin movimiento si la cantidad no cambia.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(ProductVariant $variant, int $quantity, string $reason, User $actor): ?StockMovement
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede ajustar el stock.');
        }

        if (! Modules::enabled(Module::Stock)) {
            throw ValidationException::withMessages(['stock' => 'El módulo Stock está apagado.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo del ajuste es obligatorio.']);
        }

        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad no puede ser negativa.']);
        }

        return DB::transaction(function () use ($variant, $quantity, $reason, $actor): ?StockMovement {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $difference = $quantity - $locked->stock;

            if ($difference === 0) {
                return null;
            }

            $movement = $this->record->handle($locked, StockMovementType::Adjustment, $difference, null, $actor, $reason);

            // Deja al objeto recibido con la existencia nueva.
            $variant->refresh();

            return $movement;
        });
    }
}
