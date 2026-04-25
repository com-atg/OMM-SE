<x-app-shell
    title="User Management"
    active="users"
    eyebrow="System Access"
    heading="User Management"
    subheading="Manage service accounts, admin access, faculty accounts, student mappings, and REDCap roster imports."
    width="wide"
>
    <x-slot:headerActions>
        <form method="POST" action="{{ route('admin.users.import') }}" onsubmit="return confirm('Import all student records from REDCap as Student users? Existing users will be skipped.')">
            @csrf
            <flux:button type="submit" variant="ghost" icon="arrow-down-tray">Import from REDCap</flux:button>
        </form>

        @livewire('admin.csv-user-import')

        <flux:button href="{{ route('admin.users.create') }}" variant="primary" icon="plus">Add User</flux:button>
    </x-slot:headerActions>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Service</div>
            <div class="mt-3 text-3xl font-bold text-violet-700 tabular-nums">{{ $counts['service'] }}</div>
        </div>
        <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Admin</div>
            <div class="mt-3 text-3xl font-bold text-sky-700 tabular-nums">{{ $counts['admin'] }}</div>
        </div>
        <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Student</div>
            <div class="mt-3 text-3xl font-bold text-emerald-700 tabular-nums">{{ $counts['student'] }}</div>
        </div>
        <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Faculty</div>
            <div class="mt-3 text-3xl font-bold text-teal-700 tabular-nums">{{ $counts['faculty'] }}</div>
        </div>
        <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Deleted</div>
            <div class="mt-3 text-3xl font-bold text-slate-500 tabular-nums">{{ $counts['deleted'] }}</div>
        </div>
    </section>

    @if (session('status'))
        <flux:callout icon="check-circle" color="emerald">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <section class="rounded-lg border border-white/80 bg-white/86 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <div class="flex flex-col gap-3 border-b border-slate-200/80 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2" id="filterTabs">
                <button data-filter="all" class="filter-btn rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white transition">
                    All ({{ $users->count() }})
                </button>
                <button data-filter="service" class="filter-btn rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">
                    Service ({{ $counts['service'] }})
                </button>
                <button data-filter="admin" class="filter-btn rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">
                    Admin ({{ $counts['admin'] }})
                </button>
                <button data-filter="faculty" class="filter-btn rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">
                    Faculty ({{ $counts['faculty'] }})
                </button>
                <button data-filter="student" class="filter-btn rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">
                    Student ({{ $counts['student'] }})
                </button>
            </div>

            <label class="relative w-full sm:w-72">
                <span class="sr-only">Search users</span>
                <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    id="userSearch"
                    placeholder="Search name or email"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm text-slate-800 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                >
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm" id="userTable">
                <thead class="border-b border-slate-200/80 bg-slate-50/80 text-[0.7rem] uppercase tracking-[0.24em] text-slate-500">
                    <tr>
                        <th class="py-3 pl-8 pr-5 text-center">User</th>
                        <th class="px-5 py-3 text-center">Role</th>
                        <th class="px-5 py-3 text-center">REDCap Record</th>
                        <th class="px-5 py-3 text-center">Last Login</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="userTableBody">
                    @forelse ($users as $u)
                        <tr data-role="{{ $u->role?->value }}" data-search="{{ strtolower($u->email.' '.$u->name) }}" class="transition hover:bg-sky-50/55">
                            <td class="py-4 pl-8 pr-5">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-10 shrink-0 place-items-center rounded-lg text-sm font-bold
                                        @if ($u->isService()) bg-violet-100 text-violet-700
                                        @elseif ($u->isAdmin()) bg-sky-100 text-sky-700
                                        @elseif ($u->isFaculty()) bg-teal-100 text-teal-700
                                        @else bg-emerald-100 text-emerald-700
                                        @endif">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-slate-950">{{ $u->name }}</div>
                                        <div class="truncate text-xs text-slate-500">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold
                                    @if ($u->isService()) bg-violet-100 text-violet-800
                                    @elseif ($u->isAdmin()) bg-sky-100 text-sky-800
                                    @elseif ($u->isFaculty()) bg-teal-100 text-teal-800
                                    @else bg-emerald-100 text-emerald-800
                                    @endif">
                                    {{ $u->role?->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center text-slate-600">{{ $u->redcap_record_id ?? '-' }}</td>
                            <td class="px-5 py-4 text-center text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-center">
                                    <flux:dropdown position="bottom end">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="ellipsis-horizontal"
                                            aria-label="User actions"
                                            class="text-slate-400 hover:text-slate-700 hover:bg-slate-100"
                                        />
                                        <flux:menu class="min-w-[160px]">
                                            <flux:menu.heading>{{ $u->name }}</flux:menu.heading>
                                            <flux:menu.separator />
                                            <flux:menu.item href="{{ route('admin.users.edit', $u) }}" icon="pencil-square">Edit</flux:menu.item>

                                            @if (! $u->isService() && $u->id !== auth()->id() && ! session('impersonating_original_id'))
                                                <form method="POST" action="{{ route('admin.users.impersonate', $u) }}">
                                                    @csrf
                                                    <flux:menu.item type="submit" icon="user-circle">Impersonate</flux:menu.item>
                                                </form>
                                            @endif

                                            @if ($u->id !== auth()->id())
                                                <flux:menu.separator />
                                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Delete {{ $u->name }}? This can be undone.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <flux:menu.item type="submit" variant="danger" icon="trash">Delete</flux:menu.item>
                                                </form>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="noResults" class="hidden px-5 py-12 text-center text-sm text-slate-500">
            No users match your search.
        </div>
    </section>

    @if ($trashedUsers->isNotEmpty())
        <details class="group rounded-lg border border-red-100 bg-white/82 shadow-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                <span class="text-sm font-bold text-slate-700">Deleted users ({{ $counts['deleted'] }})</span>
                <flux:icon.chevron-right class="size-4 text-slate-400 transition group-open:rotate-90" />
            </summary>

            <div class="overflow-x-auto border-t border-red-100">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-red-50/80 text-[0.7rem] uppercase tracking-[0.24em] text-red-500">
                        <tr>
                            <th class="py-3 pl-8 pr-5 text-center">User</th>
                            <th class="px-5 py-3 text-center">Role</th>
                            <th class="px-5 py-3 text-center">Deleted</th>
                            <th class="px-5 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($trashedUsers as $u)
                            <tr class="opacity-70">
                                <td class="py-4 pl-8 pr-5">
                                    <div class="flex items-center gap-3">
                                        <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-sm font-bold text-slate-500">
                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-slate-800">{{ $u->name }}</div>
                                            <div class="truncate text-xs text-slate-500">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center text-slate-600">{{ $u->role?->label() }}</td>
                                <td class="px-5 py-4 text-center text-slate-500">{{ $u->deleted_at?->diffForHumans() }}</td>
                                <td class="px-5 py-4 text-center">
                                    <form method="POST" action="{{ route('admin.users.restore', $u->id) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="ghost">Restore</flux:button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    <x-slot:scripts>
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

                    if (show) {
                        visible++;
                    }
                });

                noResults.classList.toggle('hidden', visible > 0);
            }

            searchInput.addEventListener('input', applyFilters);

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(tab => {
                        tab.classList.remove('bg-slate-950', 'text-white');
                        tab.classList.add('bg-white', 'ring-1', 'ring-slate-200', 'text-slate-600');
                    });

                    btn.classList.add('bg-slate-950', 'text-white');
                    btn.classList.remove('bg-white', 'ring-1', 'ring-slate-200', 'text-slate-600');
                    activeFilter = btn.dataset.filter;
                    applyFilters();
                });
            });
        </script>
    </x-slot:scripts>
</x-app-shell>
