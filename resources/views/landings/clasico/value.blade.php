<section id="value" class="border-t border-ink/10 bg-accent/10">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-3xl font-bold">{{ $site['value_title'] }}</h2>

        <div class="mt-10 grid gap-8 md:grid-cols-2">
            @if ($image = $appearance->assetUrl('value'))
                <img src="{{ $image }}" alt="" class="aspect-[4/3] w-full rounded-2xl object-cover">
            @endif

            <ul class="grid gap-6 sm:grid-cols-2 {{ $image ?? false ? '' : 'md:col-span-2 md:grid-cols-3' }}">
                @foreach ($site['value_items'] as $item)
                    <li class="rounded-xl border border-ink/10 bg-surface p-5">
                        <h3 class="text-lg font-semibold text-brand">{{ $item['title'] }}</h3>
                        @if (trim($item['text']) !== '')
                            <p class="mt-2 opacity-80">{{ $item['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
