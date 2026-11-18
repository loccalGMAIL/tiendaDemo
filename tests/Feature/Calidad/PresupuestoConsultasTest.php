<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cantidad de consultas que hace una petición GET.
 */
function queriesOf(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get($url)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

// --- Presupuesto por pantalla, con la demostración cargada ---------------------------------------

it('cada pantalla se mantiene dentro de su presupuesto de consultas', function () {
    resetModules();
    Storage::fake('public');
    config(['platform.admin.email' => 'admin@cliente.test', 'platform.admin.password' => 'secreto-123']);
    $this->seed(DatabaseSeeder::class);
    Modules::enableAll();
    $this->seed(DemoSeeder::class);

    $admin = User::where('email', 'admin@cliente.test')->firstOrFail();
    $panel = 'http://'.config('domains.panel');
    $landing = 'http://'.config('domains.landing');
    $sale = Sale::firstOrFail();
    $customer = Customer::whereHas('installments')->firstOrFail();
    $product = Product::firstOrFail();

    // Lo medido con la demostración (25 ventas, 12 productos, 6 clientes) más un margen: un N+1
    // en cualquiera de estas pantallas lo supera de largo.
    $budgets = [
        $panel.'/' => 35,
        $panel.'/sales' => 15,
        $panel.'/sales/create' => 35,
        $panel.'/sales/'.$sale->id => 14,
        $panel.'/customers' => 18,
        $panel.'/customers/'.$customer->id => 14,
        $panel.'/products' => 26,
        $panel.'/products/'.$product->id.'/edit' => 14,
        $panel.'/payments' => 16,
        $panel.'/payments/create' => 10,
        $panel.'/stock-movements' => 17,
        $panel.'/stock-count' => 13,
        $panel.'/informe-ventas' => 12,
        $panel.'/informe-cobros' => 15,
        $panel.'/informe-saldos' => 10,
        $panel.'/informe-cobranza' => 11,
        $panel.'/financing-plans' => 14,
        $landing.'/' => 12,
        $landing.'/catalogo' => 16,
        $landing.'/producto/'.$product->slug => 18,
    ];

    $over = [];

    foreach ($budgets as $url => $budget) {
        $this->actingAs($admin);
        $queries = queriesOf($url);

        if ($queries > $budget) {
            $over[] = "{$url}: {$queries} consultas (presupuesto {$budget})";
        }
    }

    expect($over)->toBe([]);
});

// --- Las listas no hacen más consultas con más filas (el N+1) ----------------------------------------

it('las listas no hacen más consultas con más filas', function (string $path, Closure $addRows) {
    Modules::enable(Module::Stock);
    Modules::enable(Module::CurrentAccount);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $url = 'http://'.config('domains.panel').$path;

    // Las dos cantidades caben en la primera página (10 filas): lo único que cambia es cuántas filas se dibujan.
    $addRows(2);
    queriesOf($url); // calentamiento: la primera petición carga una sola vez los módulos y la configuración
    $few = queriesOf($url);

    $addRows(6);
    $many = queriesOf($url);

    // Con 8 filas se hacen las mismas consultas que con 2: una consulta por fila sería un N+1.
    expect($many)->toBe($few);
})->with([
    'ventas' => ['/sales', fn (int $n) => Sale::factory()->count($n)->create()],
    'clientes' => ['/customers', fn (int $n) => Customer::factory()->count($n)->create()],
    'productos' => ['/products', function (int $n) {
        $category = Category::factory()->create();
        Product::factory()->count($n)->create(['category_id' => $category->id]);
    }],
]);
