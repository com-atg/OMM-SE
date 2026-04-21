<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" style="font-family: 'Instrument Sans', system-ui, sans-serif;">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">User Management</h1>
                <p class="mt-1 text-sm text-slate-500">Assign roles and REDCap record mappings. Service-only.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Back to overview</a>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 ring-1 ring-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">REDCap ID</th>
                        <th class="px-4 py-3 text-left">Last Login</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $u)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $u->email }}</td>
                            <td class="px-4 py-3">{{ $u->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    @class([
                                        'bg-purple-100 text-purple-800' => $u->isService(),
                                        'bg-blue-100 text-blue-800' => $u->isAdmin(),
                                        'bg-slate-100 text-slate-700' => $u->isStudent(),
                                    ])">
                                    {{ $u->role?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $u->redcap_record_id ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('admin.users.edit', $u) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                                @if ($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline" onsubmit="return confirm('Delete {{ $u->email }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No users.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if ($trashedUsers->isNotEmpty())
            <h2 class="mt-10 mb-3 text-base font-semibold text-slate-700">Deleted users</h2>
            <section class="bg-white rounded-xl shadow-sm ring-1 ring-red-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-red-50 text-xs uppercase tracking-wide text-red-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Role</th>
                            <th class="px-4 py-3 text-left">Deleted</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($trashedUsers as $u)
                            <tr class="opacity-60">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $u->email }}</td>
                                <td class="px-4 py-3">{{ $u->name }}</td>
                                <td class="px-4 py-3">{{ $u->role?->label() }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $u->deleted_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.users.restore', $u->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-800">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>
</body>
</html>
