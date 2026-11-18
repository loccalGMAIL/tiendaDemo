<?php

use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\AccountMovement;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Money;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    resetModules();
    Storage::fake('public');
    config(['platform.admin.email' => 'admin@cliente.test', 'platform.admin.password' => 'secreto-123']);
    $this->seed(DatabaseSeeder::class);
});

function enableOnly(Module ...$modules): void
{
    foreach ($modules as $module) {
        Modules::enable($module);
    }
}

function runDemo(): void
{
    test()->seed(DemoSeeder::class);
}

// --- Seeders iniciales ------------------------------------------------------

it('los seeders iniciales se pueden correr varias veces sin duplicar ni pisar cambios', function () {
    $admin = User::where('email', 'admin@cliente.test')->firstOrFail();
    $admin->update(['password' => 'nueva-clave-456', 'name' => 'Dueña']);
    PaymentMethod::where('name', 'Efectivo')->update(['adjustment_percent' => '-5.00']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('role', Role::Admin->value)->count())->toBe(1)
        ->and(PaymentMethod::count())->toBe(6)
        ->and($admin->fresh()->name)->toBe('Dueña')
        ->and(Hash::check('nueva-clave-456', $admin->fresh()->password))->toBeTrue()
        ->and(PaymentMethod::where('name', 'Efectivo')->value('adjustment_percent'))->toBe('-5.00');
});

// --- Con todos los módulos --------------------------------------------------

it('arma una demostración completa con las acciones reales', function () {
    Modules::enableAll();
    runDemo();

    expect(Category::count())->toBe(5)
        ->and(Product::count())->toBe(12)
        ->and(Customer::count())->toBe(6)
        ->and(Sale::count())->toBe(25)
        ->and(Sale::where('status', 'cancelled')->count())->toBe(2)
        ->and(ProductVariant::where('name', '!=', '')->count())->toBeGreaterThan(20);

    // Los totales cuadran: lo cobrado en el acto de cada venta es su `down_payment`, y lo que
    // queda a cuenta (Cuenta corriente) es el resto del total más el recargo de financiación.
    foreach (Sale::with(['items', 'payments'])->get() as $sale) {
        $paid = Money::sum($sale->payments->pluck('amount')->all());

        expect($paid)->toBe($sale->down_payment)
            ->and(Money::sub(Money::add($sale->total, $sale->financing_surcharge), $sale->financed_amount))->toBe($sale->down_payment)
            ->and(Money::sum($sale->items->pluck('subtotal')->all()))->toBe($sale->subtotal)
            ->and($sale->total_cost)->not->toBe('0.00');
    }

    expect(Sale::whereHas('payments', fn ($q) => $q, '>=', 2)->count())->toBeGreaterThanOrEqual(3)
        ->and(Payment::whereNotNull('cancelled_at')->count())->toBeGreaterThanOrEqual(2);
});

it('la demostración deja ventas en el mes actual y en el anterior, sin fechas futuras', function () {
    Modules::enableAll();
    runDemo();

    $thisMonth = now()->startOfMonth();

    expect(Sale::where('date', '>=', $thisMonth->toDateString())->count())->toBeGreaterThan(0)
        ->and(Sale::whereBetween('date', [$thisMonth->copy()->subMonthNoOverflow()->toDateString(), $thisMonth->copy()->subDay()->toDateString()])->count())->toBeGreaterThan(0)
        ->and(Sale::where('date', '>', now()->toDateString())->count())->toBe(0);
});

