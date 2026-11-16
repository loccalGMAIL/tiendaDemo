<?php

use App\Filament\Widgets\LatestSales;
use App\Filament\Widgets\SalesOverview;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->other = User::factory()->seller()->create();
});

function saleOn(string $date, string $total, ?User $seller = null, bool $cancelled = false): Sale
{
    $factory = Sale::factory()->totalling($total)->state(['date' => $date]);

    if ($seller) {
        $factory = $factory->state(['seller_id' => $seller->id]);
    }

    return ($cancelled ? $factory->cancelled() : $factory)->create();
}

function payOn(Sale $sale, string $amount, string $date, bool $cancelled = false): Payment
{
    return Payment::factory()->create([
        'sale_id' => $sale->id,
        'date' => $date,
        'base_amount' => $amount,
        'amount' => $amount,
        'cancelled_at' => $cancelled ? now() : null,
    ]);
}

it('suma las ventas del mes y las compara con el mes anterior', function () {
    saleOn('2026-11-02', '1000.00', $this->seller);
    saleOn('2026-11-15', '500.50', $this->seller);
    saleOn('2026-10-20', '1000.00', $this->seller);

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)
        ->assertSee('$ 1.500,50')
        ->assertSee('50,1 % más que el mes anterior')
        ->assertSee('$ 1.000,00');
});

it('indica cuando el mes anterior no tuvo ventas', function () {
    saleOn('2026-11-02', '1000.00', $this->seller);

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)->assertSee('Sin ventas el mes anterior');
});

it('muestra la baja cuando se vendió menos que el mes anterior', function () {
    saleOn('2026-11-02', '500.00', $this->seller);
    saleOn('2026-10-20', '1000.00', $this->seller);

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)->assertSee('50,0 % menos que el mes anterior');
});

it('excluye las ventas anuladas de los totales', function () {
    saleOn('2026-11-02', '1000.00', $this->seller);
    saleOn('2026-11-03', '9999.00', $this->seller, cancelled: true);

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)
        ->assertSee('$ 1.000,00')
        ->assertDontSee('9.999')
        ->assertDontSee('10.999');
});

it('suma lo cobrado del mes sin contar los cobros anulados', function () {
    $sale = saleOn('2026-11-02', '1000.00', $this->seller);
    payOn($sale, '1000.00', '2026-11-02');
    payOn($sale, '777.00', '2026-11-05', cancelled: true);
    payOn($sale, '555.00', '2026-10-30');

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)
        ->assertSee('Cobrado del mes')
        ->assertSee('$ 1.000,00')
        ->assertDontSee('777')
        ->assertDontSee('555');
});

it('el vendedor ve solo sus totales', function () {
    saleOn('2026-11-02', '1000.00', $this->seller);
    saleOn('2026-11-02', '4000.00', $this->other);

    $this->actingAs($this->seller);

    Livewire::test(SalesOverview::class)
        ->assertSee('$ 1.000,00')
        ->assertDontSee('5.000')
        ->assertDontSee('4.000');
});

it('con SELLER_SEES_ALL_SALES el vendedor ve los totales de todos', function () {
    config()->set('platform.sales.seller_sees_all', true);
    saleOn('2026-11-02', '1000.00', $this->seller);
    saleOn('2026-11-02', '4000.00', $this->other);

    $this->actingAs($this->seller);

    Livewire::test(SalesOverview::class)->assertSee('$ 5.000,00');
});

it('el mes se corta por la fecha de la venta', function () {
    saleOn('2026-10-31', '800.00', $this->seller);
    saleOn('2026-12-01', '900.00', $this->seller);

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)
        ->assertSee('$ 0,00')
        ->assertDontSee('900,00');
});

it('lista las últimas cinco ventas, las más nuevas primero', function () {
    $sales = collect(range(1, 7))->map(fn (int $i) => saleOn('2026-11-0'.$i, (string) ($i * 100).'.00', $this->seller));

    $this->actingAs($this->admin);

    Livewire::test(LatestSales::class)
        ->assertCanSeeTableRecords($sales->slice(2)->all(), inOrder: false)
        ->assertCanNotSeeTableRecords($sales->take(2)->all());
});

it('las últimas ventas del vendedor son solo las suyas', function () {
    $mine = saleOn('2026-11-02', '100.00', $this->seller);
    $theirs = saleOn('2026-11-03', '200.00', $this->other);

    $this->actingAs($this->seller);

    Livewire::test(LatestSales::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('el escritorio ofrece Nueva venta con enlace al formulario', function (string $role) {
    $this->withoutVite();
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertSee('Nueva venta')
        ->assertSee('/sales/create')
        ->assertSee('Últimas ventas')
        ->assertDontSee('Documentación');
})->with(['admin', 'seller']);
