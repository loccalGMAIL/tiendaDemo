<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-6 px-4 text-center">
        <h1 class="text-4xl font-semibold">{{ config('app.name') }}</h1>
        <a href="{{ $panelUrl }}" class="rounded-lg bg-gray-900 px-5 py-2.5 text-white hover:bg-gray-700">
            Ingresar al sistema
        </a>
    </main>
</body>
</html>
