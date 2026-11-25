<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\CashSession;
use App\Models\User;
use App\Services\CashSessionSummary;
use App\Services\SaleTicket;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resumen de un turno para la impresora de la caja (módulo Control de caja): el administrador
 * cualquiera; el vendedor, los que abrió o cerró. Con arqueo ciego, el vendedor no ve lo
 * esperado ni la diferencia.
 */
class CashSessionPrintController extends Controller
{
    public function __invoke(Request $request, CashSession $session, CashSessionSummary $summary, SaleTicket $ticket): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getPanel('admin')->getLoginUrl() ?? '/');
        }

        $isOwnSession = in_array($user->getKey(), [$session->opened_by, $session->closed_by], true);

        abort_unless($user->active && ($user->isAdmin() || ($user->isSeller() && $isOwnSession)), 403);

        $session->load(['register', 'openedBy', 'closedBy', 'movements.user']);

        return view('cash.session-print', [
            'session' => $session,
            'summary' => $summary->for($session),
            'business' => $ticket->businessName(),
            'paperWidth' => $session->register->paper_width ?? $ticket->settings()['paper_width'],
            'showExpected' => $user->isAdmin() || ! Modules::setting(Module::CashControl, 'blind_count', false),
            'printedAt' => now()->format('d/m/Y H:i'),
            'autoprint' => $request->boolean('imprimir'),
        ]);
    }
}
