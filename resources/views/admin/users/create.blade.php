<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add User — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" style="font-family: 'Instrument Sans', system-ui, sans-serif;">
    @include('partials.impersonation-banner')

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Add User</h1>
                <p class="mt-1 text-sm text-slate-500">Manually create a user. They'll log in via Okta SAML on first sign-in.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Back to users</a>
        </header>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-6">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none @error('email') border-red-400 @enderror">
                    <p class="mt-1 text-xs text-slate-500">Must match the user's Okta / institutional email exactly.</p>
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="name" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Display Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none @error('name') border-red-400 @enderror">
                    <p class="mt-1 text-xs text-slate-500">Overwritten by Okta display name on first login.</p>
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="role" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Role <span class="text-red-500">*</span></label>
                    <select name="role" id="role" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none @error('role') border-red-400 @enderror">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        <strong>Service</strong> — full access including user management.
                        <strong>Admin</strong> — view all scholars.
                        <strong>Student</strong> — view own evaluations only.
                    </p>
                    @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="pt-2 flex justify-end gap-3">
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 transition-colors">
                        Create user
                    </button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
