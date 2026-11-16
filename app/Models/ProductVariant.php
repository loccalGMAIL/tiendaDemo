<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'name', 'attributes', 'sku', 'price', 'active', 'position'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'decimal:2',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
}
