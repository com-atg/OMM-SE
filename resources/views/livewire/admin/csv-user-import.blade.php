<div>
    <flux:button variant="ghost" icon="table-cells" wire:click="openModal">Import CSV</flux:button>

    <flux:modal wire:model="modalOpen" name="csv-user-import" class="md:w-[34rem]" scroll="body">
        <div class="space-y-6">

            {{-- Header --}}
            <div>
                <div class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#455f88]">Bulk Import</div>
                <flux:heading size="lg" class="mt-2">Import Users via CSV</flux:heading>
                <flux:text class="mt-1">Upload a CSV file to create multiple users at once. All rows are validated before any users are created.</flux:text>
            </div>

            @if ($done)
                {{-- ── Success state ──────────────────────────────────────── --}}
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-center">
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
                </div>

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
                    <flux:modal.close>
                        <flux:button variant="primary">Done</flux:button>
                    </flux:modal.close>
                </div>

            @else
                {{-- ── Upload form ────────────────────────────────────────── --}}

                {{-- Format guide --}}
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
                    <p class="mt-2.5 text-[0.7rem] text-[#74777f]">
                        Valid roles: <span class="font-medium text-[#43474e]">admin, student, faculty</span>
                    </p>
                </div>

                {{-- File drop zone --}}
                <div>
                    <label for="csv-file-input" class="block cursor-pointer">
                        <div @class([
                            'rounded-lg border-2 border-dashed p-8 text-center transition',
                            'border-slate-200 bg-slate-50 hover:border-sky-300 hover:bg-sky-50/60' => ! $csvFile,
                            'border-sky-300 bg-sky-50' => $csvFile,
                        ])>
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
                        />
                    </label>

                    @error('csvFile')
                        <div class="mt-2 flex items-start gap-2 text-sm text-red-600">
                            <flux:icon.exclamation-circle variant="mini" class="mt-0.5 size-4 shrink-0" />
                            {{ $message }}
                        </div>
                    @enderror
                </div>

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

                {{-- Per-row validation errors --}}
                @if (! empty($rowErrors))
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                        <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-800">
                            <flux:icon.exclamation-triangle variant="mini" class="size-4 shrink-0" />
                            {{ count($rowErrors) }} issue(s) found — fix your CSV and re-upload
                        </div>
                        <ul class="max-h-48 space-y-1.5 overflow-y-auto text-xs text-red-700">
                            @foreach ($rowErrors as $error)
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-px shrink-0 text-red-400">•</span>
                                    {{ $error }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center justify-between gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="primary"
                        wire:click="import"
                        wire:loading.attr="disabled"
                        wire:target="import,csvFile"
                        icon="arrow-up-tray"
                    >
                        <span wire:loading.remove wire:target="import">Import users</span>
                        <span wire:loading wire:target="import">Importing…</span>
                    </flux:button>
                </div>

            @endif
        </div>
    </flux:modal>
</div>
