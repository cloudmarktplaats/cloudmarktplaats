<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Cloudmarktplaats' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="border-b bg-white">
        <nav class="container mx-auto flex items-center justify-between py-3">
            <a href="/" class="font-bold">cloudmarktplaats<span class="text-gray-400">.nl</span></a>
            <div class="space-x-4 text-sm">
                @auth
                    <span>{{ auth()->user()->display_name }}</span>
                    <form method="POST" action="/logout" class="inline">@csrf <button>Logout</button></form>
                @else
                    <a href="/login">Login</a>
                    <a href="/register">Register</a>
                @endauth
            </div>
        </nav>
    </header>
    <main class="container mx-auto py-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    @livewireScripts
</body>
</html>
