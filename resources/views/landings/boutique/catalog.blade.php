@module('catalog')
    <section id="catalog" class="bg-night text-night-ink">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <h2 class="font-display text-center text-3xl sm:text-4xl">Catálogo</h2>
            <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>

            <ul class="mt-12 grid gap-px bg-night-ink/20 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <li class="bg-night p-8 text-center font-display text-xl">{{ $category->name }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endmodule
