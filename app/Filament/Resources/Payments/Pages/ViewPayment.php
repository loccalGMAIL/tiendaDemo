<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Accounts\CancelPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof Payment ? 'Cobro '.$record->number() : 'Cobro';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Anular cobro')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->visible(fn (Payment $record): bool => auth()->user() instanceof User && auth()->user()->can('cancel', $record))
                ->modalHeading('Anular cobro')
                ->modalDescription('Los vencimientos vuelven a deber lo que se les había imputado y las ventas que se habían saldado se reabren. No se puede deshacer.')
                ->modalSubmitActionLabel('Anular')
                ->schema([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->required()
                        ->minLength(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data, Payment $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CancelPayment::class)->handle($record, $actor, $data['reason']);
                    } catch (ValidationException $e) {
                        Notification::make()->title($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Cobro anulado')->success()->send();

                    $record->refresh();
                }),
        ];
    }
}
