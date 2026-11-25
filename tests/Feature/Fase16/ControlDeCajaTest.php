<?php

use App\Actions\CashRegister\CloseCashSession;
use App\Actions\CashRegister\OpenCashSession;
use App\Actions\CashRegister\RecordCashMovement;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Enums\SaleChannel;
use App\Facades\Modules;
use App\Models\ActivityLog;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashSessionSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::PointOfSale);
    Modules::enable(Module::CashControl);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->register = CashRegister::factory()->create(['name' => 'Caja 1']);

    $this->variant = Product::factory()->priced('1000.00', '600.00')->create()->implicitVariant;
    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Débito']);
});

function openSession(string $fund = '5000', ?CashRegister $register = null): CashSession
{
    return app(OpenCashSession::class)->handle(test()->seller, $register ?? test()->register, $fund);
}

/**
 * Venta de mostrador de dos unidades (2000) cobrada con el medio indicado en el turno.
 */
function counterSale(CashSession $session, PaymentMethod $method, int $quantity = 2): Sale
{
    return app(CreateSale::class)->handle(test()->seller, [
        'cash_session_id' => $session->id,
        'items' => [['variant_id' => test()->variant->id, 'quantity' => $quantity]],
        'payments' => [['payment_method_id' => $method->id]],
    ]);
}

describe('apertura', function () {
    it('abre el turno con el fondo como primer movimiento y deja un registro', function () {
        $session = openSession('5000');

        expect($session->isOpen())->toBeTrue()
            ->and($session->opening_amount)->toBe('5000.00')
            ->and($session->movements()->sole())
            ->type->toBe(CashMovementType::Opening)
            ->amount->toBe('5000.00')
            ->and(ActivityLog::query()->where('description', 'like', 'Apertura de Caja 1%')->count())->toBe(1);
    });

    it('no abre dos turnos en la misma caja, ni una caja desactivada, ni con fondo negativo', function () {
        openSession();

        expect(fn () => openSession())->toThrow(ValidationException::class, 'La caja «Caja 1» ya está abierta.')
            ->and(fn () => openSession('100', CashRegister::factory()->inactive()->create(['name' => 'Vieja'])))->toThrow(ValidationException::class, 'La caja «Vieja» está desactivada.')
            ->and(fn () => openSession('-1', CashRegister::factory()->create()))->toThrow(ValidationException::class, 'El fondo inicial no puede ser negativo.');
    });

    it('sin el módulo no se puede abrir', function () {
        Modules::disable(Module::CashControl);

        expect(fn () => openSession())->toThrow(ValidationException::class, 'El módulo Control de caja está apagado.');
    });

    it('propone como fondo lo que quedó al cerrar el turno anterior', function () {
        $session = openSession('5000');
        app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '5000', 'left_amount' => '3000']);

        expect(app(OpenCashSession::class)->suggestedOpening($this->register))->toBe('3000.00')
            ->and(app(OpenCashSession::class)->suggestedOpening(CashRegister::factory()->create()))->toBe('0.00');
    });
});

describe('ventas en la caja', function () {
    it('lo cobrado en efectivo entra al cajón y lo demás queda en el turno sin tocar el efectivo', function () {
        $session = openSession('5000');

        $cashSale = counterSale($session, $this->cash);
        $cardSale = counterSale($session, $this->card);

        $saleMovement = CashMovement::query()->where('type', CashMovementType::Sale)->sole();

        // 2 × 1000 = 2000; efectivo −10 % → 1800.
        expect($saleMovement->amount)->toBe('1800.00')
            ->and($saleMovement->subject->is($cashSale))->toBeTrue()
            ->and($cashSale->payments->sole()->fresh()->cash_session_id)->toBe($session->id)
            ->and($cardSale->payments->sole()->fresh()->cash_session_id)->toBe($session->id)
            ->and(app(CashSessionSummary::class)->for($session))->toMatchArray([
                'opening' => '5000.00',
                'sales' => '1800.00',
                'expected' => '6800.00',
                'sales_count' => 2,
            ]);
    });

    it('no vende en un turno cerrado ni con una venta online, y no queda nada a medias', function () {
        $session = openSession();
        app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '0']);

        expect(fn () => counterSale($session, $this->cash))->toThrow(ValidationException::class, 'La caja está cerrada: abrila de nuevo para cobrar.');

        $open = openSession('0', CashRegister::factory()->create());

        expect(fn () => app(CreateSale::class)->handle($this->seller, [
            'channel' => SaleChannel::Online,
            'cash_session_id' => $open->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
            'payments' => [['payment_method_id' => $this->cash->id]],
        ]))->toThrow(ValidationException::class, 'Una venta online no pasa por la caja.');

        expect(Sale::query()->count())->toBe(0);
    });

    it('una venta sin turno no toca ninguna caja', function () {
        $session = openSession('5000');

        sellVariant($this->seller, $this->variant);

        expect(app(CashSessionSummary::class)->for($session)['expected'])->toBe('5000.00');
    });
});

