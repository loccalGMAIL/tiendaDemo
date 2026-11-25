<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-seller">Vendedor</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-seller" wire:model.live="sellerId">
            <option value="">Todos</option>
            @foreach ($this->sellers() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>

<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-channel">Canal</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-channel" wire:model.live="channel">
            <option value="">Todos</option>
            @foreach (\App\Enums\SaleChannel::cases() as $channelOption)
                <option value="{{ $channelOption->value }}">{{ $channelOption->getLabel() }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>

<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-group">Agrupar</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-group" wire:model.live="group">
            @foreach (\App\Services\Reports\SalesReport::GROUPS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
