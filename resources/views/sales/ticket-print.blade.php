@php
    $money = fn (string $amount): string => \App\Services\Money::format($amount);
    $width = $settings['paper_width'];
    // Área que imprime cada rollo (el cabezal no llega a los bordes).
    $printable = $width === 58 ? 48 : 72;
    $isCancelled = $sale->isCancelled();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Ticket {{ $sale->number() }} — {{ $business }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body { margin: 0; color: #000; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; font-size: {{ $width === 58 ? '11px' : '12px' }}; line-height: 1.3; }
        .actions { display: flex; gap: 0.5rem; justify-content: center; padding: 1rem; }
        .actions button { padding: 0.4rem 0.9rem; font-size: 0.9rem; cursor: pointer; }
        .ticket { width: {{ $printable }}mm; margin: 0 auto 1rem; padding: 3mm 0; background: #fff; }
        .ticket + .ticket { break-before: page; }
        .center { text-align: center; }
        .business { font-size: 1.35em; font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row span:last-child { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .item { margin-bottom: 1mm; }
        .item-detail { padding-left: 2mm; }
        .total { font-size: 1.4em; font-weight: 700; }
        .strong { font-weight: 700; }
        .cancelled { border: 2px solid #000; padding: 1mm; font-size: 1.4em; font-weight: 700; text-align: center; letter-spacing: 0.2em; }
        .small { font-size: 0.9em; }
        @media screen { .ticket { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); padding: 4mm; width: {{ $printable + 8 }}mm; } }
        @media print {
            html { background: #fff; }
            .actions { display: none; }
            .ticket { margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>

    @foreach (range(1, $settings['copies']) as $copy)
        <div class="ticket">
            <div class="center">
                <div class="business">{{ $business }}</div>
                @foreach ($headerLines as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>

            <hr>

            @if ($isCancelled)
                <div class="cancelled">ANULADA</div>
                <hr>
            @endif

            <div class="row"><span class="strong">Venta {{ $sale->number() }}</span><span>{{ $sale->created_at?->format('d/m/Y H:i') ?? $sale->date->format('d/m/Y') }}</span></div>
            @if ($settings['show_seller'])
                <div>Vendedor: {{ $sale->seller->name }}</div>
            @endif
            @if ($settings['show_customer'])
                <div>Cliente: {{ $sale->customer->name ?? 'Consumidor final' }}</div>
            @endif

            <hr>

            @foreach ($sale->items as $item)
                <div class="item">
                    <div>{{ $item->description }}</div>
                    <div class="row item-detail">
                        <span>
                            {{ $item->quantity }} × {{ $money((string) $item->unit_price) }}
                            @if ($settings['show_codes'] && ($code = $item->variant?->sku ?? $item->variant?->product?->sku))
                                <span class="small">· {{ $code }}</span>
                            @endif
                        </span>
                        <span>{{ $money((string) $item->subtotal) }}</span>
                    </div>
                </div>
            @endforeach

            <hr>

            <div class="row"><span>Subtotal</span><span>{{ $money((string) $sale->subtotal) }}</span></div>
            @if (\App\Services\Money::compare((string) $sale->adjustment, '0') !== 0)
                <div class="row"><span>{{ \App\Services\Money::isNegative((string) $sale->adjustment) ? 'Descuentos' : 'Recargos' }}</span><span>{{ $money((string) $sale->adjustment) }}</span></div>
            @endif
            <div class="row total"><span>TOTAL</span><span>{{ $money((string) $sale->total) }}</span></div>

            @if ($payments->isNotEmpty())
                <hr>
                @foreach ($payments as $payment)
                    <div class="row">
                        <span>{{ $payment->method->name }}@if ($payment->installments > 1) ({{ $payment->installments }} cuotas)@endif</span>
                        <span>{{ $money((string) $payment->amount) }}</span>
                    </div>
                @endforeach
                @if ($tendered !== null && $change !== null)
                    <div class="row"><span>Paga con</span><span>{{ $money($tendered) }}</span></div>
                    <div class="row strong"><span>Vuelto</span><span>{{ $money($change) }}</span></div>
                @endif
            @endif

            @if (\App\Services\Money::compare((string) $sale->financed_amount, '0') > 0)
                <hr>
                <div class="row strong"><span>Saldo a cuenta</span><span>{{ $money((string) $sale->financed_amount) }}</span></div>
                @foreach ($sale->installments as $installment)
                    <div class="row small">
                        <span>{{ $sale->installments->count() > 1 ? 'Cuota '.$installment->number.' · ' : 'Vence ' }}{{ $installment->due_date->format('d/m/Y') }}</span>
                        <span>{{ $money((string) $installment->amount) }}</span>
                    </div>
                @endforeach
            @endif

            @if ($footerLines !== [] || $settings['not_invoice_legend'])
                <hr>
                <div class="center">
                    @foreach ($footerLines as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if ($settings['not_invoice_legend'])
                        <div class="small strong">No válido como factura</div>
                    @endif
                </div>
            @endif

            @if ($settings['copies'] > 1)
                <div class="center small">Copia {{ $copy }} de {{ $settings['copies'] }}</div>
            @endif
        </div>
    @endforeach

    @if ($autoprint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
