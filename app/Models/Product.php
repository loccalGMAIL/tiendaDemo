<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['sku', 'name', 'brand', 'description', 'category_id', 'price', 'cost', 'active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * La variante implícita (nombre vacío) que tiene todo producto.
     *
     * @return HasOne<ProductVariant, $this>
     */
    public function implicitVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('name', '')->oldestOfMany();
    }

    /**
     * Precio de venta: el de la variante si tiene uno propio, si no el del producto.
     */
    public function effectivePrice(?ProductVariant $variant = null): string
    {
        if ($variant !== null && $variant->price !== null) {
            return $variant->price;
        }

        return $this->price;
    }

    protected function activitySubjectLabel(): string
    {
        return 'producto';
    }
}
