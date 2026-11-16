<section id="faq" class="border-t border-ink/10">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <h2 class="text-3xl font-bold">Preguntas frecuentes</h2>

        <div class="mt-8 divide-y divide-ink/10 border-y border-ink/10">
            @foreach ($site['faq_items'] as $item)
                <details class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                        {{ $item['question'] }}
                        <span class="text-brand transition group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <p class="mt-3 opacity-80">{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
