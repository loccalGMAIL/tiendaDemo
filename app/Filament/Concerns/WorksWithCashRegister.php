<?php

namespace App\Filament\Concerns;

use App\Actions\CashRegister\CloseCashSession;
use App\Actions\CashRegister\OpenCashSession;
use App\Actions\CashRegister\RecordCashMovement;
use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\CashSessions\Schemas\CloseCashSessionForm;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Services\CashSessionSummary;
use App\Services\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

/**
 * La Caja con el módulo Control de caja: en qué caja trabaja esta PC (se recuerda con una
 * cookie), abrir el turno con su fondo, ingresos y retiros (F8) y el cierre con el arqueo.
 * Sin el módulo no hace nada y la Caja vende sin turnos.
 */
trait WorksWithCashRegister
{
    public const REGISTER_COOKIE = 'pos_cash_register';

    /** La caja en la que trabaja esta PC. */
    public ?int $cashRegisterId = null;

    public function mountWorksWithCashRegister(): void
    {
        if (! $this->cashControlEnabled()) {
            return;
        }

        $remembered = filter_var(request()->cookie(self::REGISTER_COOKIE), FILTER_VALIDATE_INT);
        $registers = $this->cashRegisters();

        if ($remembered !== false && $registers->has($remembered)) {
            $this->cashRegisterId = $remembered;
        } elseif ($registers->count() === 1) {
            $this->cashRegisterId = (int) $registers->keys()->first();
        }
    }

    public function cashControlEnabled(): bool
    {
        return Modules::enabled(Module::CashControl);
    }

    /**
     * @return Collection<int, CashRegister>
     */
    public function cashRegisters(): Collection
    {
        return CashRegister::query()
            ->with('openSession.openedBy')
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->keyBy('id');
    }

    public function currentRegister(): ?CashRegister
    {
        if ($this->cashRegisterId === null) {
            return null;
        }

        return CashRegister::query()->where('active', true)->find($this->cashRegisterId);
    }

    /**
     * El turno abierto de la caja de esta PC, si hay.
     */
    public function currentCashSession(): ?CashSession
    {
        if ($this->cashRegisterId === null) {
            return null;
        }

        return CashSession::query()
            ->with(['register', 'openedBy'])
            ->where('cash_register_id', $this->cashRegisterId)
            ->open()
            ->first();
    }

    public function chooseRegister(int $registerId): void
    {
        if (! $this->cashRegisters()->has($registerId)) {
            Notification::make()->title('Esa caja no está disponible.')->danger()->send();

            return;
        }

        $this->cashRegisterId = $registerId;
        Cookie::queue(self::REGISTER_COOKIE, (string) $registerId, 60 * 24 * 365);
        $this->dispatch('pos-focus-entry');
    }

    public function changeRegister(): void
    {
        $this->cashRegisterId = null;
        Cookie::queue(Cookie::forget(self::REGISTER_COOKIE));
    }

    public function canWithdrawCash(): bool
    {
        $user = auth()->user();

        return $user instanceof User && RecordCashMovement::canRecord($user, CashMovementType::Withdrawal);
    }

