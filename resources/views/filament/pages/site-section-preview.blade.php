<!DOCTYPE html>
<html lang="es" data-theme="{{ $themeKey }}">
<head>
    @include('landings.partials.head')
    <style>
        a, button, form { pointer-events: none; }
    </style>
</head>
<body class="antialiased">
    @if ($previewSection === 'business')
        @include('landings.'.$themeKey.'.header')
        @if ($siteContent->sectionEnabled('hero'))
            <main>
                @include('landings.'.$themeKey.'.hero')
            </main>
        @endif
        @include('landings.'.$themeKey.'.footer')
    @else
        <main>
            @includeFirst(['landings.'.$themeKey.'.'.$previewSection, 'landings.partials.'.$previewSection])
        </main>
        @unless (in_array($previewSection, $sections, true))
            <p class="px-4 py-6 text-center text-sm opacity-70">
                Esta sección todavía no tiene contenido suficiente para aparecer en el sitio.
            </p>
        @endunless
    @endif
</body>
</html>
