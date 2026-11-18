<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\Purchases\CreatePurchaseOrder as CreatePurchaseOrderAction;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Nueva orden de compra';

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new LogicException('Hace falta un usuario para crear una orden de compra.');
        }

        try {
            return app(CreatePurchaseOrderAction::class)->handle($actor, [
                'supplier_id' => $data['supplier_id'] ?? null,
                'date' => $data['date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'items' => array_values($data['items'] ?? []),
            ]);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo crear la orden')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Orden creada en borrador';
    }
}
