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
    @if ($availableMappings->count() >= 2 || ! $lockSelection)
        <section class="rounded-lg border border-[#d8e3fa] bg-white/90 p-5 shadow-[0_14px_38px_rgba(26,54,93,0.06)] backdrop-blur">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                @if ($availableMappings->count() >= 2)
                    <flux:select
                        class="max-w-[14rem]"
                        wire:model.live="selectedGraduationYear"
                        label="Academic Year"
                    >
                        @foreach ($availableMappings as $am)
                            <flux:select.option value="{{ $am->graduation_year }}" wire:key="ay-option-{{ $am->id }}">
                                {{ $am->academic_year }} (Class of {{ $am->graduation_year }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @unless ($lockSelection)
                    <flux:select
                        class="max-w-sm"
                        wire:model.live="selectedFaculty"
                        label="Choose a faculty member"
                    >
                        <flux:select.option value="">Select faculty...</flux:select.option>
                        @foreach ($facultyRoster as $faculty)
                            <flux:select.option value="{{ $faculty }}" wire:key="faculty-option-{{ md5($faculty) }}">
                                {{ $faculty }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endunless
            </div>
        </section>
    @endif

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
