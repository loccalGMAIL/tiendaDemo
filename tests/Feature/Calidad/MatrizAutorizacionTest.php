<?php

use App\Facades\Modules;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Matriz de autorización: cada ruta propia, con cada actor, con todos los módulos encendidos.
 *
 * Declara quién entra (200) a cada ruta; el resto tiene que quedar afuera (el invitado va al
 * login; los demás reciben 403 o 404). Si aparece una ruta que no está declarada, el test falla:
 * obliga a decidir quién puede verla antes de publicarla.
 */
const ACCESO_PUBLICO = ['invitado', 'vendedor', 'admin', 'superadmin', 'inactivo'];
const ACCESO_PERSONAL = ['vendedor', 'admin'];   // el equipo de la tienda: vendedor y administrador
const ACCESO_ADMIN = ['admin'];
const ACCESO_PLATAFORMA = ['superadmin'];

/**
 * Quién entra a cada ruta (clave: «panel», «plataforma» o «landing» + la ruta sin parámetros resueltos).
 *
 * @return array<string, list<string>>
 */
function declaredAccess(): array
{
    $panelStaff = ['/', '/profile', '/customers', '/customers/create', '/customers/{record}', '/payments', '/payments/create', '/products', '/sales', '/sales/create', '/caja'];
    $panelAdmin = [
        '/color-settings', '/informe-saldos', '/informe-cobranza', '/informe-cobros', '/informe-compras', '/informe-ventas',
        '/site-settings', '/stock-count', '/stock-movements', '/activity-logs', '/activity-logs/{record}',
        '/categories', '/categories/create', '/categories/{record}/edit',
        '/customers/{record}/edit',
        '/financing-plans', '/financing-plans/create', '/financing-plans/{record}/edit',
        '/payment-methods', '/payment-methods/create', '/payment-methods/{record}/edit',
        '/payments/{record}', '/products/create', '/products/{record}/edit',
        '/purchase-orders', '/purchase-orders/create', '/purchase-orders/{record}', '/purchase-orders/{record}/edit',
        '/purchases', '/purchases/create', '/purchases/{record}', '/sales/{record}',
        '/suppliers', '/suppliers/create', '/suppliers/{record}/edit',
        '/users', '/users/create', '/users/{record}/edit',
        '/informes/{report}/imprimir', '/compras/ordenes/{order}/imprimir',
        '/barcode-labels', '/etiquetas/imprimir',
        '/variant-attributes',
        '/ticket-settings',
        // El ticket de una venta lo ve quien ve la venta: la del recorrido no es del vendedor de prueba.
        '/ventas/{sale}/ticket',
    ];
    $platform = ['/profile', '/appearance', '/modules', '/', '/activity-logs', '/activity-logs/{record}', '/users', '/users/create', '/users/{record}/edit'];

    $access = [];

    foreach ($panelStaff as $path) {
        $access['panel '.$path] = ACCESO_PERSONAL;
    }

    foreach ($panelAdmin as $path) {
        $access['panel '.$path] = ACCESO_ADMIN;
    }

    foreach ($platform as $path) {
        $access['plataforma '.$path] = ACCESO_PLATAFORMA;
    }

    foreach (['/', '/catalogo', '/catalogo/{slug}', '/producto/{slug}'] as $path) {
        $access['landing '.$path] = ACCESO_PUBLICO;
    }

    return $access;
}

it('cada ruta deja entrar solo a quienes tiene declarados', function () {
    resetModules();
    Storage::fake('public');
    config([
        'platform.admin.email' => 'admin@cliente.test',
        'platform.admin.password' => 'secreto-123',
        'services.google.client_id' => 'id',
        'services.google.client_secret' => 'secret',
    ]);
    $this->seed(DatabaseSeeder::class);
    Modules::enableAll();
    $this->seed(DemoSeeder::class);

    $actors = [
        'invitado' => null,
        'vendedor' => User::factory()->seller()->create(),
        'admin' => User::where('email', 'admin@cliente.test')->firstOrFail(),
        'superadmin' => User::factory()->superadmin()->create(),
        'inactivo' => User::factory()->admin()->inactive()->create(),
    ];

    // La demostración no trae proveedores, órdenes ni compras: se crean para tener un registro real de cada recurso.
    Supplier::factory()->create();
    PurchaseOrder::factory()->create();
    Purchase::factory()->create();

    // Un registro real de cada recurso: así «404» significa lo que dice y no «no existe el 1».
    $records = [
        'activity-logs' => ActivityLog::query()->value('id'),
        'categories' => Category::query()->value('id'),
        'customers' => Customer::query()->value('id'),
        'financing-plans' => FinancingPlan::query()->value('id'),
        'payment-methods' => PaymentMethod::query()->value('id'),
        'payments' => Payment::query()->onAccount()->value('id'),
        'products' => Product::query()->value('id'),
        'purchase-orders' => PurchaseOrder::query()->value('id'),
        'purchases' => Purchase::query()->value('id'),
        'sales' => Sale::query()->value('id'),
        'suppliers' => Supplier::query()->value('id'),
        'users' => User::query()->value('id'),
    ];

    $declared = declaredAccess();
    $undeclared = [];
    $wrong = [];
    $checked = 0;

    foreach (Route::getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');
        $methods = $route->methods();
        $domain = (string) $route->getDomain();

        $skip = ! in_array('GET', $methods, true)
            || preg_match('#^/(livewire[\w-]*|filament|storage|sanctum)(/|$)|^/up$|^/login$|^/auth/google#', $uri);

        if ($skip) {
            continue;
        }

        $zone = match (true) {
            $domain === config('domains.panel') => 'panel',
            $domain === config('domains.superadmin') => 'plataforma',
            default => 'landing',
        };
        $key = $zone.' '.$uri;

        if (! isset($declared[$key])) {
            $undeclared[] = $key;

            continue;
        }

        $segment = explode('/', trim($uri, '/'))[0];
        $path = preg_replace_callback('/\{(\w+)\??\}/', fn (array $m): string => (string) match ($m[1]) {
            'record' => $records[$segment] ?? 1,
            'order' => $records['purchase-orders'],
            'sale' => $records['sales'],
            'report' => 'ventas',
            'slug' => $segment === 'producto' ? Product::published()->value('slug') : Category::query()->value('slug'),
            default => 1,
        }, $uri);
        $url = 'http://'.($domain !== '' ? $domain : config('domains.landing')).$path;

        foreach ($actors as $name => $user) {
            app('auth')->forgetGuards();
            $this->flushSession();

            if ($user !== null) {
                $this->actingAs($user);
            }

            $status = $this->get($url)->getStatusCode();
            $mayEnter = in_array($name, $declared[$key], true);
            $ok = $mayEnter
                ? $status === 200
                : ($zone === 'landing' ? false : ($name === 'invitado' ? $status === 302 : in_array($status, [403, 404], true)));

            if (! $ok) {
                $wrong[] = "{$key} como {$name}: respondió {$status}, ".($mayEnter ? 'tenía que entrar' : 'tenía que quedar afuera');
            }

            $checked++;
        }
    }

    expect($undeclared)->toBe([], 'Rutas nuevas sin declarar en la matriz de autorización')
        ->and($wrong)->toBe([])
        ->and($checked)->toBeGreaterThan(250);
});

it('el acceso con Google solo responde con el módulo encendido y, apagado, no existe', function () {
    resetModules();
    $google = 'http://'.config('domains.panel').'/auth/google/redirect';

    $this->get($google)->assertNotFound();
});
