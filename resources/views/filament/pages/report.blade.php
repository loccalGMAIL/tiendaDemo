<x-filament-panels::page>
    @php
        $result = $this->result();
    @endphp

    @include('reports._styles')

    <x-filament::section>
        <div class="report-filters">
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
    </x-filament::section>

    <div class="report-stats">
        @foreach ($this->summary($result) as $stat)
            <div class="report-stat">
                <small>{{ $stat['label'] }}</small>
                <strong>{{ $stat['value'] }}</strong>
            </div>
        @endforeach
    </div>

    <x-filament::section heading="Resultado" :description="'Del '.$this->periodLabel().' · Importes en pesos'">
        @include('reports._table', ['result' => $result])
    </x-filament::section>
</x-filament-panels::page>
