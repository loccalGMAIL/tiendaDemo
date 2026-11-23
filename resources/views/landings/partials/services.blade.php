<section id="services" class="border-t border-ink/10 bg-accent/5">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 md:py-24">
        <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ $site['services_title'] }}</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($site['service_items'] as $item)
                <article class="rounded-2xl border border-ink/10 bg-surface p-8">
                    <span class="text-sm font-semibold text-brand" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="mt-5 font-display text-xl font-semibold">{{ $item['title'] }}</h3>
                    <p class="mt-3 whitespace-pre-line leading-relaxed opacity-75">{{ $item['text'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
