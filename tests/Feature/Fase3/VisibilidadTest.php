<?php

use App\Actions\Sales\CreateSale;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->otherSeller = User::factory()->seller()->create();
    $this->panel = 'http://'.config('domains.panel');

    $product = Product::factory()->priced('1000.00', '600.00')->create();
    $method = PaymentMethod::factory()->create();

    $make = fn (User $actor) => app(CreateSale::class)->handle($actor, [
        'items' => [['variant_id' => $product->implicitVariant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $method->id]],
    ]);

    $this->mine = $make($this->seller);
    $this->theirs = $make($this->otherSeller);
});

it('el vendedor ve solo sus ventas', function () {
    $this->actingAs($this->seller);

    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$this->mine])
        ->assertCanNotSeeTableRecords([$this->theirs]);
});

it('el administrador ve todas las ventas', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListSales::class)->assertCanSeeTableRecords([$this->mine, $this->theirs]);
});

it('con SELLER_SEES_ALL_SALES el vendedor ve todas', function () {
    config()->set('platform.sales.seller_sees_all', true);
    $this->actingAs($this->seller);

    Livewire::test(ListSales::class)->assertCanSeeTableRecords([$this->mine, $this->theirs]);

    $this->get($this->panel.'/sales/'.$this->theirs->id)->assertOk();
});

it('el vendedor no abre una venta ajena: responde 404', function () {
    $this->actingAs($this->seller);

    $this->get($this->panel.'/sales/'.$this->mine->id)->assertOk();
    $this->get($this->panel.'/sales/'.$this->theirs->id)->assertNotFound();
});

it('el filtro de vendedor no muestra al SuperAdmin', function () {
    User::factory()->superadmin()->create(['name' => 'Zeta Super']);
    $this->actingAs($this->admin);

    $options = Livewire::test(ListSales::class)
        ->instance()
        ->getTable()
        ->getFilter('seller_id')
        ->getOptions();

    expect($options)->toHaveKey($this->seller->id)->not->toContain('Zeta Super');
});

it('se puede buscar por el número de venta', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListSales::class)
        ->searchTable($this->theirs->number())
        ->assertCanSeeTableRecords([$this->theirs])
        ->assertCanNotSeeTableRecords([$this->mine]);
});

it('filtra las ventas por estado y por vendedor', function () {
    $this->actingAs($this->admin);
    $cancelled = Sale::factory()->cancelled()->create(['seller_id' => $this->seller->id]);

    Livewire::test(ListSales::class)
        ->filterTable('status', SaleStatus::Cancelled->value)
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$this->mine, $this->theirs]);

    Livewire::test(ListSales::class)
        ->filterTable('seller_id', $this->otherSeller->id)
        ->assertCanSeeTableRecords([$this->theirs])
        ->assertCanNotSeeTableRecords([$this->mine]);
});

it('las ventas no se editan ni se borran desde el panel', function () {
    $this->actingAs($this->admin);

    $this->get($this->panel.'/sales/'.$this->mine->id.'/edit')->assertNotFound();

    Livewire::test(ListSales::class)
        ->assertTableActionExists('view')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

it('el vendedor no ve costos ni ganancia, el administrador sí', function () {
    $this->actingAs($this->seller);
    $this->get($this->panel.'/sales/'.$this->mine->id)
        ->assertOk()
        ->assertSee('Subtotal')
        ->assertDontSee('Costo')
        ->assertDontSee('Ganancia');

    $this->actingAs($this->admin);
    $this->get($this->panel.'/sales/'.$this->mine->id)
        ->assertOk()
        ->assertSee('Costo')
        ->assertSee('Ganancia');
});

it('el vendedor no ve el botón de anular; el administrador sí', function () {
    $this->actingAs($this->seller);
    Livewire::test(ViewSale::class, ['record' => $this->mine->id])->assertActionHidden('cancel');

    $this->actingAs($this->admin);
    Livewire::test(ViewSale::class, ['record' => $this->mine->id])->assertActionVisible('cancel');
});

it('el administrador anula desde la vista con motivo y la venta queda anulada', function () {
    $this->actingAs($this->admin);

    Livewire::test(ViewSale::class, ['record' => $this->mine->id])
        ->callAction('cancel', ['reason' => 'Error de carga'])
        ->assertNotified('Venta anulada');

    $sale = $this->mine->fresh();

    expect($sale->status)->toBe(SaleStatus::Cancelled)
        ->and($sale->cancellation_reason)->toBe('Error de carga')
        ->and($sale->cancelled_by)->toBe($this->admin->id)
        ->and($sale->payments()->whereNotNull('cancelled_at')->count())->toBe(1);

    $this->get($this->panel.'/sales/'.$sale->id)
        ->assertOk()
        ->assertSee('Anulada')
        ->assertSee('Error de carga');
});

it('el motivo de la anulación es obligatorio en el formulario', function () {
    $this->actingAs($this->admin);

    Livewire::test(ViewSale::class, ['record' => $this->mine->id])
        ->callAction('cancel', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->mine->fresh()->status)->toBe(SaleStatus::Paid);
});

it('una venta ya anulada no ofrece anular de nuevo', function () {
    $this->actingAs($this->admin);
    $cancelled = Sale::factory()->cancelled()->create();

    Livewire::test(ViewSale::class, ['record' => $cancelled->id])->assertActionHidden('cancel');
});
