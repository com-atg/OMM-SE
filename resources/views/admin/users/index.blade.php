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
    @include('partials.impersonation-banner')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Stats bar --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl ring-1 ring-slate-200 px-5 py-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">Service</p>
                <p class="text-3xl font-semibold text-purple-700">{{ $counts['service'] }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-slate-200 px-5 py-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">Admin</p>
                <p class="text-3xl font-semibold text-blue-700">{{ $counts['admin'] }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-slate-200 px-5 py-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">Student</p>
                <p class="text-3xl font-semibold text-teal-700">{{ $counts['student'] }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-slate-200 px-5 py-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">Deleted</p>
                <p class="text-3xl font-semibold text-slate-400">{{ $counts['deleted'] }}</p>
            </div>
        </div>

        {{-- Header --}}
        <header class="mb-6 flex items-start justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">User Management</h1>
                <p class="mt-1 text-sm text-slate-500">Manage roles, REDCap mappings, and access. Service accounts only.</p>
            </div>
            <div class="flex items-center flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.users.import') }}"
                      onsubmit="return confirm('Import all scholar records from REDCap as Student users? Existing users will be skipped.')">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Import from REDCap
                    </button>
                </form>
                <a href="{{ route('admin.users.create') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add User
                </a>
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Dashboard</a>
            </div>
        </header>

        {{-- Status message --}}
        @if (session('status'))
            <div class="mb-5 flex items-center gap-2.5 rounded-lg bg-green-50 ring-1 ring-green-200 px-4 py-3 text-sm text-green-800">
                <svg class="h-4 w-4 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ session('status') }}
            </div>
        @endif

        {{-- Controls: filter tabs + search --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-1.5 flex-wrap" id="filterTabs">
                <button data-filter="all"
                    class="filter-btn rounded-lg px-3 py-1.5 text-xs font-semibold bg-slate-900 text-white transition-colors">
                    All ({{ $users->count() }})
                </button>
                <button data-filter="service"
                    class="filter-btn rounded-lg px-3 py-1.5 text-xs font-semibold bg-white ring-1 ring-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                    Service ({{ $counts['service'] }})
                </button>
                <button data-filter="admin"
                    class="filter-btn rounded-lg px-3 py-1.5 text-xs font-semibold bg-white ring-1 ring-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                    Admin ({{ $counts['admin'] }})
                </button>
                <button data-filter="student"
                    class="filter-btn rounded-lg px-3 py-1.5 text-xs font-semibold bg-white ring-1 ring-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                    Student ({{ $counts['student'] }})
                </button>
            </div>
            <input type="search" id="userSearch" placeholder="Search name or email…"
                class="w-full sm:w-64 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none">
        </div>

        {{-- Main table --}}
        <div class="bg-white rounded-xl ring-1 ring-slate-200 overflow-hidden">
            <table class="w-full text-sm" id="userTable">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3 text-left">User</th>
                        <th class="px-5 py-3 text-left">Role</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">REDCap Record</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Last Login</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="userTableBody">
                    @forelse ($users as $u)
                        <tr data-role="{{ $u->role?->value }}"
                            data-search="{{ strtolower($u->email . ' ' . $u->name) }}"
                            class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 shrink-0 rounded-full flex items-center justify-center text-sm font-semibold select-none
                                        @if($u->isService()) bg-purple-100 text-purple-700
                                        @elseif($u->isAdmin()) bg-blue-100 text-blue-700
                                        @else bg-teal-100 text-teal-700
                                        @endif">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-slate-900 truncate">{{ $u->name }}</div>
                                        <div class="text-xs text-slate-500 truncate">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @if($u->isService()) bg-purple-100 text-purple-800
                                    @elseif($u->isAdmin()) bg-blue-100 text-blue-800
                                    @else bg-teal-50 text-teal-800
                                    @endif">
                                    {{ $u->role?->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 hidden sm:table-cell">
                                {{ $u->redcap_record_id ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 hidden md:table-cell">
                                {{ $u->last_login_at?->diffForHumans() ?? 'never' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.users.edit', $u) }}"
                                        class="rounded-md px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 transition-colors">
                                        Edit
                                    </a>
                                    @if (!$u->isService() && $u->id !== auth()->id() && !session('impersonating_original_id'))
                                        <form method="POST" action="{{ route('admin.users.impersonate', $u) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="rounded-md px-2.5 py-1 text-xs font-medium text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50 transition-colors">
                                                Impersonate
                                            </button>
                                        </form>
                                    @endif
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline"
                                              onsubmit="return confirm('Delete {{ addslashes($u->email) }}? This can be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="rounded-md px-2.5 py-1 text-xs font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50 transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            {{-- Empty search state --}}
            <div id="noResults" class="hidden px-5 py-12 text-center text-slate-400 text-sm">
                No users match your search.
            </div>
        </div>

        {{-- Deleted users (collapsible) --}}
        @if ($trashedUsers->isNotEmpty())
            <details class="mt-8 group">
                <summary class="flex items-center gap-2 cursor-pointer list-none mb-3 select-none">
                    <svg class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                    <span class="text-sm font-semibold text-slate-500">Deleted users ({{ $counts['deleted'] }})</span>
                </summary>
                <div class="bg-white rounded-xl ring-1 ring-red-100 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-red-50 text-xs uppercase tracking-wide text-red-400 border-b border-red-100">
                            <tr>
                                <th class="px-5 py-3 text-left">User</th>
                                <th class="px-5 py-3 text-left">Role</th>
                                <th class="px-5 py-3 text-left hidden sm:table-cell">Deleted</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($trashedUsers as $u)
                                <tr class="opacity-60">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 shrink-0 rounded-full bg-slate-100 flex items-center justify-center text-sm font-semibold text-slate-500 select-none">
                                                {{ strtoupper(substr($u->name, 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-slate-700 truncate">{{ $u->name }}</div>
                                                <div class="text-xs text-slate-400 truncate">{{ $u->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-500">{{ $u->role?->label() }}</td>
                                    <td class="px-5 py-3.5 text-slate-400 hidden sm:table-cell">{{ $u->deleted_at?->diffForHumans() }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        <form method="POST" action="{{ route('admin.users.restore', $u->id) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="rounded-md px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200 hover:bg-green-50 transition-colors">
                                                Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>

    <script>
        const searchInput = document.getElementById('userSearch');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const rows = document.querySelectorAll('#userTableBody tr[data-role]');
        const noResults = document.getElementById('noResults');
        let activeFilter = 'all';

        function applyFilters() {
            const q = searchInput.value.toLowerCase().trim();
            let visible = 0;

            rows.forEach(row => {
                const matchesFilter = activeFilter === 'all' || row.dataset.role === activeFilter;
                const matchesSearch = !q || row.dataset.search.includes(q);
                const show = matchesFilter && matchesSearch;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            noResults.classList.toggle('hidden', visible > 0);
        }

        searchInput.addEventListener('input', applyFilters);

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => {
                    b.classList.remove('bg-slate-900', 'text-white');
                    b.classList.add('bg-white', 'ring-1', 'ring-slate-200', 'text-slate-600');
                });
                btn.classList.add('bg-slate-900', 'text-white');
                btn.classList.remove('bg-white', 'ring-1', 'ring-slate-200', 'text-slate-600');
                activeFilter = btn.dataset.filter;
                applyFilters();
            });
        });
    </script>
</body>
</html>