describe('ingresos y retiros', function () {
    it('el vendedor ingresa efectivo y no retira, salvo que el módulo lo permita', function () {
        $session = openSession('5000');

        app(RecordCashMovement::class)->handle($this->seller, $session, CashMovementType::Deposit, '1000', 'Cambio');

        expect(fn () => app(RecordCashMovement::class)->handle($this->seller, $session, CashMovementType::Withdrawal, '500', 'Proveedor'))
            ->toThrow(AuthorizationException::class);

        Modules::updateSettings(Module::CashControl, ['seller_can_withdraw' => true]);

        $withdrawal = app(RecordCashMovement::class)->handle($this->seller, $session, CashMovementType::Withdrawal, '500', 'Pago a proveedor');

        expect($withdrawal->amount)->toBe('-500.00')
            ->and(app(CashSessionSummary::class)->for($session)['expected'])->toBe('5500.00');
    });

    it('no se retira más de lo que hay, ni sin motivo, ni en un turno cerrado', function () {
        $session = openSession('1000');

        expect(fn () => app(RecordCashMovement::class)->handle($this->admin, $session, CashMovementType::Withdrawal, '1500', 'Retiro'))
            ->toThrow(ValidationException::class, 'En la caja hay $ 1.000,00: no se puede retirar más.')
            ->and(fn () => app(RecordCashMovement::class)->handle($this->admin, $session, CashMovementType::Deposit, '100', '  '))
            ->toThrow(ValidationException::class, 'Indicá el motivo.');

        app(CloseCashSession::class)->handle($this->admin, $session, ['counted_amount' => '1000']);

        expect(fn () => app(RecordCashMovement::class)->handle($this->admin, $session, CashMovementType::Deposit, '100', 'Cambio'))
            ->toThrow(ValidationException::class, "El turno {$session->number()} ya está cerrado.");
    });
});

describe('cierre', function () {
    it('cierra con el arqueo: esperado, contado, diferencia y fondo que queda', function () {
        $session = openSession('5000');
        counterSale($session, $this->cash);
        app(RecordCashMovement::class)->handle($this->admin, $session, CashMovementType::Withdrawal, '2000', 'Retiro del dueño');

        // 5000 + 1800 − 2000 = 4800; contó 4700 → faltan 100.
        $closed = app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '4700', 'left_amount' => '3000', 'notes' => 'Faltó un billete']);

        expect($closed)
            ->isOpen()->toBeFalse()
            ->closed_by->toBe($this->seller->id)
            ->expected_amount->toBe('4800.00')
            ->counted_amount->toBe('4700.00')
            ->difference->toBe('-100.00')
            ->left_amount->toBe('3000.00')
            ->closing_notes->toBe('Faltó un billete');

        expect(fn () => app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '0']))
            ->toThrow(ValidationException::class, "El turno {$session->number()} ya está cerrado.");
    });

    it('cuenta billete por billete con las monedas', function () {
        $session = openSession('0');

        $closed = app(CloseCashSession::class)->handle($this->seller, $session, ['counted_bills' => [
            '20000' => '2', '1000' => 3, '100' => '', 'coins' => '50',
        ]]);

        expect($closed->counted_amount)->toBe('43050.00')
            ->and($closed->counted_bills)->toBe([20000 => 2, 1000 => 3, 'coins' => '50.00'])
            ->and($closed->difference)->toBe('43050.00');
    });

    it('el fondo que queda no puede superar lo contado y hay que indicar lo contado', function () {
        $session = openSession('1000');

        expect(fn () => app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '1000', 'left_amount' => '1500']))
            ->toThrow(ValidationException::class, 'El fondo que queda no puede ser mayor que lo contado.')
            ->and(fn () => app(CloseCashSession::class)->handle($this->seller, $session, []))
            ->toThrow(ValidationException::class, 'Indicá cuánto efectivo contaste.');
    });
});

