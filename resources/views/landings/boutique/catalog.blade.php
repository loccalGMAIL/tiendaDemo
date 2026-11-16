@module('catalog')
    <section id="catalog" class="bg-night text-night-ink">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <h2 class="text-center font-display text-3xl sm:text-4xl">Catálogo</h2>
            <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>

            @if ($categories->isNotEmpty())
                <ul class="mt-10 flex flex-wrap justify-center gap-x-8 gap-y-2 text-xs uppercase tracking-widest">
                    @foreach ($categories as $category)
                        <li><a href="{{ url('/catalogo/'.$category->slug) }}" class="opacity-80 hover:text-brand hover:opacity-100">{{ $category->name }}</a></li>
                    @endforeach
                </ul>
            @endif

            @if ($featured->isNotEmpty())
                <div class="mt-12 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($featured as $product)
                        @include('landings.boutique.product-card', ['dark' => true])
                    @endforeach
                </div>
            @endif

            <p class="mt-12 text-center">
                <a href="{{ url('/catalogo') }}" class="border border-brand px-8 py-3 text-xs uppercase tracking-widest text-brand hover:bg-brand hover:text-on-brand">Ver catálogo completo</a>
            </p>
        </div>
    </section>
@endmodule
