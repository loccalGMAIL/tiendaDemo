<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Accounts\CollectPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Customer;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Nuevo cobro';

    /**
     * Con `?customer=ID` el cobro arranca con ese cliente (viene de su ficha o del escritorio).
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $customerId = request()->integer('customer');
        $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;

        // Sin cliente, el formulario arranca con sus valores por defecto (por ejemplo la fecha de hoy).
        $this->form->fill($customer === null ? null : ['customer_id' => $customer->getKey(), 'date' => now()->toDateString()]);

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new LogicException('Hace falta un usuario para registrar un cobro.');
        }

        try {
            return app(CollectPayment::class)->handle($actor, [
                'customer_id' => $data['customer_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'base_amount' => $data['base_amount'] ?? null,
                'date' => $data['date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'installment_ids' => $data['installment_ids'] ?? null,
            ]);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo registrar el cobro')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cobro registrado';
    }
}
