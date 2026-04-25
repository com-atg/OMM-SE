@props([
    'active' => 'dashboard',
    'eyebrow' => null,
    'heading' => null,
    'subheading' => null,
    'title' => config('app.name', 'OMM Student Evaluations'),
    'width' => '7xl',
])

@php
    $containerClass = match ($width) {
        'narrow' => 'max-w-3xl',
        'wide' => 'max-w-7xl',
        default => 'max-w-6xl',
    };
    $user = auth()->user();
    $showDashboardLink = $user?->canViewDashboard() ?? false;
    $showStudentLink = $user?->isStudent() || $user?->canViewAllStudents();
    $showFacultyLink = $user?->canViewFacultyDetail() ?? false;

    $navLink = function (string $section) use ($active): string {
        return $active === $section
            ? 'bg-amber-100 text-amber-950 shadow-sm ring-1 ring-amber-200'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950';
    };

    $hasViteBuild = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    $configuredAppPath = app()->isProduction()
        ? trim((string) parse_url(config('app.url'), PHP_URL_PATH), '/')
        : '';
    $requestBasePath = trim(request()->getBaseUrl(), '/');
    $appPath = $requestBasePath !== '' ? $requestBasePath : $configuredAppPath;
    $livewireEndpoint = trim(app('livewire')->getUriPrefix(), '/');
    $livewireBrowserEndpoint = '/'.trim($appPath.'/'.$livewireEndpoint, '/');
    $livewireProgressBar = config('livewire.navigate.show_progress_bar', true) ? '' : 'data-no-progress-bar';
    $livewireScriptConfig = [
        'csrf' => app()->has('session.store') ? csrf_token() : '',
        'uri' => $livewireBrowserEndpoint.'/update',
        'moduleUrl' => $livewireBrowserEndpoint,
        'progressBar' => $livewireProgressBar,
        'nonce' => \Illuminate\Support\Facades\Vite::cspNonce() ?? '',
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name', 'OMM Student Evaluations') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">

    @livewireScriptConfig
    <script data-navigate-once="true">window.livewireScriptConfig = @js($livewireScriptConfig);</script>

    @if ($hasViteBuild)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="{{ route('runtime.flux-styles') }}">
        <script type="module" data-navigate-once>
            import { Livewire, Alpine } from @js(route('runtime.livewire'));
            import @js(route('runtime.flux'));
            import { bootStudentDetailCharts } from @js(route('runtime.student-detail-charts'));

            window.Alpine = Alpine;
            window.Livewire = Livewire;

            bootStudentDetailCharts(Livewire);
            Livewire.start();
        </script>
    @endif

    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-[#eef3fb] font-sans text-slate-900 antialiased">
    @include('partials.impersonation-banner')

    <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.98),rgba(239,246,255,0.88)_34%,rgba(226,232,240,0.78)_100%)]">
        <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/92 backdrop-blur-xl">
            <nav class="mx-auto flex h-16 max-w-7xl items-center gap-5 px-4 sm:px-6 lg:px-8" aria-label="Primary navigation">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-950 text-white shadow-sm">
                        <flux:icon.academic-cap variant="mini" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-extrabold tracking-tight">
                            <span class="text-amber-600">NYITCOM</span>
                            <span class="text-slate-950">OMM ACE</span>
                        </span>
                        <span class="block truncate text-xs font-medium text-slate-500">Student Evaluations</span>
                    </span>
                </a>

                <div class="hidden items-center gap-1 lg:flex">
                    @if ($showDashboardLink)
                        <a href="{{ route('dashboard') }}" class="{{ $navLink('dashboard') }} rounded-lg px-3 py-2 text-sm font-semibold transition">
                            Dashboard
                        </a>
                    @endif
                    @if ($showStudentLink)
                        <a href="{{ route('student') }}" class="{{ $navLink('students') }} rounded-lg px-3 py-2 text-sm font-semibold transition">
                            Students
                        </a>
                    @endif
                    @if ($showFacultyLink)
                        <a href="{{ route('faculty') }}" class="{{ $navLink('faculty') }} rounded-lg px-3 py-2 text-sm font-semibold transition">
                            Faculty
                        </a>
                    @endif
                    @can('manage-users')
                        <a href="{{ route('admin.users.index') }}" class="{{ $navLink('users') }} rounded-lg px-3 py-2 text-sm font-semibold transition">
                            Users
                        </a>
                    @endcan
                    @can('manage-settings')
                        <a href="{{ route('admin.settings.index') }}" class="{{ $navLink('settings') }} rounded-lg px-3 py-2 text-sm font-semibold transition">
                            Settings
                        </a>
                    @endcan
                </div>

                <div class="ml-auto hidden items-center gap-2 md:flex">
                    {{ $navActions ?? '' }}

                    <form method="POST" action="{{ route('saml.logout') }}">
                        @csrf
                        <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle">
                            Sign out
                        </flux:button>
                    </form>
                </div>

                <flux:dropdown class="md:hidden" position="bottom" align="end">
                    <flux:button variant="ghost" icon="bars-3" inset="top bottom" aria-label="Open navigation" />

                    <flux:menu>
                        @if ($showDashboardLink)
                            <flux:menu.item href="{{ route('dashboard') }}" icon="chart-pie">Dashboard</flux:menu.item>
                        @endif
                        @if ($showStudentLink)
                            <flux:menu.item href="{{ route('student') }}" icon="users">Students</flux:menu.item>
                        @endif
                        @if ($showFacultyLink)
                            <flux:menu.item href="{{ route('faculty') }}" icon="identification">Faculty</flux:menu.item>
                        @endif
                        @can('manage-users')
                            <flux:menu.item href="{{ route('admin.users.index') }}" icon="shield-check">Users</flux:menu.item>
                        @endcan
                        @can('manage-settings')
                            <flux:menu.item href="{{ route('admin.settings.index') }}" icon="cog-6-tooth">Settings</flux:menu.item>
                        @endcan
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('saml.logout') }}">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle">Sign out</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </nav>
        </header>

        <main class="mx-auto flex w-full {{ $containerClass }} flex-col gap-7 px-4 py-8 sm:px-6 lg:px-8">
            @if ($heading || $eyebrow || $subheading || isset($headerActions))
                <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        @if ($eyebrow)
                            <div class="mb-3 inline-flex rounded-full border border-sky-200 bg-white/72 px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.34em] text-sky-700 shadow-sm">
                                {{ $eyebrow }}
                            </div>
                        @endif

                        @if ($heading)
                            <h1 class="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{{ $heading }}</h1>
                        @endif

                        @if ($subheading)
                            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">{{ $subheading }}</p>
                        @endif
                    </div>

                    @isset($headerActions)
                        <div class="flex shrink-0 flex-wrap gap-2">
                            {{ $headerActions }}
                        </div>
                    @endisset
                </section>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{ $scripts ?? '' }}
</body>
</html>
