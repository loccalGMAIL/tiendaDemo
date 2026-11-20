<?php

namespace App\Models;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El stock no es asignable en masa: solo cambia por movimientos (ventas, ajustes, compras).
 */
#[Fillable(['product_id', 'name', 'attributes', 'sku', 'price', 'min_stock', 'active', 'position'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Variantes que se pueden vender: activas, de un producto activo. Con el módulo
     * Variantes apagado solo existe la implícita; con él encendido, la implícita se
     * ofrece únicamente si el producto no tiene variantes con nombre.
     *
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeSellable(Builder $query): Builder
    {
        $query->where('active', true)
            ->whereHas('product', fn (Builder $product) => $product->where('active', true));

        if (! Modules::enabled(Module::Variants)) {
            return $query->where('name', '');
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', '!=', '')
            ->orWhereDoesntHave('product.variants', fn (Builder $named) => $named->where('name', '!=', '')));
    }

    /**
     * Stock bajo: tiene mínimo definido y la existencia llegó a él o lo pasó.
     *
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /**
     * Precio de venta: el propio si lo tiene, si no el del producto.
     */
    public function effectivePrice(): string
    {
        return $this->price ?? $this->product->price;
    }

    public function isImplicit(): bool
    {
        return $this->name === '';
    }

    /**
     * "Remera lisa — M / Negro", o solo el nombre del producto para la variante implícita.
     */
    public function fullName(): string
    {
        return $this->isImplicit()
            ? $this->product->name
            : $this->product->name.' — '.$this->name;
    }

    /**
     * Una variante con ventas o movimientos de stock no se borra: se desactiva.
     */
    public function hasHistory(): bool
    {
        return SaleItem::query()->where('product_variant_id', $this->getKey())->exists()
            || StockMovement::query()->where('product_variant_id', $this->getKey())->exists();
    }

    /**
     * La variante implícita se crea con cada producto; no se registra para no llenar la actividad.
     */
    protected function activityEnabled(): bool
    {
        return ! $this->isImplicit();
    }

    /**
     * El stock cambia con cada venta y ya tiene su historial de movimientos.
     *
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return ['stock'];
    }

    protected function activitySubjectLabel(): string
    {
        return 'variante';
    }

    protected function activityName(): string
    {
        // Carga explícita (no «a demanda»): el nombre del registro de actividad necesita el producto
        // aunque la variante se haya leído sola, y cuando viene del producto ya está cargado.
        $this->loadMissing('product');

        return $this->fullName();
    }
}
