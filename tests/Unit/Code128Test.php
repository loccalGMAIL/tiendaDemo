<?php

use App\Services\Barcodes\BarcodeSvg;
use App\Services\Barcodes\Code128;

it('cada símbolo mide 11 módulos y el de fin 13', function () {
    $encoder = new Code128;

    foreach (['A', 'PROD-00012-01', '1234567890', 'ab12345cd', ' ~'] as $text) {
        $widths = $encoder->widths($text);

        expect(array_sum($widths))->toBe(11 * (count($encoder->values($text)) - 1) + 13);
    }
});

it('codifica en set B con el dígito de control correcto', function () {
    // Caso de Wikipedia (PJJ123C): en set B el control es 55.
    expect((new Code128)->values('PJJ123C'))->toBe([104, 48, 42, 42, 17, 18, 19, 35, 55, 106]);
});

it('pasa al set C en tramos de 4 o más dígitos', function () {
    // Empieza en C: 12 34 56 → 105, 12, 34, 56; control = (105 + 12 + 68 + 168) % 103 = 44.
    expect((new Code128)->values('123456'))->toBe([105, 12, 34, 56, 44, 106]);
});

it('con una cantidad impar de dígitos al inicio, el primero va en set B', function () {
    // B: '1' (17) → C: 23 45 → control = (104 + 17 + 99*2 + 23*3 + 45*4) % 103 = 53.
    expect((new Code128)->values('12345'))->toBe([104, 17, 99, 23, 45, 53, 106]);
});

it('vuelve al set B cuando termina el tramo de dígitos', function () {
    expect((new Code128)->values('A1234B'))->toBe([104, 33, 99, 12, 34, 100, 34, 78, 106]);
});

it('rechaza texto vacío o con caracteres que no son ASCII imprimible', function () {
    expect(Code128::canEncode(''))->toBeFalse()
        ->and(Code128::canEncode('Camión'))->toBeFalse()
        ->and(Code128::canEncode("a\tb"))->toBeFalse()
        ->and(Code128::canEncode('PROD-00012-01'))->toBeTrue();

    (new Code128)->values('Camión');
})->throws(InvalidArgumentException::class);

it('dibuja un SVG que entra en el ancho disponible', function () {
    $svg = (new BarcodeSvg)->render('PROD-00012-01', 10, 46);

    expect($svg)->toContain('<svg')->toContain('shape-rendering="crispEdges"')->toContain('height="10mm"');

    preg_match('/ width="([\d.]+)mm"/', $svg, $match);

    expect((float) $match[1])->toBeLessThanOrEqual(46.0);
});
