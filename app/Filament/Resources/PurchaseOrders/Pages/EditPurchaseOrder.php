<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\Purchases\UpdatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof PurchaseOrder) {
            $data['items'] = $record->items
                ->map(fn (PurchaseOrderItem $item): array => [
                    'variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                ])
                ->all();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();

        if (! $actor instanceof User || ! $record instanceof PurchaseOrder) {
            throw new LogicException('Hace falta un usuario para editar una orden de compra.');
        }

        try {
            return app(UpdatePurchaseOrder::class)->handle($record, $actor, [
                'supplier_id' => $data['supplier_id'] ?? null,
                'date' => $data['date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'items' => array_values($data['items'] ?? []),
            ]);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo guardar la orden')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
