@php
    $studentDropdownSql = <<<SQL
select a.value, CONCAT_WS(' ',b.value,d.value) as label
from
(select record, value from redcap_data8 where project_id=2115 and
field_name='datatelid') a
JOIN
(select record, value from redcap_data8 where project_id=2115 and
field_name='first_name') b
JOIN
(select record, value from redcap_data8 where project_id=2115 and
field_name='last_name') d
JOIN
(select record, value from redcap_data8 where project_id=2115 and
field_name='year') e
ON
a.record=b.record and b.record=d.record and d.record=e.record and e.value='{$nextGraduationYear}'
order by a.value
SQL;
@endphp

<x-app-shell
    title="New Academic Year"
    active="settings"
    eyebrow="Service Settings"
    heading="Prepare for a New Academic Year"
    subheading="Complete these REDCap-side steps before adding the new project mapping."
    width="wide"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.settings.index') }}" variant="ghost" icon="arrow-left">Back to settings</flux:button>
    </x-slot:headerActions>

    <section class="space-y-5">
        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">1</span>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Roster</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Add new students to the OMM ACE List</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">In REDCap project <span class="font-semibold text-slate-800">OMM ACE List</span> (PID <span class="font-mono">2115</span>), add a record for each new student with the following fields:</p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                        <li>Name</li>
                        <li>Email</li>
                        <li>Datatel ID</li>
                        <li>Graduating Year</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">2</span>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Project</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Copy the existing Academic project</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">In REDCap, copy the most recent academic-year project to create the new one. Take note of the new PID once it is created.</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">3</span>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">API</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Create a new API token</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">In the newly copied REDCap project, request and generate an API token. You will paste this token into the project mapping form.</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">4</span>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">SQL</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Update the Student dropdown SQL query</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        In the new project's Student field, replace the dropdown query with the SQL below. The graduating year has been incremented to
                        <span class="font-mono font-semibold text-slate-900">{{ $nextGraduationYear }}</span> based on the latest existing project mapping.
                    </p>

                    <div
                        x-data="{
                            copied: false,
                            failed: false,
                            async copy() {
                                const text = this.$refs.sql.innerText;
                                let ok = false;
                                try {
                                    if (navigator.clipboard && window.isSecureContext) {
                                        await navigator.clipboard.writeText(text);
                                        ok = true;
                                    }
                                } catch (e) { ok = false; }
                                if (!ok) {
                                    const ta = document.createElement('textarea');
                                    ta.value = text;
                                    ta.style.position = 'fixed';
                                    ta.style.opacity = '0';
                                    document.body.appendChild(ta);
                                    ta.focus();
                                    ta.select();
                                    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                                    document.body.removeChild(ta);
                                }
                                if (ok) {
                                    this.copied = true;
                                    this.failed = false;
                                    setTimeout(() => this.copied = false, 1800);
                                } else {
                                    this.failed = true;
                                    const range = document.createRange();
                                    range.selectNodeContents(this.$refs.sql);
                                    const sel = window.getSelection();
                                    sel.removeAllRanges();
                                    sel.addRange(range);
                                    setTimeout(() => this.failed = false, 4000);
                                }
                            }
                        }"
                        class="mt-4"
                    >
                        <div class="flex items-center justify-between gap-2 rounded-t-lg border border-slate-200 px-4 py-2 text-xs font-bold uppercase tracking-[0.22em]" style="background-color: #0f172a; color: #cbd5e1;">
                            <span>Student Dropdown SQL</span>
                            <button
                                type="button"
                                x-on:click="copy()"
                                class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[0.7rem] font-semibold tracking-[0.18em] transition"
                                style="background-color: #1e293b; color: #f1f5f9;"
                                onmouseover="this.style.backgroundColor='#334155'"
                                onmouseout="this.style.backgroundColor='#1e293b'"
                            >
                                <flux:icon.clipboard-document class="size-3.5" x-show="!copied && !failed" />
                                <flux:icon.check class="size-3.5 text-emerald-400" x-show="copied" x-cloak />
                                <flux:icon.x-mark class="size-3.5 text-red-400" x-show="failed" x-cloak />
                                <span x-text="copied ? 'Copied' : (failed ? 'Press Ctrl+C' : 'Copy')"></span>
                            </button>
                        </div>
                        <pre
                            x-ref="sql"
                            x-on:click="copy()"
                            class="m-0 cursor-pointer overflow-x-auto rounded-b-lg border border-t-0 border-slate-200 p-4 text-xs leading-6"
                            style="background-color: #020617; color: #f1f5f9;"
                            title="Click to copy"
                        >{{ $studentDropdownSql }}</pre>
                    </div>

                    <p class="mt-3 text-xs leading-5 text-slate-500">
                        Note: each year, increment the <span class="font-mono">e.value</span> filter in this query to the new graduating class.
                    </p>
                </div>
            </div>
        </div>

        <livewire:admin.academic-year-wizard :next-graduation-year="$nextGraduationYear" />
    </section>
</x-app-shell>
