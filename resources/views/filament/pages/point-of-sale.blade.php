<x-filament-panels::page>
    @php
        $ticket = $this->ticket();
        $total = $this->ticketTotal();
        $methods = $this->paymentMethods();
        $customerName = $this->customerName();
        $summary = $checkingOut ? $this->checkoutSummary() : null;
        $money = fn (string $amount): string => \App\Services\Money::format($amount);
    @endphp

    <div
        class="pos"
        x-data="{
            highlighted: 0,
            due: 0,
            selectedLine: $wire.$entangle('selectedLine'),
            tendered: $wire.$entangle('tendered'),
            methodIds: @js($methods->keys()->values()),
            isTyping(target) {
                return ['INPUT', 'SELECT', 'TEXTAREA'].includes(target?.tagName);
            },
            focusEntry(delay = 0) {
                setTimeout(() => {
                    if ($wire.checkingOut || ($wire.mountedActions ?? []).length > 0) return;
                    this.$refs.entry?.focus();
                }, delay);
            },
            printTicket(url) {
                if (! url) return;

                {{-- Un marco invisible carga el ticket y abre su diálogo de impresión (o imprime
                     directo con Chrome en modo --kiosk-printing). --}}
                const frame = document.createElement('iframe');
                frame.setAttribute('aria-hidden', 'true');
                frame.style.cssText = 'position: fixed; right: 0; bottom: 0; width: 0; height: 0; border: 0;';
                frame.src = url + (url.includes('?') ? '&' : '?') + 'imprimir=1';
                document.body.appendChild(frame);
                setTimeout(() => frame.remove(), 60000);
                this.focusEntry(800);
            },
            askTicket() {
                {{-- El botón de Filament es su propio componente Alpine: se lo busca por atributo. --}}
                setTimeout(() => this.$root.querySelector('[data-pos-print]')?.focus(), 400);
            },
            focusTendered() {
                this.$nextTick(() => this.$refs.tendered?.focus());
            },
            submitEntry() {
                const input = this.$refs.entry;
                const value = input.value.trim();
                input.value = '';

                if (value !== '') {
                    $wire.submitEntry(value).then(() => { this.highlighted = 0; this.focusEntry(); });

                    return;
                }

                const result = $wire.results[this.highlighted];

                if (result) {
                    $wire.addVariant(result.id, $wire.resultsQuantity).then(() => this.focusEntry());
                }
            },
            move(step) {
                if ($wire.results.length > 0) {
                    this.highlighted = Math.max(0, Math.min(this.highlighted + step, $wire.results.length - 1));

                    return;
                }

                if ($wire.lines.length > 0) {
                    const current = this.selectedLine ?? (step > 0 ? -1 : $wire.lines.length);
                    this.selectedLine = Math.max(0, Math.min(current + step, $wire.lines.length - 1));
                }
            },
            onEntryKey(event) {
                const empty = event.target.value === '';

                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.submitEntry();
                } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.move(event.key === 'ArrowDown' ? 1 : -1);
                } else if (empty && (event.key === '+' || event.key === '-')) {
                    event.preventDefault();
                    $wire.changeQuantity(this.selectedLine, event.key === '+' ? 1 : -1);
                } else if (empty && event.key === 'Delete') {
                    event.preventDefault();
                    $wire.removeLine(this.selectedLine);
                }
            },
            onKey(event) {
                if (($wire.mountedActions ?? []).length > 0) return;

                if (event.key === 'F2') {
                    event.preventDefault();
                    this.focusEntry();
                } else if (event.key === 'F4') {
                    event.preventDefault();
                    $wire.mountAction('selectCustomer');
                } else if (event.key === 'F9') {
                    event.preventDefault();
                    this.printTicket($wire.lastSale?.ticket_url);
                } else if (event.key === 'F12') {
                    event.preventDefault();
                    if ($wire.checkingOut) return;

                    {{-- Si quedó un código escrito sin Enter, primero se agrega. --}}
                    const pending = this.$refs.entry?.value.trim() ?? '';

                    if (pending === '') {
                        $wire.openCheckout();

                        return;
                    }

                    this.$refs.entry.value = '';
                    $wire.submitEntry(pending).then(() => $wire.openCheckout());
                } else if (event.key === 'Escape') {
                    if ($wire.checkingOut) {
                        event.preventDefault();
                        $wire.closeCheckout().then(() => this.focusEntry());
                    } else if ($wire.results.length > 0) {
                        $wire.clearResults();
                    }
                } else if ($wire.checkingOut && /^[1-9]$/.test(event.key) && ! this.isTyping(event.target)) {
                    const id = this.methodIds[Number(event.key) - 1];

                    if (id !== undefined) {
                        event.preventDefault();
                        $wire.choosePaymentMethod(id).then(() => this.focusTendered());
                    }
                } else if (! $wire.checkingOut && event.key.length === 1 && ! event.ctrlKey && ! event.metaKey && ! event.altKey && ! this.isTyping(event.target)) {
                    {{-- Si el foco quedó en otro lado, lo que llega del lector va igual al campo. --}}
                    this.$refs.entry?.focus();
                }
            },
            parseAmount(text) {
                let value = String(text ?? '').replace(/[\s$]/g, '');

                if (value === '') return null;

                if (value.includes(',')) {
                    value = value.replaceAll('.', '').replace(',', '.');
                } else if (! /\.\d{1,2}$/.test(value)) {
                    value = value.replaceAll('.', '');
                }

                return /^\d+(\.\d{1,2})?$/.test(value) ? Number(value) : null;
            },
            formatMoney(amount) {
                return '$ ' + new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
            },
        }"
        x-init="
            if ($store.sidebar?.isOpen && window.matchMedia('(min-width: 1024px)').matches) $store.sidebar.close();
            focusEntry(50);
        "
        x-on:keydown.window="onKey($event)"
        x-on:pos-focus-entry.window="focusEntry(300)"
        x-on:modal-closed.window="focusEntry(300)"
        x-on:pos-print-ticket.window="printTicket($event.detail.url)"
        x-on:pos-ask-ticket.window="askTicket()"
    >
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Entrada y ticket --}}
            <div class="min-w-0 space-y-4 lg:col-span-2">
                <div class="relative">
                    <label for="pos-entry" class="sr-only">Código, SKU o nombre del producto</label>
                    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" class="pos-entry">
                        <x-filament::input
                            id="pos-entry"
                            type="text"
                            x-ref="entry"
                            x-on:keydown="onEntryKey($event)"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            placeholder="Escaneá o escribí código, SKU o nombre y Enter  ·  3*código suma tres"
                            class="py-3! text-lg!"
                        />
                    </x-filament::input.wrapper>

                    @if ($results !== [])
                        <div class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                            <p class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
                                {{ count($results) }} resultados{{ $resultsQuantity > 1 ? " · se agregan {$resultsQuantity} unidades" : '' }} · elegí con ↑ ↓ y Enter, Esc para cerrar
                            </p>
                            <ul class="max-h-80 overflow-y-auto" role="listbox" aria-label="Resultados de la búsqueda">
                                @foreach ($results as $result)
                                    <li wire:key="result-{{ $result['id'] }}" role="option" x-bind:aria-selected="highlighted === {{ $loop->index }}">
                                        <button
                                            type="button"
                                            tabindex="-1"
                                            wire:click="addVariant({{ $result['id'] }}, {{ $resultsQuantity ?? 'null' }})"
                                            x-on:mouseenter="highlighted = {{ $loop->index }}"
                                            class="flex w-full items-center gap-3 px-4 py-2 text-start text-sm"
                                            x-bind:class="highlighted === {{ $loop->index }} ? 'bg-primary-50 dark:bg-primary-500/10' : ''"
                                        >
                                            <span class="flex-1">
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $result['label'] }}</span>
                                                <span class="ms-2 text-xs text-gray-500 dark:text-gray-400">{{ $result['code'] }}</span>
                                            </span>
                                            @if ($result['stock'] !== null)
                                                <span @class(['text-xs', 'text-danger-600 dark:text-danger-400' => $result['stock'] <= 0, 'text-gray-500 dark:text-gray-400' => $result['stock'] > 0])>
                                                    Stock {{ $result['stock'] }}
                                                </span>
                                            @endif
                                            <span class="w-28 text-end font-semibold tabular-nums">{{ $money($result['price']) }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <x-filament::section compact>
                    @if ($ticket === [])
                        <div class="py-16 text-center text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-qr-code" class="mx-auto mb-3 h-10 w-10" />
                            <p>Escaneá un código o buscá un producto para empezar.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                        <th class="px-2 py-2 text-start font-medium">Producto</th>
                                        <th class="w-40 px-2 py-2 text-center font-medium">Cantidad</th>
                                        <th class="w-32 px-2 py-2 text-end font-medium">Precio</th>
                                        <th class="w-32 px-2 py-2 text-end font-medium">Subtotal</th>
                                        <th class="w-10 px-2 py-2"><span class="sr-only">Quitar</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ticket as $line)
                                        <tr
                                            wire:key="line-{{ $line['variant_id'] }}"
                                            x-on:click="selectedLine = {{ $line['index'] }}"
                                            class="border-b border-gray-100 dark:border-white/5"
                                            x-bind:class="selectedLine === {{ $line['index'] }} ? 'bg-primary-50 dark:bg-primary-500/10' : ''"
                                        >
                                            <td class="min-w-40 px-2 py-2">
                                                <div class="font-medium text-gray-950 dark:text-white">{{ $line['description'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $line['code'] }}
                                                    @if (! $line['available'])
                                                        <span class="text-danger-600 dark:text-danger-400">· no disponible</span>
                                                    @elseif ($line['stock'] !== null && $line['quantity'] > $line['stock'])
                                                        <span class="text-warning-600 dark:text-warning-400">· {{ $line['stock'] > 0 ? "hay {$line['stock']} en stock" : 'sin stock' }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-2 py-2">
                                                <div class="flex items-center justify-center gap-1">
                                                    <x-filament::icon-button icon="heroicon-m-minus" color="gray" size="sm" label="Una menos" tabindex="-1" wire:click="changeQuantity({{ $line['index'] }}, -1)" />
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="{{ \App\Filament\Pages\PointOfSale::MAX_QUANTITY }}"
                                                        value="{{ $line['quantity'] }}"
                                                        aria-label="Cantidad de {{ $line['description'] }}"
                                                        wire:change="setQuantity({{ $line['index'] }}, $event.target.value)"
                                                        x-on:keydown.enter.prevent="$event.target.blur(); focusEntry(150)"
                                                        class="w-16 rounded-md border-gray-300 bg-white px-1 py-1 text-center text-sm tabular-nums dark:border-white/10 dark:bg-white/5"
                                                    />
                                                    <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="sm" label="Una más" tabindex="-1" wire:click="changeQuantity({{ $line['index'] }}, 1)" />
                                                </div>
                                            </td>
                                            <td class="px-2 py-2 text-end whitespace-nowrap tabular-nums">{{ $money($line['unit_price']) }}</td>
                                            <td class="px-2 py-2 text-end font-semibold whitespace-nowrap tabular-nums">{{ $money($line['subtotal']) }}</td>
                                            <td class="px-2 py-2 text-end">
                                                <x-filament::icon-button icon="heroicon-m-x-mark" color="danger" size="sm" label="Quitar" tabindex="-1" wire:click="removeLine({{ $line['index'] }})" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            </div>

            {{-- Total, cliente y cobro --}}
            <div class="min-w-0 space-y-4">
                <x-filament::section compact>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Cliente</p>
                            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $customerName ?? 'Consumidor final' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($customerName !== null)
                                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" label="Volver a consumidor final" wire:click="forgetCustomer" />
                            @endif
                            {{ $this->selectCustomerAction }}
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section compact>
                    <div class="space-y-4">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->ticketUnits() }} {{ $this->ticketUnits() === 1 ? 'unidad' : 'unidades' }}</p>
                            </div>
                            <p class="text-3xl font-bold whitespace-nowrap tabular-nums text-gray-950 sm:text-4xl dark:text-white">{{ $money($total) }}</p>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Antes de recargos o descuentos del medio de pago.</p>

                        <x-filament::button size="xl" class="w-full" icon="heroicon-o-banknotes" wire:click="openCheckout" :disabled="$ticket === []">
                            Cobrar (F12)
                        </x-filament::button>

                        @if ($ticket !== [])
                            <x-filament::button color="gray" size="sm" class="w-full" wire:click="clearTicket" wire:confirm="¿Vaciar el ticket sin vender?">
                                Vaciar ticket
                            </x-filament::button>
                        @endif
                    </div>
                </x-filament::section>

                @if ($lastSale !== null)
                    <x-filament::section compact>
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Última venta</p>
                                <p class="font-medium text-gray-950 dark:text-white">{{ $lastSale['number'] }} · {{ $money($lastSale['total']) }}</p>
                            </div>
                            <x-filament::link :href="$lastSale['url']" size="sm">Ver</x-filament::link>
                        </div>
                        @if ($lastSale['change'] !== null)
                            <p class="mt-2 text-2xl font-bold tabular-nums text-success-600 dark:text-success-400">Vuelto {{ $money($lastSale['change']) }}</p>
                        @endif
                        <div class="mt-3 space-y-1">
                            <x-filament::button
                                color="gray"
                                size="sm"
                                icon="heroicon-o-printer"
                                class="w-full"
                                data-pos-print
                                x-on:click="printTicket($wire.lastSale?.ticket_url)"
                            >
                                Imprimir ticket (F9)
                            </x-filament::button>
                            @if ($lastSale['ask_print'])
                                <p class="text-center text-xs text-gray-500 dark:text-gray-400">Enter imprime · seguí escaneando para no imprimir</p>
                            @endif
                        </div>
                    </x-filament::section>
                @endif

                <div class="rounded-lg border border-dashed border-gray-300 p-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <p class="mb-1 font-medium text-gray-700 dark:text-gray-300">Atajos</p>
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-1">
                        <div><dt class="inline"><kbd class="pos-kbd">F2</kbd></dt> <dd class="inline">Buscar</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">F4</kbd></dt> <dd class="inline">Cliente</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">F12</kbd></dt> <dd class="inline">Cobrar</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">Esc</kbd></dt> <dd class="inline">Volver</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">↑</kbd> <kbd class="pos-kbd">↓</kbd></dt> <dd class="inline">Elegir línea</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">+</kbd> <kbd class="pos-kbd">−</kbd></dt> <dd class="inline">Cantidad</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">Supr</kbd></dt> <dd class="inline">Quitar línea</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">3*</kbd></dt> <dd class="inline">Tres unidades</dd></div>
                        <div><dt class="inline"><kbd class="pos-kbd">F9</kbd></dt> <dd class="inline">Imprimir ticket</dd></div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Ventana de cobro --}}
        @if ($summary !== null)
            @php
                $rows = $summary['rows'];
                $lastRow = $rows === [] ? null : $rows[array_key_last($rows)];
            @endphp
            <div
                wire:key="pos-checkout"
                class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-950/50 p-4 lg:items-center"
                x-init="$nextTick(() => $refs.firstMethod?.focus())"
            >
                <div role="dialog" aria-modal="true" aria-labelledby="pos-checkout-title" class="w-full max-w-4xl rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-white/10">
                        <h2 id="pos-checkout-title" class="text-lg font-semibold text-gray-950 dark:text-white">Cobrar · {{ $money($summary['total']) }}</h2>
                        <x-filament::button color="gray" size="sm" x-on:click="$wire.closeCheckout().then(() => focusEntry())">Volver (Esc)</x-filament::button>
                    </div>

                    <div class="grid gap-6 p-6 md:grid-cols-2">
                        {{-- Medios de pago --}}
                        <div class="space-y-4">
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">
                                    {{ count($rows) > 1 ? 'Medio de pago del último cobro' : 'Medio de pago' }}
                                    <span class="font-normal text-gray-500 dark:text-gray-400">· tecla del número</span>
                                </p>
                                @if ($methods->isEmpty())
                                    <p class="text-sm text-danger-600 dark:text-danger-400">No hay medios de pago activos.</p>
                                @endif
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($methods as $method)
                                        @php $isChosen = $lastRow !== null && $lastRow['method']?->is($method); @endphp
                                        <button
                                            type="button"
                                            wire:key="method-{{ $method->id }}"
                                            @if ($loop->first) x-ref="firstMethod" @endif
                                            x-on:click="$wire.choosePaymentMethod({{ $method->id }}).then(() => focusTendered())"
                                            @class([
                                                'flex items-center gap-2 rounded-lg px-3 py-2 text-start text-sm ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                'bg-primary-600 font-semibold text-white ring-primary-600' => $isChosen,
                                                'bg-white text-gray-950 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:hover:bg-white/10' => ! $isChosen,
                                            ])
                                        >
                                            @if ($loop->iteration <= 9)
                                                <kbd @class(['pos-kbd', 'bg-white/20! text-white! ring-white/30!' => $isChosen])>{{ $loop->iteration }}</kbd>
                                            @endif
                                            <span>{{ $method->label() }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            @if (count($rows) > 1 || $onAccount)
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $onAccount ? 'Lo que se cobra ahora' : 'Pago dividido' }}</p>
                                    @foreach ($rows as $row)
                                        <div wire:key="payment-{{ $row['index'] }}" class="flex items-center gap-2">
                                            <select
                                                wire:model.live="payments.{{ $row['index'] }}.method_id"
                                                aria-label="Medio de pago {{ $loop->iteration }}"
                                                class="min-w-0 flex-1 rounded-md border-gray-300 bg-white py-1.5 text-sm dark:border-white/10 dark:bg-white/5"
                                            >
                                                <option value="">Elegí…</option>
                                                @foreach ($methods as $method)
                                                    <option value="{{ $method->id }}">{{ $method->label() }}</option>
                                                @endforeach
                                            </select>
                                            @if ($row['covers_rest'])
                                                <span class="w-28 text-end text-sm text-gray-500 dark:text-gray-400">el resto</span>
                                            @else
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0"
                                                    aria-label="Monto que cubre el medio {{ $loop->iteration }}"
                                                    wire:model.blur="payments.{{ $row['index'] }}.base"
                                                    class="w-28 rounded-md border-gray-300 bg-white py-1.5 text-end text-sm tabular-nums dark:border-white/10 dark:bg-white/5"
                                                />
                                            @endif
                                            <span class="w-28 text-end text-sm font-semibold tabular-nums">{{ $money($row['amount']) }}</span>
                                            @if (count($rows) > 1)
                                                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" size="sm" label="Quitar este medio" wire:click="removePaymentLine({{ $row['index'] }})" />
                                            @endif
                                        </div>
                                    @endforeach
                                    <p class="text-xs text-gray-500 dark:text-gray-400">El monto es la parte del total que cubre cada medio; a la derecha, lo que se cobra con su recargo o descuento.</p>
                                </div>
                            @endif

                            <x-filament::link tag="button" size="sm" icon="heroicon-m-plus" wire:click="addPaymentLine">
                                {{ $onAccount ? 'Agregar un medio de pago' : 'Dividir el pago con otro medio' }}
                            </x-filament::link>

                            @if ($this->canLeaveBalance())
                                <div class="space-y-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                                        <x-filament::input.checkbox wire:model.live="onAccount" />
                                        Dejar saldo a cuenta del cliente
                                    </label>

                                    @if ($onAccount)
                                        @if ($customerName === null)
                                            <div class="flex items-center justify-between gap-2 text-sm text-warning-700 dark:text-warning-400">
                                                <span>Elegí el cliente: el saldo queda a su cuenta.</span>
                                                <x-filament::button size="xs" color="warning" x-on:click="$wire.mountAction('selectCustomer')">Elegir (F4)</x-filament::button>
                                            </div>
                                        @else
                                            <p class="text-sm text-gray-700 dark:text-gray-300">Cliente: <span class="font-medium">{{ $customerName }}</span></p>
                                        @endif

                                        @php $plans = $this->financingPlans(); @endphp
                                        @if ($plans->isNotEmpty())
                                            <div>
                                                <label for="pos-plan" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Plan de pago</label>
                                                <select id="pos-plan" wire:model.live="financingPlanId" class="w-full rounded-md border-gray-300 bg-white py-1.5 text-sm dark:border-white/10 dark:bg-white/5">
                                                    <option value="">Un solo vencimiento</option>
                                                    @foreach ($plans as $plan)
                                                        <option value="{{ $plan->id }}">{{ $plan->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        @if (filled($financingPlanId) && $plans->isNotEmpty())
                                            @if ($this->asksFirstMonth())
                                                <div>
                                                    <label for="pos-first-month" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Mes de la primera cuota</label>
                                                    <select id="pos-first-month" wire:model.live="firstMonth" class="w-full rounded-md border-gray-300 bg-white py-1.5 text-sm dark:border-white/10 dark:bg-white/5">
                                                        @foreach ($this->monthOptions() as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif
                                        @else
                                            <div>
                                                <label for="pos-due-date" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Vencimiento del saldo</label>
                                                <input id="pos-due-date" type="date" wire:model.live="dueDate" min="{{ now()->toDateString() }}" class="w-full rounded-md border-gray-300 bg-white py-1.5 text-sm dark:border-white/10 dark:bg-white/5" />
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Resumen, paga con y vuelto --}}
                        <div class="space-y-4">
                            <dl class="space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Subtotal</dt><dd class="tabular-nums">{{ $money($summary['subtotal']) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Recargos y descuentos</dt><dd class="tabular-nums">{{ $money($summary['adjustment']) }}</dd></div>
                                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ $money($summary['total']) }}</dd></div>
                                @if ($onAccount)
                                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Se cobra ahora</dt><dd class="tabular-nums">{{ $money($summary['now']) }}</dd></div>
                                    <div class="flex justify-between font-semibold text-warning-700 dark:text-warning-400"><dt>A cuenta del cliente</dt><dd class="tabular-nums">{{ $money($summary['balance']) }}</dd></div>
                                @endif
                            </dl>

                            @if ($lastRow !== null)
                                <div class="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                    {{-- Lo que hay que cobrar con el último medio, para calcular el vuelto en pantalla. --}}
                                    <span wire:key="due-{{ $summary['last_amount'] }}" x-init="due = {{ (float) $summary['last_amount'] }}" hidden></span>

                                    <div class="flex items-end justify-between gap-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">A cobrar{{ $lastRow['method'] ? ' con '.$lastRow['method']->name : '' }}</p>
                                        <p class="text-2xl font-bold whitespace-nowrap tabular-nums text-gray-950 sm:text-3xl dark:text-white">{{ $money($summary['last_amount']) }}</p>
                                    </div>

                                    <div>
                                        <label for="pos-tendered" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">Paga con</label>
                                        <x-filament::input.wrapper prefix="$">
                                            <x-filament::input
                                                id="pos-tendered"
                                                type="text"
                                                inputmode="decimal"
                                                autocomplete="off"
                                                x-ref="tendered"
                                                x-model="tendered"
                                                x-on:keydown.enter.prevent="$wire.confirm()"
                                                placeholder="Opcional: lo que entrega el cliente"
                                                class="text-lg! tabular-nums"
                                            />
                                        </x-filament::input.wrapper>
                                    </div>

                                    @if ($summary['suggestions'] !== [])
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($summary['suggestions'] as $amount)
                                                <x-filament::button
                                                    color="gray"
                                                    size="xs"
                                                    wire:key="suggestion-{{ $amount }}"
                                                    x-on:click="tendered = '{{ $amount }}'; focusTendered()"
                                                >
                                                    {{ $loop->first ? 'Exacto' : $money($amount) }}
                                                </x-filament::button>
                                            @endforeach
                                        </div>
                                    @endif

                                    <template x-if="parseAmount(tendered) !== null">
                                        <p
                                            class="text-2xl font-bold tabular-nums"
                                            x-bind:class="parseAmount(tendered) >= due ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                                            x-text="parseAmount(tendered) >= due ? 'Vuelto ' + formatMoney(parseAmount(tendered) - due) : 'Faltan ' + formatMoney(due - parseAmount(tendered))"
                                        ></p>
                                    </template>
                                </div>
                            @endif

                            @if ($summary['error'] !== null)
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $summary['error'] }}</p>
                            @endif

                            <x-filament::button size="xl" class="w-full" icon="heroicon-o-check" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
                                Confirmar venta (Enter)
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
