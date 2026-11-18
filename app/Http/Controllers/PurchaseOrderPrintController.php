<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Versión imprimible de una orden de compra (módulo Compras, solo administrador): HTML
 * con estilos de impresión, sin librería de PDF. No lleva costos: la orden solo pide cantidades.
 */
class PurchaseOrderPrintController extends Controller
{
    public function __invoke(Request $request, int $order): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getPanel('admin')->getLoginUrl() ?? '/');
        }

        abort_unless($user->isAdmin(), 403);

        $purchaseOrder = PurchaseOrder::query()->with(['supplier', 'items.variant.product'])->findOrFail($order);

        return view('purchases.order-print', [
            'business' => app(SiteContent::class)->name(),
            'order' => $purchaseOrder,
            'printedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}