describe('anulaciones', function () {
    it('devuelve el efectivo desde el turno de la venta si sigue abierto', function () {
        $session = openSession('5000');
        $sale = counterSale($session, $this->cash);

        app(CancelSale::class)->handle($sale, $this->admin, 'Se arrepintió');

        expect(CashMovement::query()->where('type', CashMovementType::Refund)->sole())
            ->cash_session_id->toBe($session->id)
            ->amount->toBe('-1800.00')
            ->and(app(CashSessionSummary::class)->for($session)['expected'])->toBe('5000.00');
    });

    it('si el turno de la venta ya cerró, sale de la única caja abierta', function () {
        $old = openSession('0');
        $sale = counterSale($old, $this->cash);
        app(CloseCashSession::class)->handle($this->seller, $old, ['counted_amount' => '1800']);

        $current = openSession('5000', CashRegister::factory()->create());

        app(CancelSale::class)->handle($sale, $this->admin, 'Devolución');

        expect(CashMovement::query()->where('type', CashMovementType::Refund)->sole()->cash_session_id)->toBe($current->id);
    });

    it('con varias cajas abiertas hay que elegir de cuál sale, y si no se elige no se anula', function () {
        $old = openSession('0');
        $sale = counterSale($old, $this->cash);
        app(CloseCashSession::class)->handle($this->seller, $old, ['counted_amount' => '1800']);

        openSession('1000', CashRegister::factory()->create());
        $chosen = openSession('1000', CashRegister::factory()->create());

        expect(fn () => app(CancelSale::class)->handle($sale, $this->admin, 'Devolución'))
            ->toThrow(ValidationException::class, 'Hay más de una caja abierta: elegí de cuál sale la devolución del efectivo.')
            ->and($sale->fresh()->isCancelled())->toBeFalse();

        app(CancelSale::class)->handle($sale, $this->admin, 'Devolución', $chosen->id);

        expect(CashMovement::query()->where('type', CashMovementType::Refund)->sole()->cash_session_id)->toBe($chosen->id);
    });

    it('sin cajas abiertas, o si no se cobró en efectivo en una caja, no hay devolución en ninguna', function () {
        $session = openSession('0');
        $cashSale = counterSale($session, $this->cash);
        $cardSale = counterSale($session, $this->card);

        app(CancelSale::class)->handle($cardSale, $this->admin, 'Error');
        app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '1800']);
        app(CancelSale::class)->handle($cashSale, $this->admin, 'Error');

        expect($cashSale->fresh()->isCancelled())->toBeTrue()
            ->and(CashMovement::query()->where('type', CashMovementType::Refund)->exists())->toBeFalse();
    });
});

it('resume lo cobrado por medio de pago, sin lo anulado', function () {
    $session = openSession('0');
    counterSale($session, $this->cash);
    counterSale($session, $this->card);
    $cancelled = counterSale($session, $this->card, 1);
    app(CancelSale::class)->handle($cancelled, $this->admin, 'Error');

    expect(app(CashSessionSummary::class)->for($session)['by_method'])->toBe([
        ['name' => 'Débito', 'is_cash' => false, 'count' => 1, 'amount' => '2000.00'],
        ['name' => 'Efectivo', 'is_cash' => true, 'count' => 1, 'amount' => '1800.00'],
    ]);
});

it('los movimientos de caja no se modifican ni se borran', function () {
    $movement = openSession('100')->movements()->sole();

    expect(fn () => $movement->update(['amount' => '1']))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('el control de caja necesita el módulo Caja encendido', function () {
    resetModules();

    expect(fn () => Modules::enable(Module::CashControl))->toThrow(ValidationException::class);

    Modules::enable(Module::PointOfSale);
    Modules::enable(Module::CashControl);

    expect(fn () => Modules::disable(Module::PointOfSale))->toThrow(ValidationException::class)
        ->and(Modules::enabled(Module::CashControl))->toBeTrue();
});
