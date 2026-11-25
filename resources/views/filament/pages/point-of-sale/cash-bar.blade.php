{{-- Módulo Control de caja: caja de esta PC, apertura y estado del turno. --}}
@php
    $register = $this->currentRegister();
@endphp

@if ($register === null)
    @php $registers = $this->cashRegisters(); @endphp
    <x-filament::section class="mx-auto max-w-2xl" heading="¿En qué caja trabajás?" description="Esta PC la recuerda para la próxima vez.">
        @if ($registers->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay cajas activas.</p>
            @if (\App\Filament\Resources\CashRegisters\CashRegisterResource::canCreate())
                <x-filament::link :href="\App\Filament\Resources\CashRegisters\CashRegisterResource::getUrl('create')" class="mt-2">Crear una caja</x-filament::link>
            @endif
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($registers as $option)
                    <button
                        type="button"
                        wire:key="register-{{ $option->id }}"
                        wire:click="chooseRegister({{ $option->id }})"
                        class="rounded-lg bg-white p-4 text-start ring-1 ring-gray-950/10 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
                    >
                        <span class="block font-semibold text-gray-950 dark:text-white">{{ $option->name }}</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">
                            {{ $option->openSession ? 'Abierta por '.$option->openSession->openedBy->name.' desde las '.$option->openSession->opened_at->format('H:i') : 'Cerrada' }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>
@elseif ($cashSession === null)
    <x-filament::section class="mx-auto max-w-2xl">
        <div class="space-y-4 py-6 text-center">
            <x-filament::icon icon="heroicon-o-lock-closed" class="mx-auto h-10 w-10 text-gray-400" />
            <div>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $register->name }} está cerrada</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Abrila con el efectivo que hay en el cajón para empezar a vender.</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
                {{ $this->openSessionAction }}
                <x-filament::link tag="button" size="sm" color="gray" wire:click="changeRegister">Cambiar de caja</x-filament::link>
            </div>
        </div>
    </x-filament::section>
@else
    <div data-pos-cash-open class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white px-4 py-2 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <span class="inline-block h-2 w-2 rounded-full bg-success-500" aria-hidden="true"></span>
            <span class="font-semibold text-gray-950 dark:text-white">{{ $register->name }}</span>
            <span>· Turno {{ $cashSession->number() }} · abierta por {{ $cashSession->openedBy->name }} a las {{ $cashSession->opened_at->format('H:i') }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-2">
            {{ $this->cashMovementAction }}
            <x-filament::button tag="a" color="gray" size="sm" icon="heroicon-o-document-text" :href="route('cash-sessions.print', $cashSession)" target="_blank">
                Estado de caja
            </x-filament::button>
            {{ $this->closeSessionAction }}
            <x-filament::link tag="button" size="sm" color="gray" wire:click="changeRegister">Cambiar de caja</x-filament::link>
        </div>
    </div>
@endif
