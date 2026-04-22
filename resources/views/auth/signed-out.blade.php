<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signed out — {{ config('app.name', 'OMM Scholar Evaluations') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" style="font-family: 'Instrument Sans', system-ui, sans-serif;">
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-lg w-full bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-10 text-center">
            <div class="mx-auto mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">
                You've been signed out
            </h1>
            <p class="mt-3 text-sm text-slate-600">
                You have successfully signed out of OMM Scholar Evaluations.
            </p>
            <div class="mt-8">
                <a href="{{ route('saml.login') }}" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Sign in again
                </a>
            </div>
        </div>
    </div>
</body>
</html>