it('el stock de la demostración cuadra con las ventas y no queda negativo', function () {
    Modules::enableAll();
    runDemo();

    expect(ProductVariant::where('stock', '<', 0)->count())->toBe(0)
        ->and(StockMovement::where('reason', 'Stock inicial')->count())->toBeGreaterThan(0);

    // El saldo de cada variante es la suma de sus movimientos.
    foreach (ProductVariant::all() as $variant) {
        expect((int) StockMovement::where('product_variant_id', $variant->id)->sum('quantity'))->toBe($variant->stock);
    }

    // Cada venta vigente descontó stock y cada anulada lo devolvió.
    expect(StockMovement::where('type', 'sale')->count())->toBe(SaleItem::count())
        ->and(StockMovement::where('type', 'cancellation')->count())->toBeGreaterThanOrEqual(2);

    // Hay de todo para mostrar: variantes agotadas y por debajo del mínimo.
    expect(ProductVariant::where('stock', 0)->count())->toBeGreaterThan(0)
        ->and(ProductVariant::whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0)->count())->toBeGreaterThan(0);
});

it('las ventas de la demostración quedan en la actividad con su autor', function () {
    Modules::enableAll();
    runDemo();

    expect(ActivityLog::where('event', ActivityEvent::Created)->where('subject_type', (new Sale)->getMorphClass())->count())->toBe(25)
        ->and(ActivityLog::where('event', ActivityEvent::Cancelled)->where('subject_type', (new Sale)->getMorphClass())->count())->toBe(2);
});

// --- Cuenta corriente ---------------------------------------------------------

