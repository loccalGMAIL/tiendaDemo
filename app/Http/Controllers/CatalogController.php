<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLandingData;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo público (módulo Catálogo web, rutas con `module:catalog`): solo productos
 * activos y publicados. Nunca expone costo, SKU interno ni cantidades.
 */
class CatalogController extends Controller
{
    use BuildsLandingData;

    private const PER_PAGE = 12;

    public function index(Request $request): View
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, string $slug): View
    {
        $category = Category::query()->where('active', true)->where('slug', $slug)->firstOrFail();

        return $this->listing($request, $category);
    }

    public function show(Request $request, string $slug): View
    {
        $product = Product::query()
            ->published()
            ->where('slug', $slug)
            ->with(['category', 'variants'])
            ->firstOrFail();

        $data = $this->landingData($request);

        $related = Product::query()
            ->inCatalog()
            ->whereKeyNot($product->getKey())
            ->when($product->category_id, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->with(['category', 'variants'])
            ->orderBy('name')
            ->limit(4)
            ->get();

        $message = "Hola, quería consultar por {$product->name}: ".url()->current();

        return view($data['appearance']->theme()['views'].'.catalog-show', [
            ...$data,
            'product' => $product,
            'images' => $product->imageUrls(),
            'catalogVariants' => $product->catalogVariants(),
            'whatsappUrl' => $data['siteContent']->whatsappUrl($message),
            'related' => $related,
            'pageTitle' => $product->name.' — '.$data['siteContent']->name(),
            'pageDescription' => $product->description !== null && trim($product->description) !== ''
                ? str($product->description)->squish()->limit(160)->toString()
                : $data['description'],
            'ogImage' => $product->imageUrls()[0] ?? null,
        ]);
    }

    private function listing(Request $request, ?Category $category): View
    {
        $data = $this->landingData($request);
        $term = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        /** @var LengthAwarePaginator<int, Product> $products */
        $products = Product::query()
            ->inCatalog()
            ->with(['category', 'variants'])
            ->when($category, fn ($query, Category $category) => $query->where('category_id', $category->getKey()))
            ->when($term !== '', function ($query) use ($term) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

                // ESCAPE explícito: los comodines escritos por la persona se buscan como texto.
                $query->where(fn ($q) => $q
                    ->whereRaw("products.name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("products.brand LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("products.description LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('category', fn ($c) => $c->whereRaw("categories.name LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $title = $category === null ? 'Catálogo' : $category->name;

        return view($data['appearance']->theme()['views'].'.catalog-index', [
            ...$data,
            'products' => $products,
            'category' => $category,
            'term' => $term,
            'pageTitle' => $title.' — '.$data['siteContent']->name(),
            'pageDescription' => $data['description'],
        ]);
    }
}
