@php
    $money = fn (string $amount): string => \App\Services\Money::format($amount);
    $printable = $paperWidth === 58 ? 48 : 72;
    $expected = $session->isOpen() ? $summary['expected'] : (string) $session->expected_amount;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Turno {{ $session->number() }} — {{ $business }}</title>
    <style>
        @page { size: {{ $paperWidth }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body { margin: 0; color: #000; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; font-size: {{ $paperWidth === 58 ? '11px' : '12px' }}; line-height: 1.3; }
        .actions { display: flex; justify-content: center; padding: 1rem; }
        .actions button { padding: 0.4rem 0.9rem; font-size: 0.9rem; cursor: pointer; }
        .ticket { width: {{ $printable }}mm; margin: 0 auto 1rem; padding: 3mm 0; background: #fff; }
        .center { text-align: center; }
        .title { font-size: 1.25em; font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row span:last-child { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .strong { font-weight: 700; }
        .small { font-size: 0.9em; }
        @media screen { .ticket { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); padding: 4mm; width: {{ $printable + 8 }}mm; } }
        @media print { html { background: #fff; } .actions { display: none; } .ticket { margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>

    <div class="ticket">
        <div class="center">
            <div class="title">{{ $business }}</div>
            <div class="strong">{{ $session->isOpen() ? 'Estado de caja' : 'Cierre de caja' }}</div>
            <div>{{ $session->register->name }} · Turno {{ $session->number() }}</div>
        </div>

        <hr>

        <div class="row"><span>Apertura</span><span>{{ $session->opened_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Abrió</span><span>{{ $session->openedBy->name }}</span></div>
        @if (! $session->isOpen())
            <div class="row"><span>Cierre</span><span>{{ $session->closed_at->format('d/m/Y H:i') }}</span></div>
            <div class="row"><span>Cerró</span><span>{{ $session->closedBy?->name }}</span></div>
        @endif
        <div class="row"><span>Ventas</span><span>{{ $summary['sales_count'] }}</span></div>

        <hr>

        <div class="strong">Efectivo</div>
        <div class="row"><span>Fondo inicial</span><span>{{ $money($summary['opening']) }}</span></div>
        <div class="row"><span>Ventas</span><span>{{ $money($summary['sales']) }}</span></div>
        <div class="row"><span>Ingresos</span><span>{{ $money($summary['deposits']) }}</span></div>
        <div class="row"><span>Retiros</span><span>{{ $money($summary['withdrawals']) }}</span></div>
        <div class="row"><span>Devoluciones</span><span>{{ $money($summary['refunds']) }}</span></div>
        @if ($showExpected)
            <div class="row strong"><span>Esperado</span><span>{{ $money($expected) }}</span></div>
        @endif
        @if (! $session->isOpen())
            <div class="row strong"><span>Contado</span><span>{{ $money((string) $session->counted_amount) }}</span></div>
            @if ($showExpected)
                <div class="row strong"><span>Diferencia</span><span>{{ \App\Filament\Resources\CashSessions\Schemas\CashSessionInfolist::differenceLabel((string) $session->difference) }}</span></div>
            @endif
            <div class="row"><span>Fondo que queda</span><span>{{ $money((string) $session->left_amount) }}</span></div>
            @if (filled($session->counted_bills))
                <div class="small">{{ \App\Filament\Resources\CashSessions\Schemas\CashSessionInfolist::billsLabel($session->counted_bills) }}</div>
            @endif
        @endif

        @if ($summary['by_method'] !== [])
            <hr>
            <div class="strong">Cobros por medio de pago</div>
            @foreach ($summary['by_method'] as $method)
                <div class="row"><span>{{ $method['name'] }} ({{ $method['count'] }})</span><span>{{ $money($method['amount']) }}</span></div>
            @endforeach
        @endif

        @php $manual = $session->movements->filter(fn ($movement) => in_array($movement->type, [\App\Enums\CashMovementType::Deposit, \App\Enums\CashMovementType::Withdrawal, \App\Enums\CashMovementType::Refund], true)); @endphp
        @if ($manual->isNotEmpty())
            <hr>
            <div class="strong">Ingresos, retiros y devoluciones</div>
            @foreach ($manual as $movement)
                <div class="row small"><span>{{ $movement->created_at->format('H:i') }} {{ $movement->description }}</span><span>{{ $money((string) $movement->amount) }}</span></div>
            @endforeach
        @endif

        @if (filled($session->closing_notes))
            <hr>
            <div class="small">{{ $session->closing_notes }}</div>
        @endif

        <hr>
        <div class="center small">Impreso el {{ $printedAt }}</div>
    </div>

    @if ($autoprint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
