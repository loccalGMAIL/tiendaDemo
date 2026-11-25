<?php

namespace App\Filament\Pages;

use App\Enums\ActivityEvent;
use App\Models\Sale;
use App\Services\ActivityLogger;
use App\Services\SaleTicket;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Configuración → Ticket (solo administrador): formato del ticket que se imprime en la Caja y
 * desde cada venta, y qué hace la Caja al confirmar. Es del núcleo: toda instalación vende.
 *
 * @property-read Schema $form
 */
class TicketSettings extends AdministratorPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $navigationLabel = 'Ticket';

    protected static ?string $title = 'Ticket de venta';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'ticket-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SaleTicket::class)->settings());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Ver con la última venta')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (): ?string => ($sale = Sale::query()->latest('id')->first()) ? route('sales.ticket', $sale) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => Sale::query()->exists()),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Papel e impresión')
                ->columns(2)
                ->schema([
                    Select::make('paper_width')
                        ->label('Ancho del papel')
                        ->options(SaleTicket::PAPER_WIDTHS)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->native(false),
                    Select::make('copies')
                        ->label('Copias')
                        ->options(fn (): array => collect(range(1, SaleTicket::MAX_COPIES))
                            ->mapWithKeys(fn (int $copies): array => [$copies => $copies === 1 ? '1 copia' : "{$copies} copias"])
                            ->all())
                        ->required()
                        ->selectablePlaceholder(false)
                        ->native(false),
                    Radio::make('print_on_confirm')
                        ->label('Al confirmar una venta en la Caja')
                        ->options(SaleTicket::PRINT_MODES)
                        ->required()
                        ->columnSpanFull(),
                ]),
            Section::make('Encabezado y pie')
                ->description('El nombre del negocio va siempre arriba. La dirección y el teléfono salen de Configuración → Sitio.')
                ->schema([
                    Toggle::make('show_site_contact')
                        ->label('Mostrar la dirección y el teléfono del sitio'),
                    Textarea::make('header')
                        ->label('Líneas del encabezado')
                        ->helperText('Una por renglón: CUIT, condición frente al IVA, horario…')
                        ->rows(3)
                        ->maxLength(SaleTicket::MAX_TEXT),
                    Textarea::make('footer')
                        ->label('Pie')
                        ->helperText('Por ejemplo, la política de cambios.')
                        ->rows(3)
                        ->maxLength(SaleTicket::MAX_TEXT),
                ]),
            Section::make('Qué se muestra')
                ->columns(2)
                ->schema([
                    Toggle::make('show_seller')->label('Vendedor'),
                    Toggle::make('show_customer')->label('Cliente'),
                    Toggle::make('show_codes')->label('Código de cada producto'),
                    Toggle::make('not_invoice_legend')->label('Leyenda «No válido como factura»'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Guardar')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $ticket = app(SaleTicket::class);
        $before = $ticket->settings();
        $after = $ticket->save($this->form->getState());

        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        if ($changed !== []) {
            app(ActivityLogger::class)->log(
                ActivityEvent::Updated,
                'Cambio en el formato del ticket',
                properties: [
                    'old' => array_intersect_key($before, array_flip($changed)),
                    'new' => array_intersect_key($after, array_flip($changed)),
                ],
            );
        }

        $this->form->fill($after);

        Notification::make()->title('Ticket guardado')->success()->send();
    }
}
