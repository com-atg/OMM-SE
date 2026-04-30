@php
    $totalEvaluations = count($evaluations);
    $categoryColors = [
        'Teaching'  => 'blue',
        'Clinic'    => 'emerald',
        'Research'  => 'amber',
        'Didactics' => 'violet',
    ];
    $semesterColors = [
        'Spring' => 'sky',
        'Fall'   => 'amber',
    ];
@endphp

<div class="flex flex-col gap-7">
    <section class="overflow-hidden rounded-xl border border-white/80 bg-white/95 shadow-[0_8px_24px_rgba(15,23,42,0.05)] backdrop-blur">
        <div class="flex flex-col divide-y divide-slate-200/70 md:flex-row md:items-stretch md:divide-x md:divide-y-0">
            <div class="relative flex items-center gap-3 bg-gradient-to-br from-sky-50 via-white to-slate-50 px-5 py-4 md:min-w-[200px]">
                <span class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-sky-400 to-indigo-500"></span>
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-sky-600 shadow-sm ring-1 ring-sky-100">
                    <flux:icon.funnel variant="mini" class="size-4" />
                </span>
                <div class="min-w-0">
                    <div class="text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-sky-700">Filters</div>
                    <div class="truncate text-sm font-semibold text-slate-900">Faculty scope</div>
                </div>
            </div>

            <div class="flex flex-1 flex-wrap items-center gap-x-5 gap-y-3 px-5 py-4">
                @unless ($lockSelection)
                    <flux:field variant="inline">
                        <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Faculty</flux:label>
                        <flux:select
                            wire:model.live="selectedFaculty"
                            size="sm"
                            class="min-w-56"
                            placeholder="Select faculty..."
                        >
                            <flux:select.option value="">Select faculty...</flux:select.option>
                            @foreach ($facultyRoster as $faculty)
                                <flux:select.option value="{{ $faculty }}" wire:key="faculty-option-{{ md5($faculty) }}">
                                    {{ $faculty }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <div class="hidden h-7 w-px bg-slate-200 md:block" aria-hidden="true"></div>
                @endunless

                <flux:field variant="inline">
                    <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Active only</flux:label>
                    <flux:switch wire:model.live="activeOnly" />
                </flux:field>

                <div class="hidden h-7 w-px bg-slate-200 md:block" aria-hidden="true"></div>

                <flux:field variant="inline">
                    <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Batch</flux:label>
                    <flux:select
                        wire:model.live="selectedBatch"
                        size="sm"
                        class="min-w-40"
                        placeholder="All batches"
                    >
                        <flux:select.option value="">All batches</flux:select.option>
                        @foreach ($availableBatches as $batch)
                            <flux:select.option value="{{ $batch }}">{{ $batch }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
        </div>
    </section>

    @if ($displayFaculty === '')
        <section class="rounded-lg border border-[#d8e3fa] bg-white/90 p-10 text-center shadow-[0_14px_38px_rgba(26,54,93,0.05)]">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-[#e7eeff] text-[#455f88]">
                <flux:icon.identification variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-[#111c2c]">Select faculty</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-[#43474e]">
                Use the faculty selector to review completed source-project evaluations.
            </p>
        </section>
    @else
        <section class="flex flex-col gap-6" wire:key="faculty-detail-{{ md5($displayFaculty) }}">
            <div class="rounded-lg border border-[#d8e3fa] bg-white/92 p-5 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <span class="rounded-full bg-[#d6e3ff] px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#001b3c]">Faculty Profile</span>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#111c2c] sm:text-4xl">{{ $displayFaculty }}</h2>
                        <p class="mt-2 text-sm leading-6 text-[#43474e]">Completed evaluations submitted from the current source project.</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 sm:min-w-[360px]">
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4 text-center">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Evals</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[#111c2c]">{{ number_format($totalEvaluations) }}</dd>
                        </div>
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f4fbfa] p-4 text-center">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#006a63]">Categories</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[#111c2c]">{{ number_format($categoryCounts->count()) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <section class="rounded-lg border border-[#d8e3fa] bg-white/92 p-6 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[#455f88]">Records</div>
                        <h2 class="mt-2 text-lg font-semibold text-[#111c2c]">Completed Evaluations</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categoryCounts as $category => $count)
                            <flux:badge color="{{ $categoryColors[$category] ?? 'zinc' }}">{{ $category }} {{ $count }}</flux:badge>
                        @endforeach
                    </div>
                </div>

                @if ($totalEvaluations === 0)
                    <div class="mt-5 rounded-lg border border-dashed border-[#c4c6cf] bg-[#f9f9ff] p-8 text-center text-sm text-[#74777f]">
                        <flux:icon.no-symbol variant="mini" class="mx-auto mb-3 size-6 text-[#98a2b3]" />
                        No completed evaluations were found for this faculty member.
                    </div>
                @else
                    <div class="mt-5 overflow-hidden rounded-lg border border-[#e2e8f0] bg-white">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="bg-[#f0f3ff] px-8 py-4 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Student</flux:table.column>
                                <flux:table.column class="bg-[#f0f3ff] px-8 py-4 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Semester</flux:table.column>
                                <flux:table.column class="bg-[#f0f3ff] px-8 py-4 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Category</flux:table.column>
                                <flux:table.column class="bg-[#f0f3ff] px-8 py-4 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Score</flux:table.column>
                                <flux:table.column class="bg-[#f0f3ff] px-8 py-4 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Date</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($evaluations as $evaluation)
                                    <flux:table.row
                                        class="cursor-pointer transition hover:bg-[#f9f9ff]"
                                        wire:key="faculty-eval-{{ $evaluation['record_id'] }}"
                                        wire:click="openEvaluation('{{ $evaluation['record_id'] }}')"
                                    >
                                        <flux:table.cell class="px-8 py-4 font-semibold text-[#111c2c]" align="center">{{ $evaluation['student_name'] }}</flux:table.cell>
                                        <flux:table.cell class="px-8 py-4" align="center">
                                            <flux:badge color="{{ $semesterColors[$evaluation['semester_label']] ?? 'zinc' }}">{{ $evaluation['semester_label'] }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell class="px-8 py-4" align="center">
                                            <flux:badge color="{{ $categoryColors[$evaluation['category_label']] ?? 'zinc' }}">{{ $evaluation['category_label'] }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell class="px-8 py-4 tabular-nums" align="center">
                                            {{ $evaluation['score'] !== '' ? $evaluation['score'].'%' : '-' }}
                                        </flux:table.cell>
                                        <flux:table.cell class="px-8 py-4 text-[#43474e]" align="center">{{ $evaluation['date'] }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </section>
        </section>
    @endif

    <flux:modal name="faculty-evaluation-detail" wire:model="detailModalOpen" class="md:w-[44rem]" scroll="body">
        @if ($selectedEvaluation)
            <div class="space-y-6">
                <div>
                    <div class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#455f88]">Evaluation Detail</div>
                    <flux:heading size="lg" class="mt-2">{{ $selectedEvaluation['student_name'] }}</flux:heading>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-[#43474e]">
                        <flux:badge color="{{ $categoryColors[$selectedEvaluation['category_label']] ?? 'zinc' }}">{{ $selectedEvaluation['category_label'] }}</flux:badge>
                        <flux:badge color="{{ $semesterColors[$selectedEvaluation['semester_label']] ?? 'zinc' }}">{{ $selectedEvaluation['semester_label'] }}</flux:badge>
                        <span>{{ $selectedEvaluation['date'] }}</span>
                    </div>
                </div>

                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-3">
                        <dt class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-[#455f88]">Score</dt>
                        <dd class="mt-1 text-xl font-semibold text-[#111c2c]">{{ $selectedEvaluation['score'] !== '' ? $selectedEvaluation['score'].'%' : '-' }}</dd>
                    </div>
                    <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-3">
                        <dt class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-[#455f88]">Faculty</dt>
                        <dd class="mt-1 text-sm font-semibold text-[#111c2c]">{{ $selectedEvaluation['faculty'] }}</dd>
                    </div>
                    <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-3">
                        <dt class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-[#455f88]">Record</dt>
                        <dd class="mt-1 text-sm font-semibold text-[#111c2c]">#{{ $selectedEvaluation['record_id'] }}</dd>
                    </div>
                </dl>

                @if (! empty($selectedEvaluation['criteria']))
                    <div>
                        <div class="mb-3 text-sm font-semibold text-[#111c2c]">Score Breakdown</div>
                        @if ($selectedEvaluation['score_scale'] !== '')
                            <p class="mb-3 text-xs text-[#74777f]">Scale: {{ $selectedEvaluation['score_scale'] }}</p>
                        @endif
                        <div class="overflow-hidden rounded-lg border border-[#e2e8f0]">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column class="bg-[#f0f3ff] px-6 py-4 text-xs font-bold uppercase tracking-[0.14em] text-[#455f88]" align="center">Criterion</flux:table.column>
                                    <flux:table.column class="bg-[#f0f3ff] px-6 py-4 text-xs font-bold uppercase tracking-[0.14em] text-[#455f88]" align="center">Score</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($selectedEvaluation['criteria'] as $criterion)
                                        <flux:table.row>
                                            <flux:table.cell class="px-6 py-4 text-sm leading-6 text-[#111c2c] whitespace-normal break-words" align="center">{{ $criterion['label'] }}</flux:table.cell>
                                            <flux:table.cell class="px-6 py-4 text-sm font-semibold tabular-nums text-[#111c2c]" align="center">{{ $criterion['value'] === '0' ? 'N/A' : $criterion['value'] }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </div>
                @endif

                <div>
                    <div class="mb-2 text-sm font-semibold text-[#111c2c]">Comments</div>
                    <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4 text-sm leading-6 text-[#43474e]">
                        {{ $selectedEvaluation['comments'] !== '' ? $selectedEvaluation['comments'] : 'No comments recorded.' }}
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
