<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Actions\Sales\CancelSale;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof Sale ? 'Venta '.$record->number() : 'Venta';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ticket')
                ->label('Imprimir ticket')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (Sale $record): string => route('sales.ticket', $record))
                ->openUrlInNewTab(),
            Action::make('cancel')
                ->label('Anular venta')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->visible(fn (Sale $record): bool => auth()->user() instanceof User && auth()->user()->can('cancel', $record))
                ->modalHeading('Anular venta')
                ->modalDescription('La venta queda en el historial como anulada y sus cobros se anulan con ella. No se puede deshacer.')
                ->modalSubmitActionLabel('Anular')
                ->schema(fn (Sale $record): array => [
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->required()
                        ->minLength(3)
                        ->maxLength(500),
                    // Módulo Control de caja: el efectivo cobrado en una caja se devuelve desde una abierta.
                    Select::make('refund_cash_session_id')
                        ->label('El efectivo sale de')
                        ->options(fn (): array => self::openCashSessions()->mapWithKeys(fn (CashSession $session): array => [$session->getKey() => $session->label()])->all())
                        ->default(fn (): ?int => self::defaultRefundSession($record))
                        ->required(fn (): bool => self::openCashSessions()->count() > 1)
                        ->native(false)
                        ->helperText('Se registra como devolución en esa caja.')
                        ->visible(fn (): bool => self::refundsCash($record) && self::openCashSessions()->isNotEmpty()),
                    Text::make('Ninguna caja está abierta: la devolución del efectivo queda fuera de caja.')
                        ->color('warning')
                        ->visible(fn (): bool => self::refundsCash($record) && self::openCashSessions()->isEmpty()),
                ])
                ->action(function (array $data, Sale $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CancelSale::class)->handle(
                            $record,
                            $actor,
                            $data['reason'],
                            filled($data['refund_cash_session_id'] ?? null) ? (int) $data['refund_cash_session_id'] : null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Venta anulada')->success()->send();

                    // Recarga estado, motivo, autor y cobros anulados para mostrarlos al instante.
                    $record->refresh();
                }),
        ];
    }

    /**
     * Con Control de caja: la venta cobró efectivo en una caja y anularla lo devuelve.
     */
    private static function refundsCash(Sale $sale): bool
    {
        return Modules::enabled(Module::CashControl)
            && Payment::query()
                ->where('sale_id', $sale->getKey())
                ->whereNotNull('cash_session_id')
                ->whereHas('method', fn ($method) => $method->where('is_cash', true))
                ->exists();
    }

    /**
     * @return Collection<int, CashSession>
     */
    private static function openCashSessions(): Collection
    {
        return CashSession::query()->with(['register', 'openedBy'])->open()->orderBy('id')->get();
    }

    /**
     * El turno de la venta si sigue abierto; si no, la única caja abierta.
     */
    private static function defaultRefundSession(Sale $sale): ?int
    {
        $open = self::openCashSessions();

        if ($sale->cash_session_id !== null && $open->contains('id', $sale->cash_session_id)) {
            return $sale->cash_session_id;
        }

        return $open->count() === 1 ? (int) $open->first()?->getKey() : null;
    }
}
