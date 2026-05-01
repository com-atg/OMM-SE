@php
    $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
    $overallAverage = $stats['kpis']['overall_avg'];
    $evaluatedPct = $stats['kpis']['total_students'] > 0
        ? round($stats['kpis']['students_evaluated'] / $stats['kpis']['total_students'] * 100)
        : 0;

    $kpis = [
        ['label' => 'Students', 'value' => number_format($stats['kpis']['total_students']), 'sub' => $stats['kpis']['students_evaluated'].' with evaluations', 'icon' => 'academic-cap', 'tone' => 'text-sky-700 bg-sky-50/70 ring-sky-200/60'],
        ['label' => 'Evaluations', 'value' => number_format($stats['kpis']['total_evals']), 'sub' => 'Across all 4 semesters', 'icon' => 'clipboard-document-check', 'tone' => 'text-emerald-700 bg-emerald-50/70 ring-emerald-200/60'],
        ['label' => 'Overall avg', 'value' => $overallAverage !== null ? number_format($overallAverage, 1) : '-', 'sub' => 'Weighted by evaluation count', 'icon' => 'chart-bar', 'tone' => 'text-amber-700 bg-amber-50/70 ring-amber-200/60'],
        ['label' => 'Coverage', 'value' => $evaluatedPct.'%', 'sub' => number_format($stats['kpis']['students_without_evals']).' without evaluations', 'icon' => 'shield-check', 'tone' => 'text-indigo-700 bg-indigo-50/70 ring-indigo-200/60'],
    ];

    $chartPalette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#0ea5e9', '#db2777'];
    $slotKeys = ['sem1', 'sem2', 'sem3', 'sem4'];
    $slotLabels = ['sem1' => 'Sem 1', 'sem2' => 'Sem 2', 'sem3' => 'Sem 3', 'sem4' => 'Sem 4'];

    $cardSurface = 'rounded-xl border border-white/80 bg-white/90 backdrop-blur';
    $cardShadow = 'shadow-[0_8px_24px_rgba(15,23,42,0.05)]';
    $eyebrow = 'text-xs font-semibold uppercase tracking-[0.18em] text-slate-500';

    $categoryRows = collect($stats['category_labels'])->map(function (string $label, int $index) use ($stats, $chartPalette, $slotKeys) {
        $bySlot = [];
        $total = 0;
        foreach ($slotKeys as $slot) {
            $count = (int) ($stats['volume_by_semester'][$slot][$index] ?? 0);
            $bySlot[$slot] = $count;
            $total += $count;
        }
        $markerColor = $chartPalette[$index % count($chartPalette)];

        return [
            'label' => $label,
            'average' => (float) ($stats['avg_by_category'][$index] ?? 0),
            'coverage' => (float) ($stats['coverage_pct'][$index] ?? 0),
            'by_slot' => $bySlot,
            'total' => $total,
            'marker_color' => $markerColor,
            'marker_ring' => $markerColor.'1A',
        ];
    });
@endphp

