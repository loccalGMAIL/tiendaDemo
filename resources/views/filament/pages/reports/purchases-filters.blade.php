<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-supplier">Proveedor</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-supplier" wire:model.live="supplierId">
            <option value="">Todos</option>
            @foreach ($this->suppliers() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>

<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-group">Agrupar</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-group" wire:model.live="group">
            @foreach (\App\Services\Reports\PurchasesReport::GROUPS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
