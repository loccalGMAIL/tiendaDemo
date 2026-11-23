<x-filament-panels::page>
    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <div class="site-settings-editor min-w-0">
            {{ $this->content }}
        </div>

        <div class="min-w-0 xl:sticky xl:top-24 xl:self-start">
            <x-filament::section heading="Vista previa">
                <div class="space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Así se verá esta sección con el diseño actual. Los cambios se publican únicamente al guardar.
                    </p>
                    @if ($this->hasUnsavedChanges)
                        <p class="text-sm font-medium text-warning-600 dark:text-warning-400" role="status">
                            Tenés cambios sin guardar.
                        </p>
                    @endif
                    <p wire:loading.delay class="text-sm text-gray-500 dark:text-gray-400" role="status">
                        Actualizando vista previa…
                    </p>
                    <iframe
                        title="Vista previa de la sección"
                        sandbox="allow-same-origin"
                        srcdoc="{{ $this->previewHtml }}"
                        class="h-[36rem] w-full rounded-xl border border-gray-200 bg-white dark:border-gray-700"
                    ></iframe>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
