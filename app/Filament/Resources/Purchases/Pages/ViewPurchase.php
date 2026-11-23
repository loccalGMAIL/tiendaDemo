<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Actions\Purchases\CancelPurchase;
use App\Filament\Pages\BarcodeLabels;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof Purchase ? 'Factura de compra '.$record->invoice_number : 'Factura de compra';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Módulo Códigos de barras: una etiqueta por cada prenda que llegó.
            Action::make('printLabels')
                ->label('Etiquetas de lo recibido')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->visible(fn (Purchase $record): bool => BarcodeLabels::canAccess() && ! $record->isCancelled())
                ->url(fn (Purchase $record): string => BarcodeLabels::urlFor(
                    $record->items
                        ->where('quantity', '>', 0)
                        ->groupBy('product_variant_id')
                        ->map(fn ($items): int => (int) $items->sum('quantity'))
                        ->all(),
                )),
            Action::make('cancel')
                ->label('Anular factura')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->visible(fn (Purchase $record): bool => auth()->user() instanceof User && auth()->user()->can('cancel', $record))
                ->modalHeading('Anular factura de compra')
                ->modalDescription('Con Stock, se resta lo que sumó la factura (no se puede si dejaría el stock en negativo). Su orden, si tiene, vuelve a emitida. El costo de los productos no cambia. No se puede deshacer.')
                ->modalSubmitActionLabel('Anular')
                ->schema([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->required()
                        ->minLength(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data, Purchase $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CancelPurchase::class)->handle($record, $actor, $data['reason']);
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Factura anulada')->success()->send();

                    $record->refresh();
                }),
        ];
    }
}
