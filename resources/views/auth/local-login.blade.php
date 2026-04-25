<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local Login — {{ config('app.name', 'OMM Student Evaluations') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" style="font-family: 'Instrument Sans', system-ui, sans-serif;">
    <div class="min-h-screen flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="max-w-lg w-full space-y-6">

            {{-- Header --}}
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold text-amber-800">Local development only</p>
                    <p class="text-sm text-amber-700 mt-0.5">This login page is not available in production.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 divide-y divide-slate-100">

                {{-- Existing users --}}
                @if ($users->isNotEmpty())
                    <div class="px-6 py-5">
                        <h2 class="text-sm font-semibold text-slate-700 mb-3">Sign in as existing user</h2>
                        <div class="space-y-2">
                            @foreach ($users as $user)
                                <form method="POST" action="{{ route('local.login.post') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                                    <button type="submit" class="w-full flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-left hover:bg-slate-50 transition-colors">
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">{{ $user->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                        <span class="ml-3 shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                            @if ($user->role->value === 'service') bg-violet-100 text-violet-700
                                            @elseif ($user->role->value === 'admin') bg-blue-100 text-blue-700
                                            @elseif ($user->role->value === 'faculty') bg-teal-100 text-teal-700
                                            @else bg-slate-100 text-slate-600
                                            @endif">
                                            {{ $user->role->label() }}
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Quick-create stub users --}}
                <div class="px-6 py-5">
                    <h2 class="text-sm font-semibold text-slate-700 mb-3">Quick login with stub user</h2>
                    <p class="text-xs text-slate-500 mb-3">Creates <code class="bg-slate-100 px-1 rounded">local-{role}@local.test</code> if it doesn't exist.</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['service' => ['label' => 'Service', 'class' => 'bg-violet-600 hover:bg-violet-500'], 'admin' => ['label' => 'Admin', 'class' => 'bg-blue-600 hover:bg-blue-500'], 'faculty' => ['label' => 'Faculty', 'class' => 'bg-teal-600 hover:bg-teal-500'], 'student' => ['label' => 'Student', 'class' => 'bg-slate-700 hover:bg-slate-600']] as $role => $cfg)
                            <form method="POST" action="{{ route('local.login.post') }}">
                                @csrf
                                <input type="hidden" name="role" value="{{ $role }}">
                                <button type="submit" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-white transition-colors {{ $cfg['class'] }}">
                                    {{ $cfg['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
