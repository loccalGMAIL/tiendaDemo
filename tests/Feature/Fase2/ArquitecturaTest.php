<?php

use App\Filament\Concerns\BelongsToModule;
use App\Filament\Concerns\RelationManagerBelongsToModule;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Filament\Pages\ColorSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PointOfSale;
use App\Filament\Pages\SiteSettings;
use App\Filament\Pages\StockCount;
use App\Filament\Pages\TicketSettings;
use App\Filament\Pages\VariantAttributes;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\LatestSales;
use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\SalesOverview;
use App\Filament\Widgets\StockOverview;
use Filament\Pages\Page;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Symfony\Component\Finder\Finder;
use Tests\Fixtures\StockPage;
use Tests\Fixtures\StockResource;
use Tests\Fixtures\StockWidget;

/**
 * Núcleo del panel admin: lo único que no pertenece a ningún módulo. Agregar
 * algo acá es una decisión consciente; todo lo demás tiene que declarar su módulo.
 */
const CORE_FILAMENT_CLASSES = [
    // Fase 1
    UserResource::class,
    ActivityLogResource::class,
    // Fase 3: núcleo comercial
    CustomerResource::class,
    CategoryResource::class,
    ProductResource::class,
    PaymentMethodResource::class,
    SaleResource::class,
    Dashboard::class,
    SalesOverview::class,
    LatestSales::class,
    // Fase 5: contenido del sitio (siempre disponible para el administrador)
    SiteSettings::class,
    // Fase 14: la Caja (venta de mostrador) y el formato de su ticket
    PointOfSale::class,
    TicketSettings::class,
];

/**
 * Recursos, páginas independientes y widgets del panel admin (app/Filament, sin Superadmin).
 *
 * @return array<int, class-string>
 */
function adminFilamentClasses(): array
{
    $classes = [];

    foreach ((new Finder)->files()->in(app_path('Filament'))->name('*.php')->exclude('Superadmin') as $file) {
        $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        $class = 'App\\Filament\\'.$relative;

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || $reflection->isTrait() || $reflection->isInterface()) {
            continue;
        }

        $isResource = $reflection->isSubclassOf(Resource::class);
        $isStandalonePage = $reflection->isSubclassOf(Page::class) && ! $reflection->isSubclassOf(ResourcePage::class);
        $isWidget = $reflection->isSubclassOf(Widget::class);
        $isRelationManager = $reflection->isSubclassOf(RelationManager::class);

        if ($isResource || $isStandalonePage || $isWidget || $isRelationManager) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('encuentra los recursos del núcleo y los de los módulos (la prueba no es vacía)', function () {
    expect(adminFilamentClasses())->toContain(
        UserResource::class,
        ActivityLogResource::class,
        StockMovementResource::class,
        StockCount::class,
        ColorSettings::class,
        StockOverview::class,
        LowStockTable::class,
        VariantsRelationManager::class,
        VariantAttributes::class,
    );
});

it('todo recurso, página y widget fuera del núcleo declara su módulo', function () {
    $offenders = [];

    foreach (adminFilamentClasses() as $class) {
        if (in_array($class, CORE_FILAMENT_CLASSES, true)) {
            continue;
        }

        [$trait, $methods] = match (true) {
            is_subclass_of($class, Widget::class) => [WidgetBelongsToModule::class, ['canView']],
            is_subclass_of($class, RelationManager::class) => [RelationManagerBelongsToModule::class, ['canViewForRecord']],
            default => [BelongsToModule::class, ['canAccess', 'shouldRegisterNavigation']],
        };

        if (! in_array($trait, class_uses_recursive($class), true)) {
            $offenders[] = "$class no usa ".class_basename($trait);

            continue;
        }

        // Si la clase redefine el método, su versión tapa el corte del módulo.
        foreach ($methods as $method) {
            $declaredIn = (new ReflectionMethod($class, $method))->getFileName();

            if ($declaredIn !== (new ReflectionClass($trait))->getFileName()) {
                $offenders[] = "$class redefine $method() y se saltea el corte del módulo";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('los fixtures cumplen la misma regla', function () {
    foreach ([StockResource::class, StockPage::class] as $class) {
        expect(class_uses_recursive($class))->toContain(BelongsToModule::class);
    }

    expect(class_uses_recursive(StockWidget::class))->toContain(WidgetBelongsToModule::class);
});

it('el núcleo no depende de ningún módulo en su ruta de acceso', function () {
    foreach (CORE_FILAMENT_CLASSES as $class) {
        expect(class_uses_recursive($class))
            ->not->toContain(BelongsToModule::class)
            ->not->toContain(WidgetBelongsToModule::class);
    }
});
