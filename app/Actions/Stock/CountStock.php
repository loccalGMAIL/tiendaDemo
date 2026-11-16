<?php

namespace App\Actions\Stock;

use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Facades\Modules;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CountStock
{
    public function __construct(private RecordMovement $record) {}

    /**
     * Carga de stock por grilla: stock inicial o recuento. Genera un ajuste por cada
     * diferencia (ninguno si no cambió) y actualiza los mínimos indicados, todo en
     * una transacción. Una variante sin cantidad contada se deja como está.
     *
     * @param  array<int|string, int|string|null>  $counts  variante => cantidad contada
     * @param  array<int|string, int|string|null>  $minimums  variante => stock mínimo
     * @return array{adjustments: int, minimums: int}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(array $counts, string $reason, User $actor, array $minimums = []): array
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede cargar stock.');
        }

        if (! Modules::enabled(Module::Stock)) {
            throw ValidationException::withMessages(['stock' => 'El módulo Stock está apagado.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo es obligatorio (por ejemplo «Stock inicial» o «Recuento»).']);
        }

        $counts = $this->integers($counts, 'counts');
        $minimums = $this->integers($minimums, 'minimums');

        $ids = collect([...array_keys($counts), ...array_keys($minimums)])->unique()->sort()->values();

        return DB::transaction(function () use ($ids, $counts, $minimums, $reason, $actor): array {
            // Se bloquean siempre en el mismo orden (por id).
            $variants = ProductVariant::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $adjustments = 0;
            $changedMinimums = 0;

            foreach ($ids as $id) {
                $variant = $variants->get($id);

                if ($variant === null) {
                    throw ValidationException::withMessages(["counts.$id" => 'La variante indicada no existe.']);
                }

                if (isset($counts[$id]) && $counts[$id] !== $variant->stock) {
                    $this->record->handle($variant, StockMovementType::Adjustment, $counts[$id] - $variant->stock, null, $actor, $reason);
                    $adjustments++;
                }

                if (isset($minimums[$id]) && $minimums[$id] !== $variant->min_stock) {
                    $variant->update(['min_stock' => $minimums[$id]]);
                    $changedMinimums++;
                }
            }

            return ['adjustments' => $adjustments, 'minimums' => $changedMinimums];
        });
    }

    /**
     * Ignora lo vacío y exige enteros no negativos.
     *
     * @param  array<int|string, int|string|null>  $values
     * @return array<int, int>
     */
    private function integers(array $values, string $field): array
    {
        $clean = [];

        foreach ($values as $id => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $number = filter_var($value, FILTER_VALIDATE_INT);

            if ($number === false || $number < 0) {
                throw ValidationException::withMessages(["$field.$id" => 'La cantidad tiene que ser un número entero, 0 o más.']);
            }

            $clean[(int) $id] = $number;
        }

        return $clean;
    }
}
