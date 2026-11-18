@php
    use App\Services\Money;

    $format = function (string $kind, ?string $value): string {
        return match ($kind) {
            'money' => Money::format((string) $value),
            'percent' => number_format((float) $value, 2, ',', '.').' %',
            default => (string) $value,
        };
    };
@endphp

<div class="report-scroll">
    <table class="report-table">
        <thead>
            <tr>
                @foreach ($result['columns'] as $column)
                    <th class="{{ $column['kind'] === 'text' ? '' : 'num' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($result['rows'] as $row)
                <tr>
                    @foreach ($result['columns'] as $column)
                        <td class="{{ $column['kind'] === 'text' ? '' : 'num' }}">{{ $format($column['kind'], $row[$column['key']] ?? '') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($result['columns']) }}" class="empty">No hay datos para esos filtros.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                @foreach ($result['columns'] as $column)
                    <td class="{{ $column['kind'] === 'text' ? '' : 'num' }}">{{ $format($column['kind'], $result['totals'][$column['key']] ?? '') }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>

@foreach ($result['subtotals'] ?? [] as $heading => $subtotals)
    <h3 class="report-subtitle">{{ $heading }}</h3>
    <div class="report-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>{{ $heading === 'Por cobrador' ? 'Cobrador' : 'Medio de pago' }}</th>
                    <th class="num">Cobros</th>
                    <th class="num">Base</th>
                    <th class="num">Ajuste</th>
                    <th class="num">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subtotals as $subtotal)
                    <tr>
                        <td>{{ $subtotal['label'] }}</td>
                        <td class="num">{{ $subtotal['count'] }}</td>
                        <td class="num">{{ $format('money', $subtotal['base']) }}</td>
                        <td class="num">{{ $format('money', $subtotal['adjustment']) }}</td>
                        <td class="num">{{ $format('money', $subtotal['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