it('con Cuenta corriente la demostración deja ventas a cuenta, vencidos y cobros de ejemplo', function () {
    Modules::enableAll();
    runDemo();

    // Cinco ventas dejan saldo (dos en las cuotas de un plan: 3 + 6 y las otras tres con un solo
    // vencimiento); algunas ya vencieron y quedan con deuda abierta.
    expect(Sale::count())->toBe(25)
        ->and(Sale::where('financed_amount', '>', 0)->count())->toBe(5)
        ->and(Installment::count())->toBe(12)
        ->and(Installment::query()->overdue()->count())->toBeGreaterThanOrEqual(1)
        ->and(Sale::where('status', SaleStatus::Active->value)->count())->toBeGreaterThanOrEqual(2);

    // Tres cobros a cuenta, uno anulado por error de carga, y una venta que se saldó con cobros.
    expect(Payment::onAccount()->count())->toBe(3)
        ->and(Payment::onAccount()->whereNotNull('cancelled_at')->count())->toBe(1)
        ->and(Installment::where('status', InstallmentStatus::Paid->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(Sale::where('status', SaleStatus::Paid->value)->whereHas('installments')->count())->toBeGreaterThanOrEqual(1);

    // El saldo del libro de cada cliente coincide con lo que debe por sus vencimientos.
    foreach (Customer::all() as $customer) {
        $ledger = (string) (AccountMovement::where('customer_id', $customer->id)->orderByDesc('id')->value('balance') ?? '0.00');

        expect($ledger)->toBe($customer->accountBalance());
    }
});

it('con Cuotas propias la demostración suma dos planes y dos ventas en cuotas con recargo', function () {
    Modules::enableAll();
    runDemo();

    expect(FinancingPlan::query()->orderBy('position')->pluck('installments_count', 'name')->all())->toBe(['3 cuotas' => 3, '6 cuotas' => 6])
        ->and(Sale::whereNotNull('financing_plan_id')->count())->toBe(2)
        ->and(Sale::where('installments_count', 6)->firstOrFail()->financing_surcharge_percent)->toBe('12.00')
        ->and((float) Sale::sum('financing_surcharge'))->toBeGreaterThan(0.0);

    // Lo financiado de cada venta con saldo es lo que queda a cuenta más su recargo, y se reparte exacto.
    foreach (Sale::where('financed_amount', '>', 0)->get() as $sale) {
        expect(Money::add(Money::sub($sale->total, $sale->down_payment), $sale->financing_surcharge))->toBe($sale->financed_amount)
            ->and(Money::sum($sale->installments()->pluck('amount')->all()))->toBe($sale->financed_amount);
    }
});

it('con Cuenta corriente y sin Cuotas propias la demostración deja un único vencimiento por venta', function () {
    enableOnly(Module::CurrentAccount);
    runDemo();

    expect(Installment::count())->toBe(5)
        ->and(FinancingPlan::count())->toBe(0)
        ->and(Sale::whereNotNull('financing_plan_id')->count())->toBe(0);
});

it('la demostración con Cuenta corriente aparece en las pantallas y en el escritorio', function () {
    Modules::enableAll();
    runDemo();

    $admin = User::where('role', Role::Admin->value)->firstOrFail();
    $panel = 'http://'.config('domains.panel');

    $this->actingAs($admin)->get($panel.'/payments')->assertOk();
    $this->get($panel.'/customers/'.Customer::firstOrFail()->id)->assertOk()->assertSee('Saldo a cobrar');
    $this->get($panel.'/')->assertOk()->assertSee('Total por cobrar');
});

it('sin Cuenta corriente la demostración sigue siendo de contado', function () {
    enableOnly(Module::Stock);
    runDemo();

    expect(Sale::count())->toBe(25)
        ->and(Installment::count())->toBe(0)
        ->and(AccountMovement::count())->toBe(0)
        ->and(Payment::onAccount()->count())->toBe(0)
        ->and(Sale::where('status', SaleStatus::Active->value)->count())->toBe(0);
});

it('el catálogo de la demostración tiene publicados, destacados, precio oculto, borrador e imágenes', function () {
    Modules::enableAll();
    runDemo();

    expect(Product::where('is_published', true)->count())->toBe(11)
        ->and(Product::where('is_published', false)->count())->toBe(1)
        ->and(Product::where('is_featured', true)->count())->toBeGreaterThanOrEqual(3)
        ->and(Product::where('show_price', false)->count())->toBe(1);

    if (extension_loaded('gd')) {
        foreach (Product::all() as $product) {
            expect($product->imagePaths())->toHaveCount(1);
            Storage::disk('public')->assertExists($product->imagePaths()[0]);
        }
    }

    $this->get('http://'.config('domains.landing').'/catalogo')->assertOk()->assertSee('Remera básica')->assertDontSee('Bufanda de lana');
});

it('la landing y el escritorio funcionan con los datos de la demostración', function () {
    Modules::enableAll();
    runDemo();
    $this->withoutVite();

    $this->get('http://'.config('domains.landing').'/')->assertOk()->assertSee('Casa Lila');

    $this->actingAs(User::where('email', 'admin@cliente.test')->firstOrFail());
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

    Livewire\Livewire::test(ListSales::class)->assertSuccessful();
    Livewire\Livewire::test(ListProducts::class)->assertSuccessful();
});

// --- Usuarios -----------------------------------------------------------------

it('reusa al administrador existente y crea dos vendedores con la contraseña de demostración', function () {
    config(['platform.demo_password' => 'clave-demo-789']);
    Modules::enableAll();
    runDemo();

    expect(User::where('role', Role::Admin->value)->count())->toBe(1);

    foreach (['ana@demo.test', 'luis@demo.test'] as $email) {
        $seller = User::where('email', $email)->firstOrFail();

        expect($seller->role)->toBe(Role::Seller)
            ->and($seller->active)->toBeTrue()
            ->and(Hash::check('clave-demo-789', $seller->password))->toBeTrue();
    }

    // Cada vendedor cargó sus propias ventas.
    expect(Sale::where('seller_id', User::where('email', 'ana@demo.test')->value('id'))->count())->toBeGreaterThan(0);
});

it('si no hay administrador crea uno de demostración', function () {
    User::query()->delete();
    Modules::enableAll();
    runDemo();

    expect(User::where('email', 'admin@demo.test')->firstOrFail()->role)->toBe(Role::Admin);
});

// --- Solo lo encendido --------------------------------------------------------

it('con solo el núcleo no hay variantes, movimientos ni datos de catálogo', function () {
    runDemo();

    expect(Sale::count())->toBe(25)
        ->and(ProductVariant::where('name', '!=', '')->count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and(ProductVariant::where('stock', '!=', 0)->count())->toBe(0)
        ->and(ProductVariant::where('min_stock', '!=', 0)->count())->toBe(0)
        ->and(Product::where('is_published', true)->count())->toBe(0)
        ->and(Product::where('is_featured', true)->count())->toBe(0)
        ->and(Product::whereNotNull('images')->count())->toBe(0);

    Storage::disk('public')->assertMissing('products');
});

it('con Variantes genera variantes pero no stock ni catálogo', function () {
    enableOnly(Module::Variants);
    runDemo();

    expect(ProductVariant::where('name', '!=', '')->count())->toBeGreaterThan(20)
        ->and(StockMovement::count())->toBe(0)
        ->and(Product::where('is_published', true)->count())->toBe(0)
        ->and(Modules::setting(Module::Variants, 'attributes'))->toHaveCount(2);

    // Las ventas usan variantes con nombre.
    expect(SaleItem::whereHas('variant', fn ($q) => $q->where('name', '!=', ''))->count())->toBeGreaterThan(0);
});

it('con Stock carga existencias sobre las variantes implícitas y no genera variantes', function () {
    enableOnly(Module::Stock);
    runDemo();

    expect(ProductVariant::where('name', '!=', '')->count())->toBe(0)
        ->and(StockMovement::count())->toBeGreaterThan(0)
        ->and(ProductVariant::where('stock', '<', 0)->count())->toBe(0)
        ->and(Product::where('is_published', true)->count())->toBe(0);
});

it('con Catálogo publica y destaca pero no genera variantes ni stock', function () {
    enableOnly(Module::Catalog);
    runDemo();

    expect(Product::where('is_published', true)->count())->toBe(11)
        ->and(ProductVariant::where('name', '!=', '')->count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

it('cada combinación de módulos termina sin errores y con las mismas ventas', function (array $modules) {
    foreach ($modules as $module) {
        Modules::enable($module);
    }

    runDemo();

    expect(Sale::count())->toBe(25)
        ->and(ProductVariant::where('stock', '<', 0)->count())->toBe(0);
})->with([
    'Variantes + Stock' => [[Module::Variants, Module::Stock]],
    'Variantes + Catálogo' => [[Module::Variants, Module::Catalog]],
    'Stock + Catálogo' => [[Module::Stock, Module::Catalog]],
    'Cuenta corriente' => [[Module::CurrentAccount]],
]);

// --- Seguridad ------------------------------------------------------------------

it('si ya hay ventas no agrega nada', function () {
    Modules::enableAll();
    runDemo();
    $counts = [Product::count(), Sale::count(), User::count(), Customer::count(), StockMovement::count()];

    runDemo();

    expect([Product::count(), Sale::count(), User::count(), Customer::count(), StockMovement::count()])->toBe($counts);
});

it('una instalación con ventas propias no recibe la demostración', function () {
    Modules::enableAll();
    $variant = Product::factory()->create()->implicitVariant;
    sellVariant(User::where('email', 'admin@cliente.test')->firstOrFail(), $variant);

    runDemo();

    expect(Sale::count())->toBe(1)->and(Category::count())->toBe(0)->and(User::where('email', 'ana@demo.test')->exists())->toBeFalse();
});

it('en producción se niega sin tocar la base', function () {
    Modules::enableAll();
    $this->app['env'] = 'production';

    // Directo, sin db:seed: en producción ese comando pediría confirmación.
    app(DemoSeeder::class)->run();

    expect(Product::count())->toBe(0)->and(Sale::count())->toBe(0)->and(User::where('email', 'ana@demo.test')->exists())->toBeFalse();
});

it('en producción están prohibidos los comandos destructivos', function () {
    DB::prohibitDestructiveCommands(true);

    $this->artisan('db:wipe', ['--force' => true])->assertFailed();

    DB::prohibitDestructiveCommands(false);
});
