<div class="space-y-6">
    @if ($done)
        {{-- ── Success state ──────────────────────────────────────── --}}
        <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-6 text-center">
            <div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-emerald-100">
                <flux:icon.check-circle variant="mini" class="size-6 text-emerald-600" />
            </div>
            <div class="text-base font-semibold text-emerald-900">Import complete</div>
            <div class="mt-2 flex justify-center gap-6 text-sm text-emerald-800">
                <span><span class="font-bold tabular-nums">{{ $imported }}</span> created</span>
                @if ($skipped > 0)
                    <span><span class="font-bold tabular-nums">{{ $skipped }}</span> skipped (already existed)</span>
                @endif
            </div>
        </section>

        @if (! empty($rowErrors))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-800">
                    <flux:icon.exclamation-triangle variant="mini" class="size-4 shrink-0" />
                    {{ count($rowErrors) }} row(s) were skipped
                </div>
                <ul class="space-y-1 text-xs text-amber-700">
                    @foreach ($rowErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex justify-end">
            <flux:button href="{{ route('admin.users.index') }}" variant="primary">Done</flux:button>
        </div>
    @else
        {{-- Format guide + sample download --}}
        <section class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <a
                href="{{ route('admin.users.import-csv.sample') }}"
                class="group flex flex-col justify-between rounded-lg border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-sky-300 hover:shadow-[0_8px_24px_rgba(56,116,203,0.12)] focus:outline-none focus:ring-2 focus:ring-sky-300"
            >
                <div class="flex items-start gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700 transition group-hover:bg-sky-600 group-hover:text-white">
                        <flux:icon.arrow-down-tray variant="mini" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-sky-700">Starter template</div>
                        <div class="mt-0.5 text-sm font-semibold text-slate-900">Download sample CSV</div>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-500">
                    Pre-filled with the correct headers and one example row per role. Open in Excel or Numbers, edit, then drop it below.
                </p>
            </a>

            <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]">Required CSV format</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-[#e2e8f0] text-[#455f88]">
                                <th class="pb-1.5 pr-6 text-left font-semibold">name</th>
                                <th class="pb-1.5 pr-6 text-left font-semibold">email</th>
                                <th class="pb-1.5 text-left font-semibold">role</th>
                            </tr>
                        </thead>
                        <tbody class="text-[#43474e]">
                            <tr>
                                <td class="pr-6 pt-1.5">Jane Smith</td>
                                <td class="pr-6 pt-1.5">jane@example.com</td>
                                <td class="pt-1.5">faculty</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[#e2e8f0] pt-3">
                    <span class="text-[0.55rem] font-bold uppercase tracking-[0.2em] text-[#74777f]">Valid roles</span>
                    @foreach ($this->validRoleValues() as $role)
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-bold capitalize',
                            'bg-violet-100 text-violet-800' => $role === 'service',
                            'bg-sky-100 text-sky-800' => $role === 'admin',
                            'bg-teal-100 text-teal-800' => $role === 'faculty',
                            'bg-emerald-100 text-emerald-800' => $role === 'student',
                        ])>{{ $role }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- File drop zone --}}
        <section>
            <label
                for="csv-file-input"
                class="block cursor-pointer"
                x-data="csvFileDrop('csv-file-input')"
                x-on:click.stop
                x-on:dragenter.prevent="isDragging = true"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent.stop="dropFile($event)"
            >
                <div @class([
                    'rounded-lg border-2 border-dashed p-8 text-center transition',
                    'border-slate-200 bg-slate-50 hover:border-sky-300 hover:bg-sky-50/60' => ! $csvFile,
                    'border-sky-300 bg-sky-50' => $csvFile,
                ])
                x-bind:class="isDragging ? 'border-sky-400 bg-sky-50 ring-2 ring-sky-100' : ''">
                    @if ($csvFile)
                        <flux:icon.document-check variant="mini" class="mx-auto mb-2 size-8 text-sky-500" />
                        <div class="text-sm font-semibold text-sky-700">{{ $csvFile->getClientOriginalName() }}</div>
                        <div class="mt-1 text-xs text-sky-500">
                            {{ number_format($csvFile->getSize() / 1024, 1) }} KB · Click to choose a different file
                        </div>
                    @else
                        <flux:icon.arrow-up-tray variant="mini" class="mx-auto mb-2 size-8 text-slate-400" />
                        <div class="text-sm font-medium text-slate-700">Drop your CSV here, or click to browse</div>
                        <div class="mt-1 text-xs text-slate-400">CSV · max 1 MB</div>
                    @endif
                </div>
                <input
                    id="csv-file-input"
                    type="file"
                    wire:model="csvFile"
                    accept=".csv,text/csv"
                    class="sr-only"
                    x-on:click.stop
                />
            </label>

            @error('csvFile')
                <div class="mt-2 flex items-start gap-2 text-sm text-red-600">
                    <flux:icon.exclamation-circle variant="mini" class="mt-0.5 size-4 shrink-0" />
                    {{ $message }}
                </div>
            @enderror
        </section>

        @if (! empty($rows))
            @php
                $issueCount = $this->issueCount();
                $warningCount = $this->warningCount();
                $validRoleValues = $this->validRoleValues();
            @endphp

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]">Editable preview</div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ count($rows) }} row(s)
                            @if ($issueCount > 0)
                                <span class="font-semibold text-red-600">· {{ $issueCount }} issue(s)</span>
                            @elseif ($warningCount > 0)
                                <span class="font-semibold text-amber-600">· {{ $warningCount }} skip warning(s)</span>
                            @else
                                <span class="font-semibold text-emerald-600">· ready</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-[0.7rem] text-slate-500">Tip: edits save when you click out of a cell.</div>
                </div>

                @if (! empty($missingColumns))
                    <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800">
                        Missing column(s): {{ implode(', ', $missingColumns) }}. Fill the highlighted cells below.
                    </div>
                @endif

                <div class="max-h-[28rem] overflow-auto">
                    <table class="w-full min-w-[760px] border-separate border-spacing-0 text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-[0.68rem] uppercase tracking-[0.16em] text-slate-600 shadow-[0_1px_0_rgba(148,163,184,0.5)]">
                            <tr>
                                <th class="w-14 border-r border-slate-200 px-3 py-2 text-left font-bold">#</th>
                                <th class="border-r border-slate-200 px-3 py-2 text-left font-bold">Name</th>
                                <th class="border-r border-slate-200 px-3 py-2 text-left font-bold">Email</th>
                                <th class="w-44 border-r border-slate-200 px-3 py-2 text-left font-bold">Role</th>
                                <th class="w-32 px-3 py-2 text-left font-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                @php
                                    $nameError = $cellErrors[$index]['name'] ?? null;
                                    $emailError = $cellErrors[$index]['email'] ?? null;
                                    $roleError = $cellErrors[$index]['role'] ?? null;
                                    $emailWarning = $cellWarnings[$index]['email'] ?? null;
                                    $hasRowError = $nameError || $emailError || $roleError;
                                    $hasRowWarning = ! $hasRowError && $emailWarning;
                                    $currentRole = strtolower(trim((string) ($row['role'] ?? '')));
                                @endphp
                                <tr wire:key="csv-import-row-{{ $index }}-{{ $row['line'] }}">
                                    <td class="border-b border-r border-slate-200 bg-slate-50 px-3 py-0 font-semibold tabular-nums text-slate-500">{{ $row['line'] }}</td>

                                    <td @class([
                                        'border-b border-r border-slate-200 p-0',
                                        'bg-red-50' => $nameError,
                                    ])>
                                        <input
                                            type="text"
                                            wire:model.live.blur="rows.{{ $index }}.name"
                                            aria-label="Row {{ $row['line'] }} name"
                                            @if ($nameError) title="{{ $nameError }}" @endif
                                            class="w-full bg-transparent px-3 py-2 text-sm text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-inset focus:ring-sky-300"
                                        >
                                    </td>

                                    <td @class([
                                        'border-b border-r border-slate-200 p-0',
                                        'bg-red-50' => $emailError,
                                        'bg-amber-50' => ! $emailError && $emailWarning,
                                    ])>
                                        <input
                                            type="email"
                                            wire:model.live.blur="rows.{{ $index }}.email"
                                            aria-label="Row {{ $row['line'] }} email"
                                            @if ($emailError) title="{{ $emailError }}" @elseif ($emailWarning) title="{{ $emailWarning }}" @endif
                                            class="w-full bg-transparent px-3 py-2 text-sm text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-inset focus:ring-sky-300"
                                        >
                                    </td>

                                    <td @class([
                                        'border-b border-r border-slate-200 p-0',
                                        'bg-red-50' => $roleError,
                                    ])>
                                        <select
                                            wire:model.live.blur="rows.{{ $index }}.role"
                                            aria-label="Row {{ $row['line'] }} role"
                                            @if ($roleError) title="{{ $roleError }}" @endif
                                            class="w-full bg-transparent px-3 py-2 text-sm text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-inset focus:ring-sky-300"
                                        >
                                            @if ($currentRole !== '' && ! in_array($currentRole, $validRoleValues, true))
                                                <option value="{{ $currentRole }}">{{ $currentRole }}</option>
                                            @endif
                                            <option value="">Select role</option>
                                            @foreach ($validRoleValues as $role)
                                                <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="border-b border-slate-200 px-3 py-2">
                                        @if ($hasRowError)
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-[0.68rem] font-bold text-red-700">Needs edit</span>
                                        @elseif ($hasRowWarning)
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-[0.68rem] font-bold text-amber-700">Skipped</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[0.68rem] font-bold text-emerald-700">Ready</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Header / file-level error --}}
        @if ($headerError)
            <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <flux:icon.x-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-red-500" />
                <div>
                    <div class="font-semibold">Could not read file</div>
                    <div class="mt-0.5">{{ $headerError }}</div>
                </div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center justify-between gap-3">
            <flux:button href="{{ route('admin.users.index') }}" variant="ghost">Cancel</flux:button>
            <flux:button
                variant="primary"
                type="button"
                wire:click="import"
                wire:loading.attr="disabled"
                wire:target="import,csvFile"
                icon="arrow-up-tray"
                :disabled="! $this->canImport()"
            >
                <span wire:loading.remove wire:target="import">Import users</span>
                <span wire:loading wire:target="import">Importing…</span>
            </flux:button>
        </div>
    @endif
</div>
