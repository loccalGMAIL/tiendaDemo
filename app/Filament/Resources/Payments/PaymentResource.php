<?php

namespace App\Filament\Resources\Payments;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cobros a cuenta de los clientes (módulo Cuenta corriente). El vendedor los registra y ve los
 * suyos; solo el administrador los anula. No se editan ni se borran.
 */
class PaymentResource extends Resource
{
    use BelongsToModule;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'cobro';

    protected static ?string $pluralModelLabel = 'cobros';

    protected static ?string $navigationLabel = 'Cobros';

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 3;

    protected static function module(): Module
    {
        return Module::CurrentAccount;
    }

    /**
     * Solo los cobros a cuenta (los de una venta se ven en su venta). Lo que queda fuera de
     * esta consulta responde 404, no 403.
     *
     * @return Builder<Payment>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->scopes(['onAccount', 'visibleTo' => [auth()->user()]])
            ->with(['customer', 'method', 'collector']);
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
