<?php

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Users\DeleteUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();

    $this->sell = function (User $actor, ?User $seller = null) {
        $product = Product::factory()->create();
        $method = PaymentMethod::factory()->create();

        return app(CreateSale::class)->handle($actor, [
            'seller_id' => $seller?->id,
            'items' => [['variant_id' => $product->implicitVariant->id, 'quantity' => 1]],
            'payments' => [['payment_method_id' => $method->id]],
        ]);
    };
});

it('un usuario sin operaciones no tiene operaciones y se puede borrar', function () {
    $free = User::factory()->seller()->create();

    expect($free->hasOperations())->toBeFalse()
        ->and($this->admin->can('delete', $free))->toBeTrue();

    app(DeleteUser::class)->handle($free, $this->admin);

    expect(User::find($free->id))->toBeNull();
});

it('un vendedor con ventas no se borra, se desactiva', function () {
    $seller = User::factory()->seller()->create();
    ($this->sell)($seller);

    expect($seller->hasOperations())->toBeTrue()
        ->and($this->admin->can('delete', $seller))->toBeFalse();

    expect(fn () => app(DeleteUser::class)->handle($seller, $this->admin))
        ->toThrow(ValidationException::class, 'desactivalo');

    expect(User::find($seller->id))->not->toBeNull();
});

it('un administrador que cargó ventas por otro tampoco se borra', function () {
    $otherAdmin = User::factory()->admin()->create();
    ($this->sell)($otherAdmin);

    expect($otherAdmin->hasOperations())->toBeTrue()
        ->and($this->admin->can('delete', $otherAdmin))->toBeFalse();
});

it('quien anuló una venta no se borra', function () {
    $canceller = User::factory()->admin()->create();
    $sale = ($this->sell)($this->admin);

    app(CancelSale::class)->handle($sale, $canceller, 'Error');

    expect($canceller->hasOperations())->toBeTrue()
        ->and($this->admin->can('delete', $canceller))->toBeFalse();
});

it('quien figura solo como vendedor de una venta ajena tampoco se borra', function () {
    $seller = User::factory()->seller()->create();
    ($this->sell)($this->admin, $seller);

    expect($seller->hasOperations())->toBeTrue()
        ->and($this->admin->can('delete', $seller))->toBeFalse();
});

it('en el listado de usuarios no se ofrece borrar a quien tiene operaciones, pero sí desactivarlo', function () {
    $withSales = User::factory()->seller()->create();
    $free = User::factory()->seller()->create();
    ($this->sell)($withSales);

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $withSales)
        ->assertTableActionVisible('delete', $free)
        ->assertTableActionVisible('edit', $withSales);

    $withSales->update(['active' => false]);

    expect($withSales->fresh()->active)->toBeFalse();
});

it('las ventas de un usuario desactivado se conservan', function () {
    $seller = User::factory()->seller()->create();
    $sale = ($this->sell)($seller);

    $seller->update(['active' => false]);

    expect(Sale::find($sale->id)->seller_id)->toBe($seller->id);
});
