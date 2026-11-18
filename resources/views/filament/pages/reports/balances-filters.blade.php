<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-months">Meses a mostrar</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="report-months" wire:model.live="months">
            @foreach (\App\Services\Reports\AccountBalancesReport::MONTHS as $months)
                <option value="{{ $months }}">Próximos {{ $months }} meses</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
