<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reports\PaymentsReport;
use App\Services\Reports\Report;
use App\Services\Reports\SalesReport;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Versión imprimible de los informes (módulo Informes, solo administrador): HTML con
 * estilos de impresión, sin librería de PDF. Usa el mismo servicio que la pantalla.
 */
class ReportPrintController extends Controller
{
    /** @var array<string, array{title: string, service: class-string<Report>}> */
    private const REPORTS = [
        'ventas' => ['title' => 'Ventas y ganancia', 'service' => SalesReport::class],
        'cobros' => ['title' => 'Cobros realizados', 'service' => PaymentsReport::class],
    ];

    public function __invoke(Request $request, string $report): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getPanel('admin')->getLoginUrl() ?? '/');
        }

        abort_unless($user->isAdmin(), 403);
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $definition = self::REPORTS[$report];
        $service = app($definition['service']);
        $filters = $request->query();

        [$from, $to] = $service->period($filters);

        return view('reports.print', [
            'business' => app(SiteContent::class)->name(),
            'title' => $definition['title'],
            'period' => $from->format('d/m/Y').' al '.$to->format('d/m/Y'),
            'result' => $service->run($filters),
            'printedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}
