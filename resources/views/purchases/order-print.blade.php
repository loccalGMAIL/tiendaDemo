<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Orden de compra {{ $order->number() }} — {{ $business }}</title>
    @include('reports._styles')
    <style>
        body { font-family: system-ui, sans-serif; color: #111; background: #fff; margin: 0; padding: 1.5rem; max-width: 52rem; }
        header { margin-bottom: 1rem; }
        header h1 { margin: 0; font-size: 1.35rem; }
        header p { margin: 0.15rem 0 0; color: #555; font-size: 0.85rem; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 0.25rem 1.5rem; margin: 1rem 0 1.5rem; font-size: 0.9rem; }
        .meta strong { display: inline-block; min-width: 6.5rem; }
        .notes { margin-top: 1.25rem; font-size: 0.9rem; white-space: pre-line; }
        .actions { margin-bottom: 1rem; }
        .actions button { padding: 0.4rem 0.9rem; font-size: 0.9rem; cursor: pointer; }
        .report-table { font-size: 0.9rem; }
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
        <h1>Orden de compra {{ $order->number() }}</h1>
        <p>{{ $business }} · Impresa el {{ $printedAt }}</p>
    </header>

    <div class="meta">
        <div><strong>Proveedor</strong> {{ $order->supplier->name }}</div>
        <div><strong>Fecha</strong> {{ $order->date->format('d/m/Y') }}</div>
        @if ($order->supplier->tax_id)
            <div><strong>CUIT</strong> {{ $order->supplier->tax_id }}</div>
        @endif
        <div><strong>Estado</strong> {{ $order->status->getLabel() }}</div>
        @if ($order->supplier->phone)
            <div><strong>Teléfono</strong> {{ $order->supplier->phone }}</div>
        @endif
        @if ($order->supplier->email)
            <div><strong>Correo</strong> {{ $order->supplier->email }}</div>
        @endif
        @if ($order->supplier->address)
            <div><strong>Dirección</strong> {{ $order->supplier->address }}</div>
        @endif
    </div>

    <div class="report-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Código</th>
                    <th class="num">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->variant->fullName() }}</td>
                        <td>{{ $item->variant->sku ?? $item->product->sku }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total de unidades</td>
                    <td class="num">{{ $order->items->sum('quantity') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if (filled($order->notes))
        <p class="notes"><strong>Notas:</strong> {{ $order->notes }}</p>
    @endif
</body>
</html>
