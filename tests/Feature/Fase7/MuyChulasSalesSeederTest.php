<?php

use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MuyChulasSalesSeeder;
use Database\Seeders\MuyChulasSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    resetModules();
    Storage::fake('public');
    config(['platform.admin.email' => 'admin@cliente.test', 'platform.admin.password' => 'secreto-123']);
    $this->seed(DatabaseSeeder::class);
});

it('carga las ventas de prueba sobre los productos de Muy Chulas', function () {
    Modules::enableAll();
    $this->seed(MuyChulasSeeder::class);
    $this->seed(MuyChulasSalesSeeder::class);

    expect(Sale::count())->toBe(22)
        ->and(Sale::where('status', SaleStatus::Cancelled)->count())->toBe(2)
        ->and(Sale::whereNotNull('customer_id')->count())->toBeGreaterThan(0)
        ->and(Customer::count())->toBe(5)
        ->and(Sale::where('date', '>', now()->toDateString())->count())->toBe(0)
        ->and(Sale::query()->get()->every(fn (Sale $sale): bool => $sale->items()->exists() && $sale->payments()->exists()))->toBeTrue();
});

it('descuenta el stock y deja las ventas anuladas sin efecto', function () {
    Modules::enableAll();
    $this->seed(MuyChulasSeeder::class);
    $this->seed(MuyChulasSalesSeeder::class);

    $blazer = Product::where('name', 'Blazer')->firstOrFail();

    expect($blazer->implicitVariant->stock)->toBe(1)
        ->and(ProductVariant::where('stock', '<', 0)->count())->toBe(0)
        ->and(StockMovement::count())->toBeGreaterThan(0);
});

it('con solo el núcleo carga las ventas sobre las variantes implícitas', function () {
    $this->seed(MuyChulasSeeder::class);
    $this->seed(MuyChulasSalesSeeder::class);

    expect(Sale::count())->toBe(22);
});

it('si faltan los productos no carga nada', function () {
    $this->seed(MuyChulasSalesSeeder::class);

    expect(Sale::count())->toBe(0)->and(Customer::count())->toBe(0);
});

it('si ya hay ventas no agrega nada', function () {
    $this->seed(MuyChulasSeeder::class);
    $this->seed(MuyChulasSalesSeeder::class);

    $this->seed(MuyChulasSalesSeeder::class);

    expect(Sale::count())->toBe(22);
});

it('en producción se niega sin tocar la base', function () {
    $this->seed(MuyChulasSeeder::class);
    $this->app['env'] = 'production';

    app(MuyChulasSalesSeeder::class)->run();

    expect(Sale::count())->toBe(0);
});
