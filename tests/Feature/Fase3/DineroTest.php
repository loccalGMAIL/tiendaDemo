<?php

use App\Services\Money;
use App\Services\SaleCalculator;

it('redondea a centavos con la mitad hacia afuera del cero', function (string $value, string $expected) {
    expect(Money::round($value))->toBe($expected);
})->with([
    ['0.4995', '0.50'],
    ['0.4949', '0.49'],
    ['0.005', '0.01'],
    ['0.004', '0.00'],
    ['12.5', '12.50'],
    ['-10.005', '-10.01'],
    ['-10.004', '-10.00'],
    ['-0.004', '0.00'],
    ['1000', '1000.00'],
]);

it('normaliza cualquier número a dos decimales', function () {
    expect(Money::from(null))->toBe('0.00')
        ->and(Money::from(''))->toBe('0.00')
        ->and(Money::from(12.5))->toBe('12.50')
        ->and(Money::from('99.999'))->toBe('100.00')
        ->and(Money::from(7))->toBe('7.00');
});

it('suma, resta y compara sin perder centavos', function () {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::sub('1000.00', '0.01'))->toBe('999.99')
        ->and(Money::sum(['0.10', '0.20', '0.30']))->toBe('0.60')
        ->and(Money::sum([]))->toBe('0.00')
        ->and(Money::compare('10.00', '10.001'))->toBe(0)
        ->and(Money::compare('10.01', '10.00'))->toBe(1)
        ->and(Money::isNegative('-0.01'))->toBeTrue()
        ->and(Money::isNegative('0.00'))->toBeFalse();
});

it('multiplica por cantidad y calcula porcentajes con redondeo a centavos', function () {
    expect(Money::mul('33.33', 3))->toBe('99.99')
        ->and(Money::percentOf('3.33', '15'))->toBe('0.50')
        ->and(Money::percentOf('100.05', '-10'))->toBe('-10.01')
        ->and(Money::percentOf('1000.00', '-10.00'))->toBe('-100.00')
        ->and(Money::percentOf('1000.00', '0'))->toBe('0.00');
});

it('calcula el subtotal de los ítems', function () {
    $calculator = new SaleCalculator;

    expect($calculator->lineSubtotal(3, '33.33'))->toBe('99.99')
        ->and($calculator->subtotal([
            ['quantity' => 2, 'unit_price' => '1000.00'],
            ['quantity' => 1, 'unit_price' => '500.50'],
        ]))->toBe('2500.50')
        ->and($calculator->subtotal([]))->toBe('0.00');
});

it('aplica el recargo de la tarjeta sobre el subtotal', function () {
    $result = (new SaleCalculator)->calculate(
        [['quantity' => 2, 'unit_price' => '1000.00'], ['quantity' => 1, 'unit_price' => '500.50']],
        [['adjustment_percent' => '15.00']],
    );

    // 2500.50 × 15 % = 375.075 → 375.08
    expect($result['subtotal'])->toBe('2500.50')
        ->and($result['adjustment'])->toBe('375.08')
        ->and($result['total'])->toBe('2875.58')
        ->and($result['payments'])->toBe([
            ['base_amount' => '2500.50', 'adjustment_amount' => '375.08', 'amount' => '2875.58'],
        ])
        ->and($result['balanced'])->toBeTrue();
});

it('aplica el descuento del efectivo', function () {
    $result = (new SaleCalculator)->calculate(
        [['quantity' => 1, 'unit_price' => '100.05']],
        [['adjustment_percent' => '-10.00']],
    );

    expect($result['adjustment'])->toBe('-10.01')
        ->and($result['total'])->toBe('90.04');
});

it('combina varios medios de pago, cada uno con su ajuste', function () {
    $result = (new SaleCalculator)->calculate(
        [['quantity' => 1, 'unit_price' => '2500.50']],
        [
            ['adjustment_percent' => '-10.00', 'base_amount' => '1000.00'],
            ['adjustment_percent' => '30.00', 'base_amount' => '1500.50'],
        ],
    );

    // −100.00 y +450.15
    expect($result['adjustment'])->toBe('350.15')
        ->and($result['total'])->toBe('2850.65')
        ->and($result['payments'][0]['amount'])->toBe('900.00')
        ->and($result['payments'][1]['amount'])->toBe('1950.65')
        ->and($result['balanced'])->toBeTrue();
});

it('detecta cuando la suma de las bases no da el subtotal', function () {
    $calculator = new SaleCalculator;
    $items = [['quantity' => 1, 'unit_price' => '2000.00']];

    $short = $calculator->calculate($items, [
        ['adjustment_percent' => '0', 'base_amount' => '1000.00'],
        ['adjustment_percent' => '0', 'base_amount' => '999.99'],
    ]);
    $over = $calculator->calculate($items, [
        ['adjustment_percent' => '0', 'base_amount' => '1000.00'],
        ['adjustment_percent' => '0', 'base_amount' => '1000.01'],
    ]);
    $none = $calculator->calculate($items, []);

    expect($short['balanced'])->toBeFalse()
        ->and($over['balanced'])->toBeFalse()
        ->and($none['balanced'])->toBeFalse();
});

it('con un solo cobro sin base indicada, la base es el subtotal', function () {
    $result = (new SaleCalculator)->calculate(
        [['quantity' => 3, 'unit_price' => '10.00']],
        [['adjustment_percent' => '0.00', 'base_amount' => null]],
    );

    expect($result['payments'][0]['base_amount'])->toBe('30.00')
        ->and($result['balanced'])->toBeTrue();
});