<div class="flex flex-col gap-8 sm:gap-10">
    <section class="overflow-hidden rounded-xl border border-white/80 bg-white/95 shadow-[0_8px_24px_rgba(15,23,42,0.05)] backdrop-blur">
        <div class="flex flex-col divide-y divide-slate-200/70 md:flex-row md:items-stretch md:divide-x md:divide-y-0">
            <div class="relative flex items-center gap-3 bg-gradient-to-br from-sky-50 via-white to-slate-50 px-5 py-4 md:min-w-[200px]">
                <span class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-sky-400 to-indigo-500"></span>
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-sky-600 shadow-sm ring-1 ring-sky-100">
                    <flux:icon.funnel variant="mini" class="size-4" />
                </span>
                <div class="min-w-0">
                    <div class="text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-sky-700">Filters</div>
                    <div class="truncate text-sm font-semibold text-slate-900">Roster scope</div>
                </div>
            </div>

            <div class="flex flex-1 flex-wrap items-center gap-x-5 gap-y-3 px-5 py-4">
                <flux:field variant="inline">
                    <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Active only</flux:label>
                    <flux:switch wire:model.live="activeOnly" />
                </flux:field>

                <div class="hidden h-7 w-px bg-slate-200" aria-hidden="true"></div>

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

            <div class="flex items-center gap-3 bg-slate-50/70 px-5 py-4">
                @if ($stats['is_stale'] ?? false)
                    <flux:badge color="amber" size="sm" icon="clock">Cached</flux:badge>
                @endif
                <div class="flex items-center gap-2.5">
                    <span class="relative flex size-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-100"></span>
                    </span>
                    <div class="leading-tight">
                        <div class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-emerald-700">Live</div>
                        <div class="text-xs font-medium text-slate-600 tabular-nums">Refreshed {{ $generatedAt->diffForHumans() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (! empty($stats['fetch_error']))
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>Dashboard data needs attention</flux:callout.heading>
            <flux:callout.text>{{ $stats['fetch_error'] }}</flux:callout.text>
        </flux:callout>
    @endif

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            <div class="{{ $cardSurface }} {{ $cardShadow }} p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="{{ $eyebrow }}">{{ $kpi['label'] }}</div>
                        <div class="mt-3 text-[2.25rem] font-bold leading-none tracking-tight text-slate-950 tabular-nums">{{ $kpi['value'] }}</div>
                    </div>
                    <span class="{{ $kpi['tone'] }} grid size-9 place-items-center rounded-md ring-1">
                        <flux:icon :name="$kpi['icon']" variant="mini" />
                    </span>
                </div>
                <div class="mt-4 truncate text-sm text-slate-500">{{ $kpi['sub'] }}</div>
            </div>
        @endforeach
    </section>

    @if (! $stats['has_students'])
        <section class="rounded-xl border border-slate-200 bg-white/82 p-8 text-center shadow-sm">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-slate-100 text-slate-500">
                <flux:icon.inbox variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-950">No student records are available</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
                The destination REDCap project returned no records. Check the REDCap connection settings and roster load.
            </p>
        </section>
    @elseif (! $stats['has_evals'])
        <section class="rounded-xl border border-amber-200 bg-white/82 p-8 text-center shadow-sm">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-amber-50 text-amber-600">
                <flux:icon.clock variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-950">No evaluations recorded yet</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
                {{ $stats['kpis']['total_students'] }} student{{ $stats['kpis']['total_students'] === 1 ? '' : 's' }} are available. Charts will appear once evaluation records have been processed.
            </p>
        </section>
    @else
        <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div class="{{ $cardSurface }} {{ $cardShadow }} p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="{{ $eyebrow }}">Submissions</div>
                        <h2 class="mt-2 text-base font-semibold tracking-tight text-slate-950">Average Score by Category</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Evaluation-weighted average score on a 0-100 scale.</p>
                    </div>
                    <flux:badge color="blue">Score</flux:badge>
                </div>
                <div class="mt-5 h-72" wire:ignore><canvas id="chartAvgByCategory"></canvas></div>
            </div>

            <div class="{{ $cardSurface }} {{ $cardShadow }} p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="{{ $eyebrow }}">Activity</div>
                        <h2 class="mt-2 text-base font-semibold tracking-tight text-slate-950">Evaluation Volume</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Completed evaluation count by category and semester.</p>
                    </div>
                    <flux:badge color="amber">Spring/Fall</flux:badge>
                </div>
                <div class="mt-5 h-72" wire:ignore><canvas id="chartVolumeBySemester"></canvas></div>
            </div>

            <div class="{{ $cardSurface }} {{ $cardShadow }} p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="{{ $eyebrow }}">Scores</div>
                        <h2 class="mt-2 text-base font-semibold tracking-tight text-slate-950">Score Distribution</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Student category averages grouped into score bands.</p>
                    </div>
                    <flux:badge color="violet">Bands</flux:badge>
                </div>
                <div class="mt-5 h-72" wire:ignore><canvas id="chartScoreDistribution"></canvas></div>
            </div>

            <div class="{{ $cardSurface }} {{ $cardShadow }} p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="{{ $eyebrow }}">Coverage</div>
                        <h2 class="mt-2 text-base font-semibold tracking-tight text-slate-950">Category Coverage</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Students with 1+ eval in that category divided by total roster.</p>
                    </div>
                    <flux:badge color="emerald">Coverage</flux:badge>
                </div>
                <div class="mt-5 h-72" wire:ignore><canvas id="chartCoverage"></canvas></div>
            </div>
        </section>

        <section class="{{ $cardSurface }} {{ $cardShadow }} p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="{{ $eyebrow }}">Data Classification</div>
                    <h2 class="mt-2 text-base font-semibold tracking-tight text-slate-950">Category Detail</h2>
                </div>
                <flux:badge color="sky">{{ $categoryRows->count() }} categories</flux:badge>
            </div>

            <flux:table container:class="mt-5 w-full" class="w-full min-w-[760px]">
                <flux:table.columns>
                    <flux:table.column class="w-[30%] border-b border-slate-200 px-5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500" align="center">Category</flux:table.column>
                    <flux:table.column class="w-[16%] border-b border-slate-200 px-5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500" align="center">Avg score</flux:table.column>
                    @foreach ($slotKeys as $slot)
                        <flux:table.column class="w-[8%] border-b border-slate-200 px-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500" align="center">{{ $slotLabels[$slot] }}</flux:table.column>
                    @endforeach
                    <flux:table.column class="w-[22%] border-b border-slate-200 px-5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500" align="center">Coverage</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($categoryRows as $row)
                        <flux:table.row class="transition hover:bg-slate-50/70">
                            <flux:table.cell class="px-5" align="center">
                                <div class="inline-flex items-center justify-center gap-3 text-left">
                                    <span
                                        class="size-2.5 rounded-full"
                                        style="background-color: {{ $row['marker_color'] }}; box-shadow: 0 0 0 5px {{ $row['marker_ring'] }}"
                                    ></span>
                                    <div>
                                        <div class="font-semibold text-slate-950">{{ $row['label'] }}</div>
                                        <div class="mt-0.5 text-xs font-medium text-slate-500">{{ number_format($row['total']) }} total evaluations</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="px-5 text-slate-700" align="center">
                                <span class="inline-flex min-w-16 justify-center rounded-md bg-slate-100 px-2.5 py-1 font-semibold tabular-nums text-slate-800">
                                    {{ $row['average'] > 0 ? number_format($row['average'], 1) : '-' }}
                                </span>
                            </flux:table.cell>
                            @foreach ($slotKeys as $slot)
                                <flux:table.cell class="px-3 font-medium tabular-nums text-slate-600" align="center">{{ number_format($row['by_slot'][$slot]) }}</flux:table.cell>
                            @endforeach
                            <flux:table.cell class="px-5" align="center">
                                <div class="mx-auto flex w-40 flex-col gap-1.5">
                                    <div class="text-sm font-semibold tabular-nums text-slate-700">{{ number_format($row['coverage'], 1) }}%</div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, $row['coverage'])) }}%"></div>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </section>
    @endif

    @if ($stats['has_students'] && $stats['has_evals'])
        @script
        <script>
            requestAnimationFrame(function () {
                const stats = @json($stats);
                const palette = @json($chartPalette);
                const gridColor = 'rgba(100, 116, 139, 0.16)';
                const tickColor = '#64748b';

                const baseOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: tickColor,
                                boxWidth: 10,
                                boxHeight: 10,
                                useBorderRadius: true,
                                borderRadius: 4,
                                padding: 18,
                                font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                            },
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#e2e8f0',
                            padding: 12,
                            cornerRadius: 8,
                        },
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor, drawTicks: false },
                            border: { display: false },
                            ticks: { color: tickColor, font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                        },
                        y: {
                            grid: { color: gridColor, drawTicks: false },
                            border: { display: false },
                            beginAtZero: true,
                            ticks: { color: tickColor, font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                        },
                    },
                };

                function destroyExisting(id) {
                    const el = document.getElementById(id);
                    if (!el) return null;
                    const existing = (window.Chart && Chart.getChart) ? Chart.getChart(el) : null;
                    if (existing) existing.destroy();
                    return el;
                }

                const c1 = destroyExisting('chartAvgByCategory');
                if (c1) new Chart(c1, {
                    type: 'bar',
                    data: {
                        labels: stats.category_labels,
                        datasets: [{
                            label: 'Average Score',
                            data: stats.avg_by_category,
                            backgroundColor: palette.slice(0, stats.category_labels.length),
                            borderRadius: 6,
                            borderSkipped: false,
                        }],
                    },
                    options: {
                        ...baseOptions,
                        plugins: { ...baseOptions.plugins, legend: { display: false } },
                        scales: {
                            ...baseOptions.scales,
                            y: {
                                ...baseOptions.scales.y,
                                suggestedMax: 100,
                                title: { display: true, text: 'Avg score (0-100)', color: '#94a3b8', font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                            },
                        },
                    },
                });

                const c2 = destroyExisting('chartVolumeBySemester');
                if (c2) new Chart(c2, {
                    type: 'bar',
                    data: {
                        labels: stats.volume_by_semester.labels,
                        datasets: [
                            { label: 'Sem 1', data: stats.volume_by_semester.sem1, backgroundColor: '#2563eb', borderRadius: 6, borderSkipped: false },
                            { label: 'Sem 2', data: stats.volume_by_semester.sem2, backgroundColor: '#0ea5e9', borderRadius: 6, borderSkipped: false },
                            { label: 'Sem 3', data: stats.volume_by_semester.sem3, backgroundColor: '#f59e0b', borderRadius: 6, borderSkipped: false },
                            { label: 'Sem 4', data: stats.volume_by_semester.sem4, backgroundColor: '#dc2626', borderRadius: 6, borderSkipped: false },
                        ],
                    },
                    options: {
                        ...baseOptions,
                        scales: {
                            ...baseOptions.scales,
                            y: {
                                ...baseOptions.scales.y,
                                title: { display: true, text: '# evaluations', color: '#94a3b8', font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                            },
                        },
                    },
                });

                const c3 = destroyExisting('chartScoreDistribution');
                if (c3) new Chart(c3, {
                    type: 'bar',
                    data: {
                        labels: stats.histogram.labels,
                        datasets: stats.histogram.series.map((series, index) => ({
                            label: series.label,
                            data: series.data,
                            backgroundColor: palette[index % palette.length],
                            borderRadius: 6,
                            borderSkipped: false,
                        })),
                    },
                    options: {
                        ...baseOptions,
                        scales: {
                            ...baseOptions.scales,
                            y: {
                                ...baseOptions.scales.y,
                                title: { display: true, text: '# student-category averages', color: '#94a3b8', font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                            },
                        },
                    },
                });

                const c4 = destroyExisting('chartCoverage');
                if (c4) new Chart(c4, {
                    type: 'bar',
                    data: {
                        labels: stats.category_labels,
                        datasets: [{
                            label: '% of Students',
                            data: stats.coverage_pct,
                            backgroundColor: palette.slice(0, stats.category_labels.length),
                            borderRadius: 6,
                            borderSkipped: false,
                        }],
                    },
                    options: {
                        ...baseOptions,
                        indexAxis: 'y',
                        plugins: { ...baseOptions.plugins, legend: { display: false } },
                        scales: {
                            x: {
                                ...baseOptions.scales.x,
                                type: 'linear',
                                beginAtZero: true,
                                min: 0,
                                offset: false,
                                suggestedMax: 100,
                                grid: { ...baseOptions.scales.x.grid, offset: false },
                                title: { display: true, text: '% of roster', color: '#94a3b8', font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                                ticks: {
                                    ...baseOptions.scales.x.ticks,
                                    callback: (value) => `${value}%`,
                                },
                            },
                            y: {
                                type: 'category',
                                grid: { color: gridColor, drawTicks: false },
                                border: { display: false },
                                ticks: { color: tickColor, font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                            },
                        },
                    },
                });
            });
        </script>
        @endscript
    @endif
</div>
