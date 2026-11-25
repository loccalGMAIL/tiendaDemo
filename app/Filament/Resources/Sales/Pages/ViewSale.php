<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Actions\Sales\CancelSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
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
                ->schema([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->required()
                        ->minLength(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data, Sale $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CancelSale::class)->handle($record, $actor, $data['reason']);
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
}
