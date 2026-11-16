<section id="faq">
    <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6">
        <h2 class="font-display text-center text-3xl sm:text-4xl">Preguntas frecuentes</h2>
        <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>

        <div class="mt-12 divide-y divide-ink/15">
            @foreach ($site['faq_items'] as $item)
                <details class="group py-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-display text-lg">
                        {{ $item['question'] }}
                        <span class="transition group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <p class="mt-3 opacity-80">{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
