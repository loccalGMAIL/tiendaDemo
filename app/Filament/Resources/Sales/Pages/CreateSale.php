<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Actions\Sales\CreateSale as CreateSaleAction;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Nueva venta';

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new LogicException('Hace falta un usuario para confirmar una venta.');
        }

        try {
            return app(CreateSaleAction::class)->handle($actor, [
                'seller_id' => $data['seller_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'date' => $data['date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'items' => array_values(array_map(fn (array $item): array => [
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                ], $data['items'] ?? [])),
                'payments' => array_values(array_map(fn (array $payment): array => [
                    'payment_method_id' => $payment['payment_method_id'] ?? null,
                    'base_amount' => $payment['base_amount'] ?? null,
                ], $data['payments'] ?? [])),
            ]);
        } catch (ValidationException $e) {
            // Las reglas de negocio de la acción se muestran como aviso y la venta no se crea.
            Notification::make()
                ->title('No se pudo confirmar la venta')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();

            // Corta la creación sin redirigir: la persona corrige y vuelve a confirmar.
            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Venta confirmada';
    }
}
