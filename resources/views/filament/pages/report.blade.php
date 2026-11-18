<x-filament-panels::page>
    @php
        $result = $this->result();
    @endphp

    @include('reports._styles')

    <div class="grid gap-4 md:grid-cols-4">
        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-from">Desde</label>
            <x-filament::input.wrapper>
                <x-filament::input id="report-from" type="date" wire:model.live="from" />
            </x-filament::input.wrapper>
        </div>

        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-to">Hasta</label>
            <x-filament::input.wrapper>
                <x-filament::input id="report-to" type="date" wire:model.live="to" />
            </x-filament::input.wrapper>
        </div>

        @include($this->filtersView())
    </div>

    <x-filament::section>
        @include('reports._table', ['result' => $result])
    </x-filament::section>

    <div>
        <x-filament::button tag="a" :href="$this->printUrl()" target="_blank" icon="heroicon-m-printer" color="gray">
            Imprimir
        </x-filament::button>
    </div>
</x-filament-panels::page>
