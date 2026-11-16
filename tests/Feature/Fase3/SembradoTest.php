<?php

use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PaymentMethodSeeder;

it('carga los seis medios de pago iniciales con su recargo o descuento', function () {
    $this->seed(PaymentMethodSeeder::class);

    $methods = PaymentMethod::query()->orderBy('position')->get();

    expect($methods->pluck('name')->all())->toBe([
        'Efectivo', 'Transferencia', 'Débito', 'Tarjeta 1 cuota', 'Tarjeta 3 cuotas', 'Tarjeta 6 cuotas',
    ])
        ->and($methods->pluck('adjustment_percent')->all())->toBe(['-10.00', '0.00', '0.00', '0.00', '15.00', '30.00'])
        ->and($methods->pluck('installments')->all())->toBe([1, 1, 1, 1, 3, 6])
        ->and($methods->every(fn (PaymentMethod $m) => $m->active))->toBeTrue();
});

it('se puede correr dos veces sin duplicar', function () {
    $this->seed(PaymentMethodSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    expect(PaymentMethod::count())->toBe(6);
});

it('no pisa lo que el administrador cambió desde el panel', function () {
    $this->seed(PaymentMethodSeeder::class);

    PaymentMethod::where('name', 'Tarjeta 3 cuotas')->update(['adjustment_percent' => '20.00', 'active' => false]);

    $this->seed(PaymentMethodSeeder::class);

    $method = PaymentMethod::where('name', 'Tarjeta 3 cuotas')->firstOrFail();
    expect($method->adjustment_percent)->toBe('20.00')
        ->and($method->active)->toBeFalse()
        ->and(PaymentMethod::count())->toBe(6);
});

it('el seeder general crea usuarios y medios de pago', function () {
    config()->set('platform.admin', ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'clave-1']);
    config()->set('platform.superadmin', ['name' => null, 'email' => null, 'password' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'dueno@example.com')->exists())->toBeTrue()
        ->and(PaymentMethod::count())->toBe(6);
});
