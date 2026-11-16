@extends('landings.clasico.layout')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <nav class="text-sm opacity-70" aria-label="Ruta de navegación">
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

        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">{{ $category?->name ?? 'Catálogo' }}</h1>

        <form method="get" action="{{ url()->current() }}" role="search" class="mt-6 flex max-w-xl gap-2">
            <label for="q" class="sr-only">Buscar productos</label>
            <input id="q" type="search" name="q" value="{{ $term }}" placeholder="Buscar productos" maxlength="100"
                class="w-full rounded-full border border-ink/20 bg-surface px-5 py-2.5 focus:border-brand focus:outline-none">
            <button type="submit" class="rounded-full bg-brand px-6 py-2.5 font-medium text-on-brand hover:opacity-90">Buscar</button>
        </form>

        @if ($categories->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2" aria-label="Categorías">
                <a href="{{ url('/catalogo') }}" class="rounded-full border px-4 py-1.5 text-sm {{ $category ? 'border-ink/20 hover:border-brand' : 'border-brand bg-brand text-on-brand' }}">Todos</a>
                @foreach ($categories as $item)
                    <a href="{{ url('/catalogo/'.$item->slug) }}" class="rounded-full border px-4 py-1.5 text-sm {{ $category?->is($item) ? 'border-brand bg-brand text-on-brand' : 'border-ink/20 hover:border-brand' }}">{{ $item->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($term !== '')
            <p class="mt-6 text-sm opacity-70">{{ $products->total() }} {{ $products->total() === 1 ? 'resultado' : 'resultados' }} para «{{ $term }}»</p>
        @endif

        @if ($products->isEmpty())
            <p class="mt-12 rounded-2xl border border-dashed border-ink/20 p-10 text-center opacity-70">
                No encontramos productos. Probá con otra búsqueda o mirá todo el catálogo.
            </p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('landings.clasico.product-card')
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="mt-10 flex items-center justify-between text-sm" aria-label="Paginación">
                    @if ($products->previousPageUrl())
                        <a href="{{ $products->previousPageUrl() }}" rel="prev" class="rounded-full border border-ink/20 px-5 py-2 hover:border-brand hover:text-brand">&larr; Anterior</a>
                    @else
                        <span></span>
                    @endif

                    <span class="opacity-70">Página {{ $products->currentPage() }} de {{ $products->lastPage() }}</span>

                    @if ($products->nextPageUrl())
                        <a href="{{ $products->nextPageUrl() }}" rel="next" class="rounded-full border border-ink/20 px-5 py-2 hover:border-brand hover:text-brand">Siguiente &rarr;</a>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
