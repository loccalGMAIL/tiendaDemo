<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — {{ $business }}</title>
    @include('reports._styles')
    <style>
        body { font-family: system-ui, sans-serif; color: #111; background: #fff; margin: 0; padding: 1.5rem; }
        header { margin-bottom: 1rem; }
        header h1 { margin: 0; font-size: 1.25rem; }
        header p { margin: 0.15rem 0 0; color: #555; font-size: 0.85rem; }
        .actions { margin-bottom: 1rem; }
        .actions button { padding: 0.4rem 0.9rem; font-size: 0.9rem; cursor: pointer; }
        @media print {
            .actions { display: none; }
            body { padding: 0; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>

    <header>
        <h1>{{ $title }}</h1>
        <p>{{ $business }} · Período: {{ $period }}</p>
        <p>Impreso el {{ $printedAt }}</p>
    </header>

    @include('reports._table', ['result' => $result])
</body>
</html>
