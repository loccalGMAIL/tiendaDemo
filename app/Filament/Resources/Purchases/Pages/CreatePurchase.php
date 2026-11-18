<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Actions\Purchases\CreatePurchase as CreatePurchaseAction;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Nueva factura de compra';

    /**
     * Con `?order=ID` (orden emitida) la factura arranca con lo pedido para corregir lo que llegó.
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $defaults = $this->orderDefaults();

        // Sin orden, el formulario arranca con sus valores por defecto (por ejemplo la fecha de hoy).
        $this->form->fill($defaults === [] ? null : $defaults);

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new LogicException('Hace falta un usuario para cargar una factura de compra.');
        }

        try {
            return app(CreatePurchaseAction::class)->handle($actor, [
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'date' => $data['date'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'items' => array_values(array_map(fn (array $item): array => [
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'unit_cost' => $item['unit_cost'] ?? null,
                ], $data['items'] ?? [])),
            ]);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo cargar la factura')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Factura de compra cargada';
    }

    /**
     * @return array<string, mixed>
     */
    private function orderDefaults(): array
    {
        $orderId = request()->integer('order');

        if ($orderId < 1) {
            return [];
        }

        $order = PurchaseOrder::query()->with('items.variant.product')->find($orderId);

        if ($order === null || $order->status !== PurchaseOrderStatus::Issued) {
            Notification::make()->title('Esa orden no está emitida: no se puede cargar su factura.')->warning()->send();

            return [];
        }

        return [
            'purchase_order_id' => $order->getKey(),
            'supplier_id' => $order->supplier_id,
            'date' => now()->toDateString(),
            'items' => $order->items
                ->map(fn (PurchaseOrderItem $item): array => [
                    'variant_id' => $item->product_variant_id,
                    'ordered_quantity' => $item->quantity,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->product->cost,
                ])
                ->all(),
        ];
    }
}
