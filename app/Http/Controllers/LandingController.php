<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Services\Appearance;
use App\Services\SiteContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing pública. El diseño lo define el tema elegido por el SuperAdmin; el
 * contenido y las secciones visibles, el administrador del cliente.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, SiteContent $siteContent, Appearance $appearance): View
    {
        // El botón «Ingresar al sistema» lleva al panel conservando esquema y puerto.
        $port = $request->getPort();
        $panelUrl = $request->getScheme().'://'.config('domains.panel')
            .(in_array($port, [80, 443], true) ? '' : ':'.$port);

        $site = $siteContent->all();

        $categories = Modules::enabled(Module::Catalog)
            ? Category::query()->where('active', true)->orderBy('position')->orderBy('name')->get()
            : collect();

        $visible = collect(array_keys(SiteContent::SECTIONS))
            ->filter(fn (string $section): bool => $siteContent->sectionEnabled($section) && match ($section) {
                'hero' => true,
                'value' => $site['value_items'] !== [],
                'catalog' => $categories->isNotEmpty(),
                'faq' => $site['faq_items'] !== [],
                'contact' => $siteContent->hasContact(),
                default => false,
            })
            ->values()
            ->all();

        $themeKey = $appearance->themeKey();

        return view($appearance->theme()['views'].'.index', [
            'site' => $site,
            'siteContent' => $siteContent,
            'appearance' => $appearance,
            'themeKey' => $themeKey,
            'panelUrl' => $panelUrl,
            'categories' => $categories,
            'sections' => $visible,
            'description' => trim($site['tagline']) !== '' ? $site['tagline'] : (trim($site['hero_subtitle']) !== '' ? $site['hero_subtitle'] : $siteContent->name()),
        ]);
    }
}
