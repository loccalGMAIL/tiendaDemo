<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-month">Mes</label>
    <x-filament::input.wrapper>
        <x-filament::input id="report-month" type="month" wire:model.live="month" />
    </x-filament::input.wrapper>
</div>

<div>
    <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="report-overdue">Atrasados</label>
    <label class="flex items-center gap-2 text-sm" for="report-overdue" style="padding-top: 0.5rem;">
        <input id="report-overdue" type="checkbox" wire:model.live="includeOverdue">
        Incluir los de meses anteriores
    </label>
</div>
