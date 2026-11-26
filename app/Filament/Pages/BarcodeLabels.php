<?php

namespace App\Filament\Pages;

use App\Actions\Variants\AssignMissingVariantSkus;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Concerns\BelongsToModule;
use App\Http\Controllers\BarcodeLabelsPrintController;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Barcodes\LabelLayout;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Etiquetas (módulo Códigos de barras, solo administrador): se elige cuántas etiquetas
 * imprimir de cada variante y se abre la impresión en otra pestaña. Puede llegar con la
 * selección ya cargada (`?v=12:3,15:1`) desde un producto, sus variantes o una compra.
 */
class BarcodeLabels extends AdministratorPage
{
    use BelongsToModule;

    /** Filas máximas por pantalla: para ver otras hay que filtrar. */
    public const LIMIT = 200;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Etiquetas';

    protected static ?string $title = 'Etiquetas con código de barras';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'barcode-labels';

    protected string $view = 'filament.pages.barcode-labels';

    #[Url(as: 'v')]
    public string $selection = '';

    public ?int $categoryId = null;

    public string $search = '';

    /** Posición de la primera etiqueta en la hoja (solo hojas con varias etiquetas). */
    public int $startAt = 1;

    /** @var array<int|string, string|null> variante => cantidad de etiquetas */
    public array $quantities = [];

    protected static function module(): Module
    {
        return Module::Barcodes;
    }

    public function mount(): void
    {
        foreach (BarcodeLabelsPrintController::parseSelection($this->selection) as $id => $quantity) {
            $this->quantities[$id] = (string) $quantity;
        }

        $this->selection = '';
    }

    /**
     * Variantes a mostrar: las que ya tienen cantidad y las que pasan los filtros.
     *
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        $chosen = array_keys($this->quantities);

        $filtered = $this->variantsQuery()->limit(self::LIMIT)->get();

        if ($chosen === []) {
            return $filtered;
        }

        $extra = ProductVariant::query()
            ->with('product.category')
            ->whereIn('id', $chosen)
            ->whereNotIn('id', $filtered->modelKeys())
            ->orderBy('product_id')
            ->orderBy('position')
            ->get();

        return $extra->concat($filtered)->values();
    }

    public function getTotal(): int
    {
        return $this->variantsQuery()->count();
    }

    /**
     * @return array<int, string>
     */
    public function getCategories(): array
    {
        return Category::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function labelLayout(): LabelLayout
    {
        return LabelLayout::fromSettings(Modules::settings(Module::Barcodes));
    }

    /**
     * Cantidad pedida por variante, sin lo que está vacío o en cero.
     *
     * @return array<int, int>
     */
    public function getRequested(): array
    {
        $requested = [];

        foreach ($this->quantities as $id => $quantity) {
            if (is_numeric($quantity) && (int) $quantity > 0) {
                $requested[(int) $id] = min(BarcodeLabelsPrintController::MAX_PER_VARIANT, (int) $quantity);
            }
        }

        return $requested;
    }

    public function getTotalLabels(): int
    {
        return array_sum($this->getRequested());
    }

    public function getPrintUrl(): string
    {
        return BarcodeLabelsPrintController::url($this->getRequested(), $this->startAt);
    }

    /**
     * Cuántas variantes con nombre todavía no tienen SKU (y por eso no tienen código).
     */
    public function getWithoutCodeCount(): int
    {
        return ProductVariant::query()
            ->where('name', '!=', '')
            ->where(fn (Builder $query) => $query->whereNull('sku')->orWhere('sku', ''))
            ->count();
    }

    public function oneOfEach(): void
    {
        foreach ($this->getVariants() as $variant) {
            $this->quantities[$variant->getKey()] = '1';
        }
    }

    public function fromStock(): void
    {
        if (! Modules::enabled(Module::Stock)) {
            return;
        }

        foreach ($this->getVariants() as $variant) {
            $this->quantities[$variant->getKey()] = (string) max(0, $variant->stock);
        }
    }

    public function clearQuantities(): void
    {
        $this->quantities = [];
    }

    public function fillMissingSkus(): void
    {
        $assigned = app(AssignMissingVariantSkus::class)->handle();

        Notification::make()
            ->title($assigned === 0 ? 'Todas las variantes ya tienen SKU' : "Se asignó SKU a {$assigned} variantes")
            ->success()
            ->send();
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function variantsQuery(): Builder
    {
        $search = trim($this->search);

        return ProductVariant::query()
            ->with('product.category')
            ->sellable()
            ->when($this->categoryId, fn ($query, int $categoryId) => $query
                ->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")),
            ))
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Dirección de esta página con una selección cargada (para los accesos desde otros lugares).
     *
     * @param  array<int, int>  $selection  variante => cantidad
     */
    public static function urlFor(array $selection): string
    {
        $pairs = [];

        foreach ($selection as $id => $quantity) {
            $pairs[] = "{$id}:{$quantity}";
        }

        return static::getUrl(['v' => implode(',', $pairs)]);
    }

    /**
     * Etiquetas de un producto recién creado: una por unidad del stock inicial (mínimo una)
     * o una por variante, según `labels_on_create_quantity`.
     *
     * @return array<int, int> variante => cantidad
     */
    public static function selectionForNewProduct(Product $product): array
    {
        $byStock = Modules::enabled(Module::Stock)
            && Modules::setting(Module::Barcodes, 'labels_on_create_quantity', 'stock') === 'stock';

        return $product->variants()
            ->sellable()
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->getKey() => $byStock
                    ? min(BarcodeLabelsPrintController::MAX_PER_VARIANT, max(1, $variant->stock))
                    : 1,
            ])
            ->all();
    }

    /**
     * Variantes vendibles de los productos, una etiqueta de cada una.
     *
     * @param  iterable<int, Model>  $products  Productos (o los registros de una acción masiva sobre ellos)
     * @return array<int, int>
     */
    public static function selectionForProducts(iterable $products): array
    {
        $ids = collect($products)->map(fn (Model $product): int => (int) $product->getKey())->all();

        return ProductVariant::query()
            ->sellable()
            ->whereIn('product_id', $ids)
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => 1])
            ->all();
    }
}
