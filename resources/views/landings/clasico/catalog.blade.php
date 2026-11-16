@module('catalog')
    <section id="catalog" class="border-t border-ink/10">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-3xl font-bold">Catálogo</h2>

            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <li class="rounded-xl border border-ink/10 p-5 text-lg font-medium">{{ $category->name }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endmodule
