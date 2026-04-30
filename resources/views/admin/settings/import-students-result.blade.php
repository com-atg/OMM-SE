@php
    $createdCount = count($created);
    $updatedCount = count($updated);
    $missingCount = count($missingEmail);
    $subheading = "Scholars for {$projectMapping->displayName()} were imported from the OMM ACE List REDCap project.";
@endphp

<x-app-shell
    title="Import Scholars"
    active="settings"
    eyebrow="Service Settings"
    heading="Scholar Users Imported"
    :subheading="$subheading"
    width="wide"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.settings.index') }}" variant="ghost" icon="arrow-left">Back to settings</flux:button>
        <flux:button href="{{ route('admin.settings.project-mappings.import-students', $projectMapping) }}" variant="ghost" icon="arrow-path">Re-run import</flux:button>
    </x-slot:headerActions>

    <div class="rounded-lg border border-sky-200 p-5 shadow-sm" style="background-color: #f0f9ff;">
        <div class="flex items-start gap-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700">
                <flux:icon.users />
            </span>
            <div class="min-w-0">
                <h2 class="text-base font-bold text-sky-950">Scholar users imported from OMM ACE List</h2>
                <p class="mt-1 text-sm leading-6 text-sky-900">
                    Pulled the destination roster from {{ $projectMapping->displayName() }}, creating new student-role users
                    and refreshing existing users with their latest <span class="font-mono">batch</span>
                    and <span class="font-mono">is_active</span> values.
                </p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <flux:callout icon="check-circle" color="emerald">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Fetched from REDCap</div>
            <p class="mt-2 text-3xl font-bold text-slate-950">{{ $totalFetched }}</p>
            <p class="mt-1 text-xs text-slate-500">Total destination roster records</p>
        </div>

        <div class="rounded-lg border border-emerald-100 bg-emerald-50/70 p-5 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">Created</div>
            <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $createdCount }}</p>
            <p class="mt-1 text-xs text-emerald-700">New users with student role</p>
        </div>

        <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-5 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-sky-700">Updated</div>
            <p class="mt-2 text-3xl font-bold text-sky-900">{{ $updatedCount }}</p>
            <p class="mt-1 text-xs text-sky-700">Refreshed batch &amp; is_active</p>
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-amber-700">Missing Email</div>
            <p class="mt-2 text-3xl font-bold text-amber-900">{{ $missingCount }}</p>
            <p class="mt-1 text-xs text-amber-700">Could not be imported</p>
        </div>
    </section>

    @if ($createdCount > 0)
        <section class="rounded-lg border border-white/80 bg-white/86 shadow-sm">
            <div class="border-b border-slate-200/80 p-5">
                <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Imported</div>
                <h2 class="mt-1 text-lg font-bold text-slate-950">Newly Created Users ({{ $createdCount }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="bg-slate-50/80 text-[0.7rem] uppercase tracking-[0.24em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Name</th>
                            <th class="px-5 py-3 text-left">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($created as $row)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ $row['name'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row['email'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($updatedCount > 0)
        <section class="rounded-lg border border-sky-200 bg-white/82 shadow-sm">
            <div class="border-b border-sky-200/80 p-5">
                <div class="text-xs font-bold uppercase tracking-[0.26em] text-sky-600">Updated</div>
                <h2 class="mt-1 text-lg font-bold text-slate-800">Refreshed Existing Users ({{ $updatedCount }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="bg-slate-50/80 text-[0.7rem] uppercase tracking-[0.24em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Name</th>
                            <th class="px-5 py-3 text-left">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($updated as $row)
                            <tr>
                                <td class="px-5 py-3 text-slate-700">{{ $row['name'] }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $row['email'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($missingCount > 0)
        <section class="rounded-lg border border-amber-200 bg-amber-50/40 shadow-sm">
            <div class="border-b border-amber-200/80 p-5">
                <div class="text-xs font-bold uppercase tracking-[0.26em] text-amber-700">Action Needed</div>
                <h2 class="mt-1 text-lg font-bold text-amber-900">Records Missing Email ({{ $missingCount }})</h2>
                <p class="mt-1 text-xs text-amber-800">These REDCap records have no email and were not imported. Update them in REDCap and re-run the import.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="bg-amber-100/40 text-[0.7rem] uppercase tracking-[0.24em] text-amber-700">
                        <tr>
                            <th class="px-5 py-3 text-left">REDCap Record ID</th>
                            <th class="px-5 py-3 text-left">Name</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100">
                        @foreach ($missingEmail as $row)
                            <tr>
                                <td class="px-5 py-3 font-mono text-xs text-amber-900">{{ $row['record_id'] }}</td>
                                <td class="px-5 py-3 text-amber-800">{{ $row['name'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($totalFetched === 0)
        <section class="rounded-lg border border-slate-200 bg-white/82 p-8 text-center text-sm text-slate-500">
            No scholars were found in the destination REDCap project. Confirm the roster has records, then re-run the import.
        </section>
    @endif
</x-app-shell>
