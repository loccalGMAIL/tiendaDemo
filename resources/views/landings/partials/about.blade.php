<section id="about" class="border-t border-ink/10">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 md:py-24 {{ $siteContent->imageUrl($site['about_image']) ? 'md:grid-cols-2' : '' }}">
        @if ($image = $siteContent->imageUrl($site['about_image']))
            <img src="{{ $image }}" alt="{{ $site['about_title'] }}" loading="lazy" width="1200" height="900" class="aspect-[4/3] w-full rounded-2xl object-cover">
        @endif
        <div class="max-w-2xl">
            <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ $site['about_title'] }}</h2>
            <p class="mt-6 whitespace-pre-line text-lg leading-relaxed opacity-80">{{ $site['about_text'] }}</p>
        </div>
    </div>
</section>
