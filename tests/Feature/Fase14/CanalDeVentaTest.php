<?php

use App\Actions\Sales\CreateSale as CreateSaleAction;
use App\Enums\SaleChannel;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\SalesReport;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->variant = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera lisa'])->implicitVariant;
    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo']);
});

it('«Nueva venta» propone el canal online y lo guarda', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->assertSchemaStateSet(['channel' => SaleChannel::Online])
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $this->cash->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sale::query()->sole()->channel)->toBe(SaleChannel::Online);
});

it('«Nueva venta» también registra ventas de mostrador si se elige ese canal', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'channel' => SaleChannel::Counter->value,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $this->cash->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sale::query()->sole()->channel)->toBe(SaleChannel::Counter);
});

it('sin canal la venta es de mostrador y un canal desconocido se rechaza', function () {
    expect(sellVariant($this->admin, $this->variant)->channel)->toBe(SaleChannel::Counter);

    expect(fn () => app(CreateSaleAction::class)->handle($this->admin, [
        'channel' => 'telefono',
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $this->cash->id]],
    ]))->toThrow(ValidationException::class, 'El canal de venta no es válido.');

    expect(Sale::query()->count())->toBe(1);
});

it('el listado de ventas filtra por canal', function () {
    $counter = Sale::factory()->create();
    $online = Sale::factory()->online()->create();

    Livewire::actingAs($this->admin)
        ->test(ListSales::class)
        ->filterTable('channel', SaleChannel::Online->value)
        ->assertCanSeeTableRecords([$online])
        ->assertCanNotSeeTableRecords([$counter]);
});

it('el informe de ventas filtra y agrupa por canal', function () {
    Sale::factory()->totalling('1000.00', '600.00')->create();
    Sale::factory()->totalling('500.00', '200.00')->create();
    Sale::factory()->online()->totalling('2000.00', '1500.00')->create();

    $byChannel = app(SalesReport::class)->run(['group' => 'channel']);

    expect(array_map(fn (array $row): array => [$row['label'], $row['count'], $row['total']], $byChannel['rows']))
        ->toBe([['Mostrador', '2', '1500.00'], ['Online', '1', '2000.00']]);

    $onlyOnline = app(SalesReport::class)->run(['channel' => 'online']);

    expect($onlyOnline['rows'])->toHaveCount(1)
        ->and($onlyOnline['totals']['total'])->toBe('2000.00');
});
