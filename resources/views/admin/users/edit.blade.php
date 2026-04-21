<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit {{ $user->email }} — {{ config('app.name') }}</title>
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
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit User</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Last login: {{ $user->last_login_at?->diffForHumans() ?? 'never' }}
                    · Created {{ $user->created_at?->toFormattedDateString() }}
                </p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Back to users</a>
        </header>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-6">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Email</label>
                    <div class="flex items-center gap-2 text-sm text-slate-800 bg-slate-50 rounded-md px-3 py-2 ring-1 ring-slate-200">
                        <span>{{ $user->email }}</span>
                        <span class="ml-auto inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            @if($user->isService()) bg-purple-100 text-purple-800
                            @elseif($user->isAdmin()) bg-blue-100 text-blue-800
                            @else bg-teal-50 text-teal-800
                            @endif">
                            {{ $user->role?->label() }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Email is set by Okta SAML and cannot be changed here.</p>
                </div>

                <div>
                    <label for="name" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Display Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none @error('name') border-red-400 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="role" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Role</label>
                    <select name="role" id="role" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none @error('role') border-red-400 @enderror">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="redcap_record_id" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">REDCap Record ID</label>
                    <input type="text" name="redcap_record_id" id="redcap_record_id"
                        value="{{ old('redcap_record_id', $user->redcap_record_id) }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none @error('redcap_record_id') border-red-400 @enderror">
                    <p class="mt-1 text-xs text-slate-500">Auto-populated on student login; clear to force a re-lookup.</p>
                    @error('redcap_record_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="pt-2 flex items-center justify-between gap-3">
                    @if ($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                              onsubmit="return confirm('Delete {{ addslashes($user->email) }}? This can be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 underline underline-offset-4">
                                Delete user
                            </button>
                        </form>
                    @else
                        <span></span>
                    @endif
                    <div class="flex gap-3">
                        <a href="{{ route('admin.users.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 transition-colors">
                            Save changes
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
