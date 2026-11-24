<?php

use App\Services\Tender;

it('lee los montos como se escriben en la caja', function (?string $typed, ?string $expected) {
    expect((new Tender)->parse($typed))->toBe($expected);
})->with([
    'entero' => ['20000', '20000.00'],
    'con punto de miles' => ['20.000', '20000.00'],
    'con miles y coma decimal' => ['1.234,50', '1234.50'],
    'con punto decimal' => ['1234.5', '1234.50'],
    'con signo y espacios' => ['$ 15 000', '15000.00'],
    'vacío' => ['', null],
    'nulo' => [null, null],
    'texto' => ['veinte', null],
    'negativo' => ['-100', null],
]);

it('calcula el vuelto y lo deja negativo si no alcanza', function () {
    $tender = new Tender;

    expect($tender->change('20000.00', '13500.00'))->toBe('6500.00')
        ->and($tender->change('10000.00', '13500.00'))->toBe('-3500.00');
});

it('sugiere el exacto y los billetes que alcanzan, sin repetir', function () {
    $tender = new Tender;

    expect($tender->suggestions('13500.00'))->toBe(['13500.00', '14000.00', '20000.00', '100000.00'])
        ->and($tender->suggestions('54000.00'))->toBe(['54000.00', '60000.00', '100000.00'])
        ->and($tender->suggestions('1800.00'))->toBe(['1800.00', '2000.00', '10000.00', '20000.00'])
        ->and($tender->suggestions('100000.00'))->toBe(['100000.00'])
        ->and($tender->suggestions('0.00'))->toBe([]);
});
