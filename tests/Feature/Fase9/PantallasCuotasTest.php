<?php

use App\Actions\Sales\CreateSale as CreateSaleAction;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\FinancingPlans\Pages\CreateFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\EditFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\ListFinancingPlans;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Widgets\InstallmentsOverview;
use App\Filament\Widgets\ProjectedIncomeChart;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::CurrentAccount);
    Modules::enable(Module::Installments);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->panel = 'http://'.config('domains.panel');
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->customer = Customer::factory()->create(['name' => 'Marta Cliente']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;

    $this->plan = FinancingPlan::factory()->of(6, '12.00')->create(['name' => 'Seis cuotas']);
});

function screenPlanSale(?FinancingPlan $plan = null): Sale
{
    $test = test();

    return app(CreateSaleAction::class)->handle($test->admin, [
        'customer_id' => $test->customer->id,
        'date' => '2026-11-16',
        'financing_plan_id' => ($plan ?? $test->plan)->id,
        'first_month' => '2026-12',
        'items' => [['variant_id' => $test->variant->id, 'quantity' => 10]],
    ]);
}

// --- Planes de financiación ---------------------------------------------------------------------

it('el administrador ve los planes y el vendedor no', function (string $path) {
    $this->actingAs($this->admin)->get($this->panel.$path)->assertOk();
    $this->actingAs($this->seller)->get($this->panel.$path)->assertForbidden();
})->with(['/financing-plans', '/financing-plans/create']);

it('con Cuotas propias apagado los planes no existen', function (string $path) {
    Modules::disable(Module::Installments);

    $this->actingAs($this->admin)->get($this->panel.$path)->assertNotFound();
})->with(['/financing-plans', '/financing-plans/create']);

it('crea, lista y edita un plan', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateFinancingPlan::class)
        ->fillForm(['name' => 'Plan Navidad', 'installments_count' => 12, 'surcharge_percent' => '25.50', 'active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = FinancingPlan::query()->where('name', 'Plan Navidad')->firstOrFail();

    expect($plan->installments_count)->toBe(12)->and($plan->surcharge_percent)->toBe('25.50');

    Livewire::test(ListFinancingPlans::class)->assertCanSeeTableRecords([$this->plan, $plan]);

    Livewire::test(EditFinancingPlan::class, ['record' => $plan->getKey()])
        ->fillForm(['surcharge_percent' => '0'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->refresh()->surcharge_percent)->toBe('0.00');
});

it('el plan exige entre 2 y 60 cuotas y un recargo de 0 a 100', function (array $data, string $field) {
    $this->actingAs($this->admin);

    Livewire::test(CreateFinancingPlan::class)
        ->fillForm(['name' => 'Plan', 'installments_count' => 3, 'surcharge_percent' => '0', ...$data])
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(FinancingPlan::count())->toBe(1);
})->with([
    'una sola cuota' => [['installments_count' => 1], 'installments_count'],
    'demasiadas cuotas' => [['installments_count' => 61], 'installments_count'],
    'recargo negativo' => [['surcharge_percent' => '-1'], 'surcharge_percent'],
    'recargo mayor a 100' => [['surcharge_percent' => '101'], 'surcharge_percent'],
    'sin nombre' => [['name' => ''], 'name'],
]);

it('un plan usado en ventas no se borra y uno sin uso sí', function () {
    screenPlanSale();

    expect($this->admin->can('delete', $this->plan))->toBeFalse()
        ->and($this->admin->can('delete', FinancingPlan::factory()->create()))->toBeTrue()
        ->and($this->seller->can('delete', FinancingPlan::factory()->create()))->toBeFalse()
        ->and($this->seller->can('viewAny', FinancingPlan::class))->toBeFalse();
});

// --- Formulario de venta -------------------------------------------------------------------------

it('el formulario ofrece el plan con su cronograma en vivo y crea la venta en cuotas', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'date' => '2026-11-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 10]],
            'payments' => [],
            'financing_plan_id' => $this->plan->id,
            'first_month' => '2026-12',
        ])
        // 10.000 a financiar + 12 % = 11.200 en 6 cuotas (la última, 1.866,70).
        ->assertSee('A financiar')
        ->assertSee('11.200,00')
        ->assertSee('6 cuotas:')
        ->assertSee('1. 10/12/2026')
        ->assertSee('6. 10/05/2027')
        ->assertSee('1.866,70')
        ->assertFormFieldIsHidden('due_date')
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->financing_plan_id)->toBe($this->plan->id)
        ->and($sale->financed_amount)->toBe('11200.00')
        ->and($sale->installments)->toHaveCount(6);
});

it('sin elegir plan el formulario sigue pidiendo el vencimiento único', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'date' => '2026-11-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
            'payments' => [],
            'financing_plan_id' => null,
            'due_date' => '2027-01-20',
        ])
        ->assertFormFieldIsVisible('due_date')
        ->assertFormFieldIsHidden('first_month')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sale::query()->firstOrFail()->installments)->toHaveCount(1);
});

