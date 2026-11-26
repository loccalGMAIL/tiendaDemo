<?php

namespace App\Actions\Products;

use App\Actions\Stock\RecordMovement;
use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Facades\Modules;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    public const INITIAL_STOCK_REASON = 'Carga inicial';

    public function __construct(
        private RecordMovement $record,
        private GenerateVariants $generator,
    ) {}

    /**
     * Crea el producto con su variante implícita. Si el SKU viene vacío se
     * asigna PROD-00001, PROD-00002, … sin chocar con SKUs cargados a mano.
     *
     * Además puede cargar, en el mismo acto, las variantes (`variants`, cada una con
     * sus `attributes`, `price`, `initial_stock` y `min_stock`) o, para un producto
     * simple, `initial_stock` y `min_stock`. El stock inicial queda como un movimiento.
     * `custom_prices` y `track_min_stock` (por defecto sí) dicen si se usan el precio propio
     * y el stock mínimo cargados.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Product
    {
        $variants = (array) ($data['variants'] ?? []);
        $initialStock = $data['initial_stock'] ?? null;
        $minStock = $data['min_stock'] ?? null;

        // Precio propio y stock mínimo son opcionales: sin pedirlos, lo que haya quedado cargado se descarta.
        $customPrices = (bool) ($data['custom_prices'] ?? true);
        $trackMinStock = (bool) ($data['track_min_stock'] ?? true);

        unset($data['variants'], $data['initial_stock'], $data['min_stock'], $data['custom_prices'], $data['track_min_stock']);

        if (! $trackMinStock) {
            $minStock = null;
        }

        $rows = array_map(fn (array $row): array => [
            ...$row,
            'price' => $customPrices ? $row['price'] : null,
            'min_stock' => $trackMinStock ? $row['min_stock'] : 0,
        ], $this->variantRows($variants));

        return DB::transaction(function () use ($data, $rows, $initialStock, $minStock): Product {
            $sku = trim((string) ($data['sku'] ?? ''));

            $product = Product::create([...$data, 'sku' => $sku !== '' ? $sku : $this->nextSku()]);

            // Todo producto tiene al menos una variante; sin el módulo Variantes es esta, y no se ve.
            $implicit = $product->variants()->create([
                'name' => '',
                'position' => 0,
                'min_stock' => $rows === [] ? $this->count($minStock, 'min_stock') : 0,
            ]);

            if ($rows === []) {
                $this->loadStock($implicit, $this->count($initialStock, 'initial_stock'));

                return $product;
            }

            foreach ($rows as $position => $row) {
                $variant = $product->variants()->create([
                    'name' => implode(' / ', $row['attributes']),
                    'attributes' => $row['attributes'],
                    'price' => $row['price'],
                    'min_stock' => $row['min_stock'],
                    'position' => $position + 1,
                ]);

                $this->loadStock($variant, $row['initial_stock']);
            }

            return $product;
        });
    }

    /**
     * Normaliza y valida las filas de variantes contra los atributos del módulo.
     * Sin el módulo Variantes encendido se ignoran.
     *
     * @param  array<int, mixed>  $variants
     * @return list<array{attributes: array<string, string>, price: ?string, initial_stock: int, min_stock: int}>
     *
     * @throws ValidationException
     */
    private function variantRows(array $variants): array
    {
        if ($variants === [] || ! Modules::enabled(Module::Variants)) {
            return [];
        }

        $rows = [];
        $seen = [];

        foreach ($variants as $row) {
            $chosen = array_map(fn ($value): array => [(string) $value], (array) ($row['attributes'] ?? []));

            // Cada fila es una única combinación: se valida contra los atributos configurados.
            $combinations = $this->generator->combinationsFor($chosen);
            $attributes = $combinations[0] ?? [];
            $signature = $this->generator->signature($attributes);

            if (count($combinations) !== 1 || isset($seen[$signature])) {
                throw ValidationException::withMessages(['variants' => 'Hay una variante que no corresponde a los atributos configurados.']);
            }

            $seen[$signature] = true;

            $price = $row['price'] ?? null;

            if ($price !== null && $price !== '' && (! is_numeric($price) || (float) $price < 0)) {
                throw ValidationException::withMessages(['variants' => 'El precio de una variante no es válido.']);
            }

            $rows[] = [
                'attributes' => $attributes,
                'price' => $price === null || $price === '' ? null : (string) $price,
                'initial_stock' => $this->count($row['initial_stock'] ?? null, 'variants'),
                'min_stock' => $this->count($row['min_stock'] ?? null, 'variants'),
            ];
        }

        return $rows;
    }

    /**
     * Cantidad entera mayor o igual a cero; vacío cuenta como cero.
     *
     * @throws ValidationException
     */
    private function count(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (! is_numeric($value) || (int) $value != $value || (int) $value < 0) {
            throw ValidationException::withMessages([$field => 'Las cantidades deben ser números enteros, sin negativos.']);
        }

        return (int) $value;
    }

    /**
     * Deja la existencia inicial como un movimiento (solo con el módulo Stock y cantidad mayor a cero).
     */
    private function loadStock(ProductVariant $variant, int $quantity): void
    {
        if ($quantity <= 0 || ! Modules::enabled(Module::Stock)) {
            return;
        }

        $actor = Auth::user();

        $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

        $this->record->handle(
            $locked,
            StockMovementType::Adjustment,
            $quantity,
            null,
            $actor instanceof User ? $actor : null,
            self::INITIAL_STOCK_REASON,
        );
    }

    private function nextSku(): string
    {
        $next = ((int) Product::query()->lockForUpdate()->max('id')) + 1;

        while (Product::query()->where('sku', $this->format($next))->exists()) {
            $next++;
        }

        return $this->format($next);
    }

    private function format(int $number): string
    {
        return 'PROD-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
