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
                        <p class="mt-2 text-sm leading-6 text-slate-500">This single source project receives webhooks for all scholars across their 4-semester evaluation window.</p>
                    @else
                        <p class="mt-3 text-sm leading-6 text-slate-500">No active source project configured yet. Add one to enable webhook processing.</p>
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
                        <h2 class="mt-2 text-xl font-bold text-slate-950">Configure the Source Project</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Set the active REDCap source project that receives evaluation webhooks. Only one source project is active at a time.</p>
                        <div class="mt-4">
                            <flux:button href="{{ route('admin.settings.source-project.create') }}" variant="primary" icon="academic-cap">Configure source project</flux:button>
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
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">REDCap PID</th>
                        <th class="px-5 py-3 text-left">Token</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($projectMappings as $projectMapping)
                        <tr class="transition hover:bg-sky-50/55">
                            <td class="px-5 py-4">
                                @if ($projectMapping->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-950">{{ $projectMapping->redcap_pid }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ $projectMapping->maskedToken() }}</td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    @can('manage-settings-records')
                                        @unless ($projectMapping->is_active)
                                            <form method="POST" action="{{ route('admin.settings.project-mappings.activate', $projectMapping) }}">
                                                @csrf
                                                <flux:button type="submit" size="sm" variant="ghost" icon="check-circle">Activate</flux:button>
                                            </form>
                                        @endunless

                                        <flux:button href="{{ route('admin.settings.project-mappings.import-students', $projectMapping) }}" size="sm" variant="ghost" icon="user-plus" title="Import scholars from destination">Import</flux:button>

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
                            <td colspan="4" class="px-5 py-12 text-center text-slate-500">No project mappings found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <livewire:admin.reprocess-card />

    @can('edit-email-template')
        <details class="group rounded-lg border border-white/80 bg-white/86 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700">
                        <flux:icon.envelope class="size-4" />
                    </span>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Notifications</div>
                        <h2 class="mt-0.5 text-base font-bold text-slate-950">Evaluation Email Template</h2>
                    </div>
                </div>
                <flux:icon.chevron-right class="size-4 shrink-0 text-slate-400 transition group-open:rotate-90" />
            </summary>

            <div class="border-t border-slate-200/80 p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm text-slate-600">
                            The template for evaluation notification emails sent to students.
                            Open it to preview with sample data or edit the Blade/markdown content.
                        </p>
                        @if ($emailTemplateSetting?->updated_at)
                            <p class="text-xs text-slate-400">
                                Last updated {{ $emailTemplateSetting->updated_at->diffForHumans() }}
                                @if ($emailTemplateSetting->updated_at->ne($emailTemplateSetting->created_at))
                                    (customised)
                                @else
                                    (default)
                                @endif
                            </p>
                        @endif
                    </div>
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="pencil-square"
                        onclick="event.preventDefault(); Livewire.dispatch('open-email-template')"
                    >Edit email template</flux:button>
                </div>

                {{-- Inline preview rendered server-side with dummy data --}}
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-inner">
                    {{-- "Email client" chrome --}}
                    <div class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-2.5">
                        <div class="flex gap-1.5">
                            <span class="size-2.5 rounded-full bg-red-400/80"></span>
                            <span class="size-2.5 rounded-full bg-amber-400/80"></span>
                            <span class="size-2.5 rounded-full bg-emerald-400/80"></span>
                        </div>
                        <div class="flex-1 truncate text-center text-xs font-medium text-slate-500">
                            <flux:icon.envelope class="-mt-0.5 mr-1 inline size-3" />
                            Teaching evaluation · spring semester · Dr. Smith
                        </div>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.6rem] font-bold uppercase tracking-wide text-amber-700">Sample</span>
                    </div>

                    {{-- Rendered into a sandboxed iframe so any script/style in the
                         template stays isolated from the admin page. --}}
                    <div class="bg-slate-100 p-6">
                        <div class="mx-auto max-w-[680px] overflow-hidden rounded-lg bg-white shadow-md">
                            <iframe
                                sandbox=""
                                srcdoc="{{ $emailPreviewHtml }}"
                                class="block h-[640px] w-full bg-white"
                                title="Evaluation email preview"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </details>
    @endcan

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
                            <th class="px-5 py-3 text-left">REDCap PID</th>
                            <th class="px-5 py-3 text-left">Deleted</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($trashedProjectMappings as $projectMapping)
                            <tr class="opacity-70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $projectMapping->academic_year ?? '—' }}</td>
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

    @can('edit-email-template')
        <livewire:email-template-modal />
    @endcan
</x-app-shell>
