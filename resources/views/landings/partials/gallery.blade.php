<section id="gallery" class="border-t border-ink/10">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 md:py-24">
        <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ $site['gallery_title'] }}</h2>
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($site['gallery_items'] as $item)
                @if ($image = $siteContent->imageUrl($item['image'] ?? null))
                    <figure class="overflow-hidden rounded-2xl">
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy" width="900" height="900" class="aspect-square w-full object-cover">
                        @if (trim($item['alt'] ?? '') !== '')
                            <figcaption class="px-1 py-3 text-sm opacity-70">{{ $item['alt'] }}</figcaption>
                        @endif
                    </figure>
                @endif
            @endforeach
        </div>
    </div>
</section>
