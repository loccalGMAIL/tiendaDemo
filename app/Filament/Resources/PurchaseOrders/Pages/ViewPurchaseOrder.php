<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\Purchases\CancelPurchaseOrder;
use App\Actions\Purchases\IssuePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof PurchaseOrder ? 'Orden de compra '.$record->number() : 'Orden de compra';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('issue')
                ->label('Emitir')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (PurchaseOrder $record): bool => self::can('issue', $record))
                ->requiresConfirmation()
                ->modalHeading('Emitir orden')
                ->modalDescription('Una orden emitida ya no se puede editar y queda esperando su factura.')
                ->action(function (PurchaseOrder $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(IssuePurchaseOrder::class)->handle($record, $actor);
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Orden emitida')->success()->send();
                    $record->refresh();
                }),
            Action::make('print')
                ->label('Imprimir')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (PurchaseOrder $record): string => route('purchase-orders.print', ['order' => $record->getKey()]))
                ->openUrlInNewTab(),
            Action::make('invoice')
                ->label('Cargar factura')
                ->icon(Heroicon::OutlinedDocumentText)
                ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Issued && auth()->user()?->can('create', Purchase::class) === true)
                ->url(fn (PurchaseOrder $record): string => PurchaseResource::getUrl('create', ['order' => $record->getKey()])),
            Action::make('cancel')
                ->label('Anular orden')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->visible(fn (PurchaseOrder $record): bool => self::can('cancel', $record))
                ->modalHeading('Anular orden de compra')
                ->modalDescription('La orden queda en el historial como anulada. No se puede deshacer.')
                ->modalSubmitActionLabel('Anular')
                ->schema([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->required()
                        ->minLength(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data, PurchaseOrder $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CancelPurchaseOrder::class)->handle($record, $actor, $data['reason']);
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Orden anulada')->success()->send();
                    $record->refresh();
                }),
        ];
    }

    private static function can(string $ability, PurchaseOrder $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can($ability, $record);
    }
}
