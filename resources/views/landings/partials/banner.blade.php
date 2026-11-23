<section id="banner" class="px-4 py-12 sm:px-6">
    <div class="mx-auto max-w-6xl rounded-3xl bg-brand px-6 py-12 text-on-brand sm:p-16">
        <div class="max-w-2xl">
            <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ $site['banner_title'] }}</h2>
            @if (trim($site['banner_text']) !== '')
                <p class="mt-5 whitespace-pre-line text-lg leading-relaxed">{{ $site['banner_text'] }}</p>
            @endif
        </div>
    </div>
</section>