    public function openSessionAction(): Action
    {
        return Action::make('openSession')
            ->label('Abrir caja')
            ->icon(Heroicon::OutlinedLockOpen)
            ->size('lg')
            ->modalHeading(fn (): string => 'Abrir '.($this->currentRegister()->name ?? 'la caja'))
            ->modalDescription('Contá el efectivo que hay en el cajón para empezar el turno.')
            ->modalSubmitActionLabel('Abrir caja')
            ->modalWidth(Width::Medium)
            ->fillForm(fn (): array => [
                'opening_amount' => ($register = $this->currentRegister()) ? app(OpenCashSession::class)->suggestedOpening($register) : '0.00',
            ])
            ->schema([
                TextInput::make('opening_amount')
                    ->label('Fondo inicial')
                    ->helperText('Se propone lo que quedó en el cajón al cerrar el turno anterior.')
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $actor = auth()->user();
                $register = $this->currentRegister();

                if (! $actor instanceof User || $register === null) {
                    return;
                }

                try {
                    $session = app(OpenCashSession::class)->handle($actor, $register, $data['opening_amount'] ?? null);
                } catch (ValidationException $e) {
                    Notification::make()->title('No se pudo abrir la caja')->body($e->validator->errors()->first())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("{$register->name} abierta")
                    ->body('Turno '.$session->number().' con fondo de '.Money::format((string) $session->opening_amount).'.')
                    ->success()
                    ->send();

                $this->dispatch('pos-focus-entry');
            });
    }

    /**
     * Ingreso o retiro de efectivo (F8).
     */
    public function cashMovementAction(): Action
    {
        return Action::make('cashMovement')
            ->label('Ingreso o retiro (F8)')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Ingreso o retiro de efectivo')
            ->modalSubmitActionLabel('Registrar')
            ->modalWidth(Width::Medium)
            ->fillForm(['type' => CashMovementType::Deposit->value])
            ->schema(fn (): array => [
                Radio::make('type')
                    ->label('Movimiento')
                    ->options(array_filter([
                        CashMovementType::Deposit->value => 'Ingreso: se agrega efectivo (cambio, por ejemplo)',
                        CashMovementType::Withdrawal->value => $this->canWithdrawCash() ? 'Retiro: se saca efectivo (pago a un proveedor, retiro del dueño…)' : null,
                    ]))
                    ->required(),
                TextInput::make('amount')
                    ->label('Monto')
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0.01)
                    ->step('0.01')
                    ->required(),
                TextInput::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $actor = auth()->user();
                $session = $this->currentCashSession();

                if (! $actor instanceof User || $session === null) {
                    Notification::make()->title('La caja está cerrada.')->danger()->send();

                    return;
                }

                $type = CashMovementType::tryFrom((string) ($data['type'] ?? '')) ?? CashMovementType::Deposit;

                try {
                    app(RecordCashMovement::class)->handle($actor, $session, $type, $data['amount'] ?? null, (string) ($data['reason'] ?? ''));
                } catch (ValidationException $e) {
                    Notification::make()->title('No se pudo registrar')->body($e->validator->errors()->first())->danger()->send();

                    return;
                } catch (AuthorizationException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($type->getLabel().' de '.Money::format(Money::from($data['amount'])).' registrado')
                    ->success()
                    ->send();

                $this->dispatch('pos-focus-entry');
            });
    }

    public function closeSessionAction(): Action
    {
        return Action::make('closeSession')
            ->label('Cerrar caja')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->size('sm')
            ->modalHeading(fn (): string => 'Cerrar '.($this->currentRegister()->name ?? 'la caja'))
            ->modalSubmitActionLabel('Cerrar caja')
            ->schema(fn (): array => CloseCashSessionForm::fields($this->expectedForCloser()))
            ->action(function (array $data): void {
                $actor = auth()->user();
                $session = $this->currentCashSession();

                if (! $actor instanceof User || $session === null) {
                    return;
                }

                if ($this->lines !== []) {
                    Notification::make()->title('Hay un ticket sin cobrar')->body('Cobralo o vacialo antes de cerrar la caja.')->warning()->send();

                    return;
                }

                try {
                    $closed = app(CloseCashSession::class)->handle($actor, $session, CloseCashSessionForm::toActionData($data));
                } catch (ValidationException $e) {
                    Notification::make()->title('No se pudo cerrar la caja')->body($e->validator->errors()->first())->danger()->send();

                    return;
                }

                $this->lastSale = null;

                Notification::make()
                    ->title("{$closed->register->name} cerrada")
                    ->body('Turno '.$closed->number().'. Contado: '.Money::format((string) $closed->counted_amount).'.')
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('printSummary')
                            ->label('Imprimir resumen')
                            ->url(route('cash-sessions.print', $closed), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    /**
     * Lo esperado en el cajón, salvo que haya arqueo ciego y cierre un vendedor.
     */
    private function expectedForCloser(): ?string
    {
        $session = $this->currentCashSession();
        $user = auth()->user();

        if ($session === null) {
            return null;
        }

        $blind = (bool) Modules::setting(Module::CashControl, 'blind_count', false);

        if ($blind && ! ($user instanceof User && $user->isAdmin())) {
            return null;
        }

        return app(CashSessionSummary::class)->for($session)['expected'];
    }
}
