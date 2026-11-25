<?php

namespace App\Filament\Resources\CashSessions\Pages;

use App\Actions\CashRegister\CloseCashSession;
use App\Filament\Resources\CashSessions\CashSessionResource;
use App\Filament\Resources\CashSessions\Schemas\CloseCashSessionForm;
use App\Models\CashSession;
use App\Models\User;
use App\Services\CashSessionSummary;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ViewCashSession extends ViewRecord
{
    protected static string $resource = CashSessionResource::class;

    /**
     * La ficha muestra caja, usuarios y movimientos: se cargan de una vez.
     */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['register', 'openedBy', 'closedBy', 'movements.user']);
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof CashSession ? 'Turno '.$record->number().' · '.$record->register->name : 'Turno de caja';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Imprimir resumen')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (CashSession $record): string => route('cash-sessions.print', $record))
                ->openUrlInNewTab(),
            Action::make('close')
                ->label('Cerrar turno')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn (CashSession $record): bool => auth()->user() instanceof User && auth()->user()->can('close', $record))
                ->modalHeading(fn (CashSession $record): string => 'Cerrar '.$record->register->name)
                ->modalSubmitActionLabel('Cerrar turno')
                ->schema(fn (CashSession $record): array => CloseCashSessionForm::fields(app(CashSessionSummary::class)->for($record)['expected']))
                ->action(function (array $data, CashSession $record): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(CloseCashSession::class)->handle($actor, $record, CloseCashSessionForm::toActionData($data));
                    } catch (ValidationException $e) {
                        Notification::make()->title('No se pudo cerrar el turno')->body($e->validator->errors()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Turno cerrado')->success()->send();

                    $record->refresh();
                }),
        ];
    }
}
