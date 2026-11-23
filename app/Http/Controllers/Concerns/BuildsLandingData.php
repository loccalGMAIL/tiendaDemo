<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Product;
use App\Services\Appearance;
use App\Services\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Datos comunes de las páginas públicas (landing y catálogo): contenido del sitio,
 * apariencia, enlace al panel y secciones visibles. El diseño lo define el tema.
 */
trait BuildsLandingData
{
    /**
     * @return array<string, mixed>
     */
    protected function landingData(Request $request, ?SiteContent $siteContent = null): array
    {
        $siteContent ??= app(SiteContent::class);
        $appearance = app(Appearance::class);

        // El botón «Ingresar al sistema» lleva al panel conservando esquema y puerto.
        $port = $request->getPort();
        $panelUrl = $request->getScheme().'://'.config('domains.panel')
            .(in_array($port, [80, 443], true) ? '' : ':'.$port);

        $site = $siteContent->all();

        $catalogOn = Modules::enabled(Module::Catalog);

        /** @var Collection<int, Category> $categories */
        $categories = $catalogOn
            ? Category::query()
                ->where('active', true)
                ->whereHas('products', fn ($products) => $products->inCatalog())
                ->orderBy('position')
                ->orderBy('name')
                ->get()
            : collect();

        /** @var Collection<int, Product> $featured */
        $featured = $catalogOn
            ? Product::query()->inCatalog()->where('is_featured', true)->with(['category', 'variants'])->orderBy('name')->limit(8)->get()
            : collect();

        $visible = collect($siteContent->sectionOrder())
            ->filter(fn (string $section): bool => $siteContent->sectionEnabled($section) && match ($section) {
                'hero' => true,
                'value' => $site['value_items'] !== [],
                'catalog' => $categories->isNotEmpty() || $featured->isNotEmpty(),
                'about' => trim($site['about_text']) !== '',
                'services' => $site['service_items'] !== [],
                'gallery' => collect($site['gallery_items'])->contains(fn (array $item): bool => $siteContent->imageUrl($item['image']) !== null),
                'banner' => trim($site['banner_title']) !== '',
                'faq' => $site['faq_items'] !== [],
                'contact' => $siteContent->hasContact(),
                default => false,
            })
            ->values()
            ->all();

        return [
            'site' => $site,
            'siteContent' => $siteContent,
            'appearance' => $appearance,
            'themeKey' => $appearance->themeKey(),
            'panelUrl' => $panelUrl,
            'categories' => $categories,
            'featured' => $featured,
            'sections' => $visible,
            // El enlace «Catálogo» del menú existe aunque la sección de la landing esté oculta.
            'hasCatalog' => $catalogOn && ($categories->isNotEmpty() || Product::query()->inCatalog()->exists()),
            'description' => trim($site['tagline']) !== '' ? $site['tagline'] : (trim($site['hero_subtitle']) !== '' ? $site['hero_subtitle'] : $siteContent->name()),
        ];
    }
}
