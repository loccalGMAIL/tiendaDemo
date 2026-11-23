<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Etiquetas — {{ $business }}</title>
    <style>
        @page { size: {{ $layout->pageSize() }}; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Arial, sans-serif; color: #000; background: #fff; }
        .toolbar { padding: 1rem; font-size: 0.9rem; border-bottom: 1px solid #ddd; margin-bottom: 1rem; }
        .toolbar button { padding: 0.4rem 0.9rem; font-size: 0.9rem; cursor: pointer; }
        .toolbar p { margin: 0.5rem 0 0; color: #555; }
        .toolbar .warn { color: #b45309; }
        .page { break-after: page; page-break-after: always; }
        .page:last-child { break-after: auto; page-break-after: auto; }
        .roll .page { width: {{ $layout->width }}mm; height: {{ $layout->height }}mm; overflow: hidden; }
        .sheet .page {
            width: {{ $layout->paper === 'letter' ? '215.9mm' : '210mm' }};
            height: {{ $layout->paper === 'letter' ? '279.4mm' : '297mm' }};
            padding: {{ $layout->marginTop }}mm 0 0 {{ $layout->marginLeft }}mm;
            display: grid;
            grid-template-columns: repeat({{ $layout->columns }}, {{ $layout->width }}mm);
            grid-auto-rows: {{ $layout->height }}mm;
            column-gap: {{ $layout->gapX }}mm;
            row-gap: {{ $layout->gapY }}mm;
            align-content: start;
            overflow: hidden;
        }
        .label {
            width: {{ $layout->width }}mm; height: {{ $layout->height }}mm; padding: {{ $padding }}mm;
            overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; font-size: {{ $fontPt }}pt; line-height: 1.15;
        }
        .label .business { font-size: 0.8em; color: #333; }
        .label .name { font-weight: 700; max-width: 100%; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .label .variant { max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label .price { font-weight: 700; font-size: 1.15em; }
        .label svg { display: block; margin: 0.4mm 0; max-width: 100%; }
        .label .code { font-family: ui-monospace, Consolas, monospace; font-size: 0.85em; letter-spacing: 0.04em; }
        @media screen {
            .page { margin: 0 auto 1rem; background: #fff; box-shadow: 0 0 0 1px #ccc; }
            .sheet .label { outline: 1px dashed #ddd; }
        }
        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body class="{{ $layout->isSheet() ? 'sheet' : 'roll' }}">
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir {{ $total }} {{ $total === 1 ? 'etiqueta' : 'etiquetas' }}</button>
        <p>En el diálogo de impresión elegí la impresora de etiquetas, el tamaño de papel {{ $layout->isSheet() ? ($layout->paper === 'letter' ? 'Carta' : 'A4') : $layout->width.' × '.$layout->height.' mm' }}, escala 100 % y sin márgenes.</p>
        @if ($withoutCode !== [])
            <p class="warn">Sin código (no se imprimen): {{ implode(', ', $withoutCode) }}. Asignales un SKU desde el producto.</p>
        @endif
        @if ($total === 0)
            <p class="warn">No hay etiquetas para imprimir.</p>
        @endif
    </div>

    @foreach ($pages as $page)
        <div class="page">
            @foreach ($page as $label)
                @if ($label === null)
                    <div class="label"></div>
                @else
                    <div class="label">
                        @if ($layout->showBusiness)
                            <div class="business">{{ $business }}</div>
                        @endif
                        <div class="name">{{ $label['product'] }}</div>
                        @if ($label['variant'])
                            <div class="variant">{{ $label['variant'] }}</div>
                        @endif
                        @if ($label['price'])
                            <div class="price">{{ $label['price'] }}</div>
                        @endif
                        {!! $label['svg'] !!}
                        <div class="code">{{ $label['code'] }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
</body>
</html>
