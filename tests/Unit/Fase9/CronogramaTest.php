<?php

use App\Services\InstallmentSchedule;
use Illuminate\Support\Carbon;

function cronograma(): InstallmentSchedule
{
    return new InstallmentSchedule;
}

function fechas(array $rows): array
{
    return array_map(fn (array $row): string => $row['due_date']->toDateString(), $rows);
}

// --- Cuotas -------------------------------------------------------------------------------

it('la cuota se redondea hacia abajo y la última absorbe la diferencia', function () {
    $rows = cronograma()->build('1000.00', 3, Carbon::parse('2026-12-10'), 10, '0.00');

    expect(array_column($rows, 'amount'))->toBe(['333.33', '333.33', '333.34']);

    $rows = cronograma()->build('100.00', 7, Carbon::parse('2026-12-10'), 10, '0.00');

    expect(array_column($rows, 'amount'))->toBe(['14.28', '14.28', '14.28', '14.28', '14.28', '14.28', '14.32']);
});

it('las cuotas suman exactamente lo financiado', function (string $financed, int $count) {
    $rows = cronograma()->build($financed, $count, Carbon::parse('2026-12-10'), 10, '0.00');

    expect(bcadd('0', array_reduce(array_column($rows, 'amount'), fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00'), 2))
        ->toBe($financed)
        ->and($rows)->toHaveCount($count)
        ->and(array_column($rows, 'number'))->toBe(range(1, $count));
})->with([
    ['1000.00', 3],
    ['100.00', 7],
    ['9999.99', 12],
    ['0.10', 3],
    ['5000.00', 1],
]);

it('con una sola cuota queda entera', function () {
    $rows = cronograma()->build('5000.00', 1, Carbon::parse('2026-12-31'), 31, '3000.00');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['amount'])->toBe('5000.00')
        ->and($rows[0]['cost_portion'])->toBe('3000.00')
        ->and($rows[0]['due_date']->toDateString())->toBe('2026-12-31');
});

it('el recargo se calcula sobre lo que queda a cuenta', function () {
    expect(cronograma()->surcharge('6000.00', '12.00'))->toBe('720.00')
        ->and(cronograma()->surcharge('1000.00', '0.00'))->toBe('0.00')
        ->and(cronograma()->surcharge('333.33', '15.00'))->toBe('50.00');
});

// --- Porción de costo ----------------------------------------------------------------------

it('el costo se reparte en proporción a las cuotas y la última absorbe el redondeo', function () {
    $rows = cronograma()->build('1000.00', 3, Carbon::parse('2026-12-10'), 10, '100.00');

    expect(array_column($rows, 'cost_portion'))->toBe(['33.33', '33.33', '33.34']);
});

it('la suma de las porciones de costo es exactamente el costo repartido', function (string $financed, int $count, string $cost) {
    $rows = cronograma()->build($financed, $count, Carbon::parse('2026-12-10'), 10, $cost);

    $sum = array_reduce(array_column($rows, 'cost_portion'), fn (string $carry, string $portion): string => bcadd($carry, $portion, 2), '0.00');

    expect($sum)->toBe($cost);
})->with([
    ['11200.00', 6, '6000.00'],
    ['1000.00', 3, '100.00'],
    ['9999.99', 12, '4321.09'],
    ['100.00', 7, '33.33'],
]);

it('la porción de costo financiada es la parte del costo que corresponde a lo financiado', function () {
    // Venta de 10.000 con costo 6.000, de la que 6.000 quedan a cuenta: el costo financiado es 3.600.
    expect(cronograma()->financedCost('6000.00', '6000.00', '10000.00'))->toBe('3600.00')
        ->and(cronograma()->financedCost('6000.00', '0.00', '10000.00'))->toBe('0.00')
        ->and(cronograma()->financedCost('6000.00', '10000.00', '10000.00'))->toBe('6000.00')
        ->and(cronograma()->financedCost('6000.00', '100.00', '0.00'))->toBe('0.00');
});

// --- Vencimientos con día fijo ----------------------------------------------------------------

it('con día fijo la primera cuota es el mes siguiente a la venta, o el mes elegido', function () {
    $sale = Carbon::parse('2026-11-16');

    expect(cronograma()->firstDueDate($sale, 'fixed_day', 10)->toDateString())->toBe('2026-12-10')
        ->and(cronograma()->firstDueDate($sale, 'fixed_day', 10, '2027-02')->toDateString())->toBe('2027-02-10')
        // El mes de la propia venta también se puede elegir.
        ->and(cronograma()->firstDueDate($sale, 'fixed_day', 28, '2026-11')->toDateString())->toBe('2026-11-28')
        // Un mes inválido vuelve al siguiente.
        ->and(cronograma()->firstDueDate($sale, 'fixed_day', 10, '2027-13')->toDateString())->toBe('2026-12-10')
        ->and(cronograma()->firstDueDate($sale, 'fixed_day', 10, 'cualquier cosa')->toDateString())->toBe('2026-12-10');
});

it('los vencimientos con día fijo son mensuales, también al cambiar de año y en febrero', function () {
    $first = cronograma()->firstDueDate(Carbon::parse('2026-11-16'), 'fixed_day', 10);
    $rows = cronograma()->build('3000.00', 4, $first, 10, '0.00');

    expect(fechas($rows))->toBe(['2026-12-10', '2027-01-10', '2027-02-10', '2027-03-10']);

    // El día 28 existe en todos los meses, incluido febrero.
    $rows = cronograma()->build('3000.00', 3, Carbon::parse('2027-01-28'), 28, '0.00');

    expect(fechas($rows))->toBe(['2027-01-28', '2027-02-28', '2027-03-28']);
});

// --- Vencimientos el mismo día de la venta --------------------------------------------------------

it('con el mismo día de la venta la primera cuota vence un mes después', function () {
    expect(cronograma()->firstDueDate(Carbon::parse('2026-11-16'), 'same_day', 10)->toDateString())->toBe('2026-12-16')
        // El mes elegido se ignora con esta regla.
        ->and(cronograma()->firstDueDate(Carbon::parse('2026-11-16'), 'same_day', 10, '2027-05')->toDateString())->toBe('2026-12-16');
});

it('en los meses más cortos vence el último día y después vuelve al día de la venta', function () {
    $sale = Carbon::parse('2026-01-31');
    $first = cronograma()->firstDueDate($sale, 'same_day', 10);

    expect($first->toDateString())->toBe('2026-02-28');

    $rows = cronograma()->build('4000.00', 4, $first, cronograma()->anchorDay($sale, 'same_day', 10), '0.00');

    expect(fechas($rows))->toBe(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31']);

    // Año bisiesto.
    expect(cronograma()->firstDueDate(Carbon::parse('2024-01-31'), 'same_day', 10)->toDateString())->toBe('2024-02-29');
});

it('el día de anclaje es el fijo o el de la venta según la regla', function () {
    expect(cronograma()->anchorDay(Carbon::parse('2026-11-16'), 'fixed_day', 10))->toBe(10)
        ->and(cronograma()->anchorDay(Carbon::parse('2026-11-16'), 'same_day', 10))->toBe(16);
});

it('reconoce los meses válidos AAAA-MM', function () {
    expect(cronograma()->validMonth('2026-12'))->toBeTrue()
        ->and(cronograma()->validMonth('2026-1'))->toBeFalse()
        ->and(cronograma()->validMonth('2026-13'))->toBeFalse()
        ->and(cronograma()->validMonth('2026-00'))->toBeFalse()
        ->and(cronograma()->validMonth(''))->toBeFalse()
        ->and(cronograma()->validMonth(null))->toBeFalse();
});
