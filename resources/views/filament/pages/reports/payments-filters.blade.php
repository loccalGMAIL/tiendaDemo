<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-method">Medio de pago</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-method" wire:model.live="paymentMethodId">
            <option value="">Todos</option>
            @foreach ($this->methods() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>

<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-collector">Cobrador</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-collector" wire:model.live="collectedBy">
            <option value="">Todos</option>
            @foreach ($this->sellers() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
