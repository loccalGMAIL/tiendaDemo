@module('catalog')
    <section id="catalog" class="border-t border-ink/10">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-3xl font-bold">Catálogo</h2>
                <a href="{{ url('/catalogo') }}" class="text-sm font-medium text-brand hover:underline">Ver catálogo completo &rarr;</a>
            </div>

            @if ($categories->isNotEmpty())
                <ul class="mt-6 flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <li><a href="{{ url('/catalogo/'.$category->slug) }}" class="rounded-full border border-ink/20 px-4 py-1.5 text-sm hover:border-brand hover:text-brand">{{ $category->name }}</a></li>
                    @endforeach
                </ul>
            @endif

            @if ($featured->isNotEmpty())
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($featured as $product)
                        @include('landings.clasico.product-card')
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endmodule
