<?php

namespace App\Actions\Purchases\Concerns;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reglas comunes de las operaciones de Compras: solo administrador, módulo encendido,
 * proveedor válido y líneas (variante y cantidad) bien formadas.
 */
trait GuardsPurchasing
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    protected function guard(User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede operar con compras.');
        }

        if (! Modules::enabled(Module::Purchases)) {
            throw ValidationException::withMessages(['purchases' => 'El módulo Compras está apagado.']);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function resolveSupplier(mixed $id): Supplier
    {
        $supplier = filled($id) ? Supplier::query()->find($id) : null;

        if ($supplier === null) {
            throw ValidationException::withMessages(['supplier_id' => 'Elegí un proveedor.']);
        }

        if (! $supplier->active) {
            throw ValidationException::withMessages(['supplier_id' => 'El proveedor está inactivo.']);
        }

        return $supplier;
    }

    /**
     * @throws ValidationException
     */
    protected function resolveReason(mixed $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo de la anulación es obligatorio.']);
        }

        return $reason;
    }

    /**
     * Líneas de una orden: variante y cantidad entera de 1 en adelante. Si una variante
     * se repite, se suman las cantidades.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{variant: ProductVariant, quantity: int}>
     *
     * @throws ValidationException
     */
    protected function orderLines(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La orden necesita al menos un producto.']);
        }

        $variants = $this->loadVariants($items);
        $lines = [];

        foreach ($items as $item) {
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Cada cantidad tiene que ser un entero de 1 en adelante.']);
            }

            $id = (int) $item['variant_id'];
            $lines[$id] = [
                'variant' => $variants->get($id),
                'quantity' => ($lines[$id]['quantity'] ?? 0) + $quantity,
            ];
        }

        return array_values($lines);
    }

    /**
     * Variantes de las líneas, con su producto, por id.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, ProductVariant>
     *
     * @throws ValidationException
     */
    protected function loadVariants(array $items): Collection
    {
        $ids = collect($items)->pluck('variant_id')->filter()->unique()->values();

        $variants = ProductVariant::query()->with('product')->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($items as $item) {
            if (! $variants->has((int) ($item['variant_id'] ?? 0))) {
                throw ValidationException::withMessages(['items' => 'Hay un producto que no existe.']);
            }
        }

        return $variants;
    }
}
