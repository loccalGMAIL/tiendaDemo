<?php

namespace App\Filament\Pages;

use App\Actions\Stock\CountStock;
use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Carga de stock (módulo Stock, solo administrador): stock inicial y recuentos sin
 * necesidad de Compras. Se escribe la cantidad contada de cada variante en una
 * grilla; al guardar se genera un ajuste por cada diferencia.
 */
class StockCount extends AdministratorPage
{
    use BelongsToModule;

    /** Filas máximas por pantalla: para ver otras hay que filtrar. */
    public const LIMIT = 200;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Carga de stock';

    protected static ?string $title = 'Carga de stock';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'stock-count';

    protected string $view = 'filament.pages.stock-count';

    public ?int $categoryId = null;

    public string $search = '';

    public string $reason = 'Recuento';

    /** @var array<int|string, string|null> variante => cantidad contada */
    public array $counts = [];

    /** @var array<int|string, string|null> variante => stock mínimo */
    public array $minimums = [];

    protected static function module(): Module
    {
        return Module::Stock;
    }

    /**
     * Variantes vendibles según los filtros.
     *
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variantsQuery()->limit(self::LIMIT)->get();
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

    public function save(): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        try {
            $result = app(CountStock::class)->handle($this->counts, $this->reason, $actor, $this->minimums);
        } catch (ValidationException $e) {
            Notification::make()->title($e->validator->errors()->first())->danger()->send();

            return;
        }

        $this->counts = [];
        $this->minimums = [];

        Notification::make()
            ->title($result['adjustments'] === 0 && $result['minimums'] === 0
                ? 'No había diferencias para registrar'
                : "Se registraron {$result['adjustments']} ajustes de stock y {$result['minimums']} cambios de mínimo")
            ->success()
            ->send();
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function variantsQuery(): Builder
    {
        return ProductVariant::query()
            ->with('product.category')
            ->sellable()
            ->when($this->categoryId, fn ($query, int $categoryId) => $query
                ->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(
                fn ($q) => $q
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('sku', 'like', '%'.trim($this->search).'%')
                    ->orWhereHas('product', fn ($product) => $product
                        ->where('name', 'like', '%'.trim($this->search).'%')
                        ->orWhere('sku', 'like', '%'.trim($this->search).'%')),
            ))
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id');
    }
}
