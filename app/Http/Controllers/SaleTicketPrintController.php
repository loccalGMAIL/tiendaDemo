<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\User;
use App\Services\Money;
use App\Services\SaleTicket;
use App\Services\Tender;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ticket de una venta para impresora térmica (58 u 80 mm): HTML con estilos de impresión, sin
 * librería de PDF. Lo ve quien puede ver la venta (el vendedor, las suyas). Con `?imprimir=1`
 * abre el diálogo de impresión solo (lo usa la Caja); con `?pago=` muestra «Paga con» y el
 * vuelto del último cobro, que no se guardan.
 */
class SaleTicketPrintController extends Controller
{
    public function __invoke(Request $request, Sale $sale, SaleTicket $ticket, Tender $tender): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getPanel('admin')->getLoginUrl() ?? '/');
        }

        abort_unless($user->active && $user->can('view', $sale), 403);

        $sale->load(['items.variant.product', 'payments.method', 'seller', 'customer', 'installments']);

        $payments = $sale->payments->sortBy('id')->values();
        $lastAmount = (string) ($payments->last()->amount ?? '0.00');
        $tendered = $tender->parse(is_string($request->query('pago')) ? $request->query('pago') : null);
        $change = $tendered === null ? null : $tender->change($tendered, $lastAmount);

        return view('sales.ticket-print', [
            'sale' => $sale,
            'payments' => $payments,
            'settings' => $ticket->settings(),
            'business' => $ticket->businessName(),
            'headerLines' => $ticket->headerLines(),
            'footerLines' => $ticket->footerLines(),
            // «Paga con» solo tiene sentido si alcanzó para el último cobro.
            'tendered' => $change !== null && ! Money::isNegative($change) ? $tendered : null,
            'change' => $change !== null && ! Money::isNegative($change) ? $change : null,
            'autoprint' => $request->boolean('imprimir'),
        ]);
    }
}
