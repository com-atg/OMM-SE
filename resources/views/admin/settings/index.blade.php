<x-app-shell
    title="Settings"
    active="settings"
    eyebrow="Application Settings"
    heading="Settings"
    subheading="Review the application current project and maintain REDCap project mappings."
    width="wide"
>
    @if (session('status'))
        <flux:callout icon="check-circle" color="emerald">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <section class="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700">
                    <flux:icon.academic-cap />
                </span>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Application</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Current Project</h2>

                    @if ($currentProject)
                        <p class="mt-3 text-lg font-semibold text-slate-800">{{ $currentProject->displayName() }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">This is informational and is automatically determined by the largest active graduation year.</p>
                    @else
                        <p class="mt-3 text-sm leading-6 text-slate-500">No active project mappings exist yet. Add one to populate the current project.</p>
                    @endif
                </div>
            </div>
        </div>

        @can('manage-settings-records')
            <div class="rounded-lg border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur" style="background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);">
                <div class="flex items-start gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700">
                        <flux:icon.plus />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Onboarding</div>
                        <h2 class="mt-2 text-xl font-bold text-slate-950">Add a New Academic Year</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Walk through the REDCap prep steps and enter the new project mapping in one guided flow.</p>
                        <div class="mt-4">
                            <flux:button href="{{ route('admin.settings.new-academic-year') }}" variant="primary" icon="academic-cap">Start guided setup</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    </section>

    <section class="rounded-lg border border-white/80 bg-white/86 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 p-5">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Project Settings</div>
                <h2 class="mt-1 text-xl font-bold text-slate-950">Project Mapping</h2>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $projectMappings->count() }} active</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="border-b border-slate-200/80 bg-slate-50/80 text-[0.7rem] uppercase tracking-[0.24em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3 text-left">Academic Year</th>
                        <th class="px-5 py-3 text-left">Graduating Year</th>
                        <th class="px-5 py-3 text-left">REDCap PID</th>
                        <th class="px-5 py-3 text-left">Token</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($projectMappings as $projectMapping)
                        <tr class="transition hover:bg-sky-50/55">
                            <td class="px-5 py-4 font-semibold text-slate-950">{{ $projectMapping->academic_year }}</td>
                            <td class="px-5 py-4 text-slate-700">Class of {{ $projectMapping->graduation_year }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $projectMapping->redcap_pid }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ $projectMapping->maskedToken() }}</td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.settings.project-mappings.process', $projectMapping) }}" onsubmit="return confirm('Re-process this whole project now?')">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="ghost" icon="arrow-path">Re-process</flux:button>
                                    </form>

                                    @can('manage-settings-records')
                                        <flux:button href="{{ route('admin.settings.project-mappings.import-students', $projectMapping) }}" size="sm" variant="ghost" icon="user-plus" title="Import scholars for this graduating year">Import</flux:button>

                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="scale"
                                            title="Edit category weights"
                                            onclick="Livewire.dispatch('open-weights', { id: {{ $projectMapping->id }} })"
                                        >Weights</flux:button>

                                        <flux:button href="{{ route('admin.settings.project-mappings.edit', $projectMapping) }}" size="sm" variant="ghost" icon="pencil-square" aria-label="Edit project mapping" title="Edit project mapping" />

                                        <form method="POST" action="{{ route('admin.settings.project-mappings.destroy', $projectMapping) }}" onsubmit="return confirm('Delete this project mapping? This can be restored.')">
                                            @csrf
                                            @method('DELETE')
                                            <flux:button type="submit" size="sm" variant="danger" icon="trash" aria-label="Delete project mapping" title="Delete project mapping" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">No project mappings found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($trashedProjectMappings->isNotEmpty())
        <details class="group rounded-lg border border-red-100 bg-white/82 shadow-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                <span class="text-sm font-bold text-slate-700">Deleted project mappings ({{ $trashedProjectMappings->count() }})</span>
                <flux:icon.chevron-right class="size-4 text-slate-400 transition group-open:rotate-90" />
            </summary>

            <div class="overflow-x-auto border-t border-red-100">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-red-50/80 text-[0.7rem] uppercase tracking-[0.24em] text-red-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Academic Year</th>
                            <th class="px-5 py-3 text-left">Graduating Year</th>
                            <th class="px-5 py-3 text-left">REDCap PID</th>
                            <th class="px-5 py-3 text-left">Deleted</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($trashedProjectMappings as $projectMapping)
                            <tr class="opacity-70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $projectMapping->academic_year }}</td>
                                <td class="px-5 py-4 text-slate-600">Class of {{ $projectMapping->graduation_year }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $projectMapping->redcap_pid }}</td>
                                <td class="px-5 py-4 text-slate-500">{{ $projectMapping->deleted_at?->diffForHumans() }}</td>
                                <td class="px-5 py-4 text-right">
                                    @can('manage-settings-records')
                                        <form method="POST" action="{{ route('admin.settings.project-mappings.restore', $projectMapping->id) }}">
                                            @csrf
                                            <flux:button type="submit" size="sm" variant="ghost">Restore</flux:button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @can('manage-settings-records')
        <livewire:edit-weights-modal />
    @endcan
</x-app-shell>
