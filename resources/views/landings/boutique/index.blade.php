<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="boutique">
<head>
    @include('landings.partials.head')
</head>
<body class="antialiased">
    @include('landings.boutique.header')

    <main>
        @foreach ($sections as $section)
            @includeFirst(['landings.boutique.'.$section, 'landings.partials.'.$section])
        @endforeach
    </main>

    @include('landings.boutique.footer')
</body>
</html>