it('con Cuotas propias apagado el formulario no ofrece planes', function () {
    Modules::disable(Module::Installments);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'date' => '2026-11-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
            'payments' => [],
        ])
        ->assertFormFieldIsHidden('financing_plan_id')
        ->assertFormFieldIsVisible('due_date');
});

it('una venta cobrada entera no ofrece plan', function () {
    $method = PaymentMethod::factory()->create(['adjustment_percent' => '0.00']);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'date' => '2026-11-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
            // Cobra todo con un medio de pago: no queda saldo, no hay plan que elegir.
            'payments' => [['payment_method_id' => $method->id]],
        ])
        ->assertFormFieldIsHidden('financing_plan_id');
});

it('con la regla de «mismo día» el formulario no pide el mes de la primera cuota', function () {
    Modules::updateSettings(Module::Installments, ['due_rule' => 'same_day']);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'date' => '2026-01-31',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
            'payments' => [],
            'financing_plan_id' => $this->plan->id,
        ])
        ->assertFormFieldIsHidden('first_month')
        ->assertSee('1. 28/02/2026');
});

// --- La venta ya hecha ---------------------------------------------------------------------------

it('la vista de la venta muestra el plan y el cronograma de cuotas', function () {
    $sale = screenPlanSale();
    $this->actingAs($this->admin);

    Livewire::test(ViewSale::class, ['record' => $sale->getKey()])
        ->assertSee('Plan de pago')
        ->assertSee('6 cuotas')
        ->assertSee('recargo')
        ->assertSee('1.866,66')
        ->assertSee('Cuotas');
});

// --- Escritorio ----------------------------------------------------------------------------------

it('el escritorio muestra la ganancia futura y lo que vence el mes próximo', function () {
    screenPlanSale();
    $this->actingAs($this->admin);

    // Ganancia futura: 11.200 a cobrar − 6.000 de costo = 5.200 (incluye el interés).
    Livewire::test(InstallmentsOverview::class)
        ->assertSee('Ganancia futura')
        ->assertSee('5.200,00')
        ->assertSee('Vence el mes próximo')
        ->assertSee('1.866,66')
        ->assertSee('1 cuota');
});

it('el gráfico proyecta 12 meses por vencimiento y suma lo vencido al mes actual', function () {
    screenPlanSale();

    // Una venta a cuenta con un único vencimiento que ya venció.
    app(CreateSaleAction::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'date' => '2026-10-01',
        'due_date' => '2026-10-20',
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]],
    ]);

    $this->actingAs($this->admin);

    $widget = Livewire::test(ProjectedIncomeChart::class)->assertSee('Ingresos proyectados')->instance();
    $data = Closure::bind(fn (): array => $this->getData(), $widget, $widget::class)();

    expect($data['labels'])->toHaveCount(12)
        ->and($data['labels'][0])->toBe('Nov 2026')
        ->and($data['labels'][11])->toBe('Oct 2027')
        // Noviembre: lo vencido (2.000) cae en el mes actual; diciembre a mayo: las cuotas.
        ->and($data['datasets'][0]['label'])->toBe('Por cobrar')
        ->and($data['datasets'][0]['data'])->toBe([2000.0, 1866.66, 1866.66, 1866.66, 1866.66, 1866.66, 1866.7, 0.0, 0.0, 0.0, 0.0, 0.0])
        // Ganancia: lo vencido deja 2.000 − 1.200 = 800; cada cuota deja su monto menos 1.000 de costo.
        ->and($data['datasets'][1]['label'])->toBe('Ganancia')
        ->and($data['datasets'][1]['data'])->toBe([800.0, 866.66, 866.66, 866.66, 866.66, 866.66, 866.7, 0.0, 0.0, 0.0, 0.0, 0.0]);
});

it('la ganancia futura baja a medida que se cobra', function () {
    $sale = screenPlanSale();

    $first = $sale->installments()->firstOrFail();

    expect($first->futureProfit())->toBe('866.66');

    $first->update(['paid_amount' => '933.33']);

    // Cobrada la mitad, queda la mitad de la ganancia.
    expect($first->refresh()->futureProfit())->toBe('433.33');

    $first->update(['paid_amount' => '1866.66']);

    expect($first->refresh()->futureProfit())->toBe('0.00')
        ->and(Installment::query()->count())->toBe(6);
});

it('los widgets de Cuotas son del administrador y del módulo encendido', function () {
    $this->actingAs($this->seller);

    expect(InstallmentsOverview::canView())->toBeFalse()->and(ProjectedIncomeChart::canView())->toBeFalse();

    $this->actingAs($this->admin);

    expect(InstallmentsOverview::canView())->toBeTrue()->and(ProjectedIncomeChart::canView())->toBeTrue();

    Modules::disable(Module::Installments);

    expect(InstallmentsOverview::canView())->toBeFalse()->and(ProjectedIncomeChart::canView())->toBeFalse();
});
