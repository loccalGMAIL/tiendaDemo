<?php

namespace App\Models;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Concerns\LogsActivity;
use App\Services\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'sku', 'slug', 'name', 'brand', 'description', 'category_id', 'supplier_id', 'price', 'cost', 'active',
    'is_published', 'is_featured', 'show_price', 'images',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        // Slug automático y único a partir del nombre cuando no se indica. No se vuelve a
        // generar al renombrar: los enlaces públicos del catálogo no se rompen.
        static::saving(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = $product->uniqueSlug();
            }
        });

        // Las imágenes del catálogo se borran del disco junto con el producto.
        static::deleted(function (Product $product): void {
            foreach ($product->imagePaths() as $path) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'active' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'show_price' => 'boolean',
            'images' => 'array',
        ];
    }

    /**
     * Productos que se muestran en el catálogo público: activos y publicados.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('active', true)->where('is_published', true);
    }

    /**
     * Productos que se listan en el catálogo: publicados y, si el módulo Stock está
     * encendido y el catálogo oculta los agotados, con existencia en alguna variante vendible.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInCatalog(Builder $query): Builder
    {
        $query->published();

        if (Modules::enabled(Module::Stock) && ! Modules::setting(Module::Catalog, 'show_sold_out', true)) {
            $query->whereHas('variants', fn (Builder $variants) => $variants->sellable()->where('stock', '>', 0));
        }

        return $query;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Proveedor habitual (módulo Compras).
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        // chaperone: las variantes que se crean o se leen desde el producto ya conocen a su producto
        // (su nombre y el registro de actividad lo necesitan) sin una consulta por variante.
        return $this->hasMany(ProductVariant::class)->chaperone('product')->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
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

    // --- Catálogo web -------------------------------------------------------

    /**
     * Rutas de las imágenes cargadas (como se guardaron en el disco público).
     *
     * @return list<string>
     */
    public function imagePaths(): array
    {
        return array_values(array_filter((array) $this->images, 'is_string'));
    }

    /**
     * URLs de las imágenes que existen en el disco (relativas al host actual).
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return array_values(array_map(
            fn (string $path): string => asset('storage/'.ltrim($path, '/')),
            array_filter($this->imagePaths(), fn (string $path): bool => Storage::disk('public')->exists($path)),
        ));
    }

    /**
     * Variantes que se pueden vender (ver ProductVariant::sellable()): con Variantes
     * encendido y variantes con nombre, esas; si no, la implícita. Usa la relación cargada.
     *
     * @return Collection<int, ProductVariant>
     */
    public function sellableVariants(): Collection
    {
        $named = $this->variants->filter(fn (ProductVariant $variant): bool => ! $variant->isImplicit());

        if (Modules::enabled(Module::Variants) && $named->isNotEmpty()) {
            return $named->where('active', true)->values();
        }

        return $this->variants->filter(fn (ProductVariant $variant): bool => $variant->isImplicit() && $variant->active)->values();
    }

    /**
     * Agotado: con Stock encendido, ninguna variante vendible tiene existencia.
     */
    public function isSoldOut(): bool
    {
        return Modules::enabled(Module::Stock)
            && $this->sellableVariants()->every(fn (ProductVariant $variant): bool => $variant->stock <= 0);
    }

    /**
     * Variantes que muestra la ficha pública (solo con el módulo Variantes y variantes
     * con nombre), marcando las agotadas; si el módulo Catálogo oculta los agotados, no se listan.
     *
     * @return list<array{name: string, sold_out: bool}>
     */
    public function catalogVariants(): array
    {
        if (! Modules::enabled(Module::Variants)) {
            return [];
        }

        $showSoldOut = (bool) Modules::setting(Module::Catalog, 'show_sold_out', true);
        $withStock = Modules::enabled(Module::Stock);
        $variants = [];

        foreach ($this->sellableVariants()->reject(fn (ProductVariant $variant): bool => $variant->isImplicit()) as $variant) {
            $soldOut = $withStock && $variant->stock <= 0;

            if ($soldOut && ! $showSoldOut) {
                continue;
            }

            $variants[] = ['name' => $variant->name, 'sold_out' => $soldOut];
        }

        return $variants;
    }

    /**
     * Texto de precio del catálogo: «Consultar precio» si no se muestra; «Desde $ X»
     * si las variantes tienen precios distintos; si no, el precio del producto.
     */
    public function catalogPriceLabel(): string
    {
        if (! $this->show_price) {
            return 'Consultar precio';
        }

        $prices = $this->sellableVariants()
            ->reject(fn (ProductVariant $variant): bool => $variant->isImplicit())
            ->map(fn (ProductVariant $variant): string => $this->effectivePrice($variant))
            ->unique()
            ->values();

        if ($prices->count() > 1) {
            $lowest = $prices->reduce(fn (?string $carry, string $price): string => $carry === null || Money::compare($price, $carry) < 0 ? $price : $carry);

            return 'Desde '.Money::format((string) $lowest);
        }

        return Money::format($prices->first() ?? $this->price);
    }

    private function uniqueSlug(): string
    {
        $base = Str::slug($this->name) ?: 'producto';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function activitySubjectLabel(): string
    {
        return 'producto';
    }
}
