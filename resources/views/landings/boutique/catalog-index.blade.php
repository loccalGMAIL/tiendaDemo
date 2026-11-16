@extends('landings.boutique.layout')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <nav class="text-center text-xs uppercase tracking-widest opacity-60" aria-label="Ruta de navegación">
            <a href="{{ url('/') }}" class="hover:text-brand">Inicio</a>
            <span aria-hidden="true">/</span>
            @if ($category)
                <a href="{{ url('/catalogo') }}" class="hover:text-brand">Catálogo</a>
                <span aria-hidden="true">/</span>
                <span>{{ $category->name }}</span>
            @else
                <span>Catálogo</span>
            @endif
        </nav>

        <h1 class="mt-4 text-center font-display text-4xl sm:text-5xl">{{ $category?->name ?? 'Catálogo' }}</h1>
        <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>

        <form method="get" action="{{ url()->current() }}" role="search" class="mx-auto mt-10 flex max-w-xl gap-2">
            <label for="q" class="sr-only">Buscar productos</label>
            <input id="q" type="search" name="q" value="{{ $term }}" placeholder="Buscar productos" maxlength="100"
                class="w-full border border-ink/25 bg-surface px-5 py-3 focus:border-brand focus:outline-none">
            <button type="submit" class="bg-night px-6 py-3 text-xs uppercase tracking-widest text-night-ink hover:opacity-90">Buscar</button>
        </form>

        @if ($categories->isNotEmpty())
            <div class="mt-8 flex flex-wrap justify-center gap-x-6 gap-y-2 text-xs uppercase tracking-widest" aria-label="Categorías">
                <a href="{{ url('/catalogo') }}" class="{{ $category ? 'opacity-60 hover:opacity-100' : 'border-b border-brand' }}">Todos</a>
                @foreach ($categories as $item)
                    <a href="{{ url('/catalogo/'.$item->slug) }}" class="{{ $category?->is($item) ? 'border-b border-brand' : 'opacity-60 hover:opacity-100' }}">{{ $item->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($term !== '')
            <p class="mt-8 text-center text-sm opacity-70">{{ $products->total() }} {{ $products->total() === 1 ? 'resultado' : 'resultados' }} para «{{ $term }}»</p>
        @endif

        @if ($products->isEmpty())
            <p class="mt-12 border border-dashed border-ink/25 p-10 text-center opacity-70">
                No encontramos productos. Probá con otra búsqueda o mirá todo el catálogo.
            </p>
        @else
            <div class="mt-12 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('landings.boutique.product-card')
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="mt-14 flex items-center justify-between text-xs uppercase tracking-widest" aria-label="Paginación">
                    @if ($products->previousPageUrl())
                        <a href="{{ $products->previousPageUrl() }}" rel="prev" class="border border-ink/25 px-5 py-2 hover:border-brand">&larr; Anterior</a>
                    @else
                        <span></span>
                    @endif

                    <span class="opacity-70">Página {{ $products->currentPage() }} de {{ $products->lastPage() }}</span>

                    @if ($products->nextPageUrl())
                        <a href="{{ $products->nextPageUrl() }}" rel="next" class="border border-ink/25 px-5 py-2 hover:border-brand">Siguiente &rarr;</a>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
