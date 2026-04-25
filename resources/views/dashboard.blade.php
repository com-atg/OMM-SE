@php
    $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
    $overallAverage = $stats['kpis']['overall_avg'];
    $evaluatedPct = $stats['kpis']['total_students'] > 0
        ? round($stats['kpis']['students_evaluated'] / $stats['kpis']['total_students'] * 100)
        : 0;

    $kpis = [
        ['label' => 'Students', 'value' => number_format($stats['kpis']['total_students']), 'sub' => $stats['kpis']['students_evaluated'].' with evaluations', 'icon' => 'academic-cap', 'tone' => 'text-sky-700 bg-sky-50 ring-sky-100'],
        ['label' => 'Evaluations', 'value' => number_format($stats['kpis']['total_evals']), 'sub' => 'Across spring and fall', 'icon' => 'clipboard-document-check', 'tone' => 'text-emerald-700 bg-emerald-50 ring-emerald-100'],
        ['label' => 'Overall avg', 'value' => $overallAverage !== null ? number_format($overallAverage, 1) : '-', 'sub' => 'Weighted by evaluation count', 'icon' => 'chart-bar', 'tone' => 'text-amber-700 bg-amber-50 ring-amber-100'],
        ['label' => 'Coverage', 'value' => $evaluatedPct.'%', 'sub' => number_format($stats['kpis']['students_without_evals']).' without evaluations', 'icon' => 'shield-check', 'tone' => 'text-indigo-700 bg-indigo-50 ring-indigo-100'],
    ];

    $chartPalette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#0ea5e9', '#db2777'];

    $categoryRows = collect($stats['category_labels'])->map(function (string $label, int $index) use ($stats, $chartPalette) {
        $spring = (int) ($stats['volume_by_semester']['spring'][$index] ?? 0);
        $fall = (int) ($stats['volume_by_semester']['fall'][$index] ?? 0);
        $markerColor = $chartPalette[$index % count($chartPalette)];

        return [
            'label' => $label,
            'average' => (float) ($stats['avg_by_category'][$index] ?? 0),
            'coverage' => (float) ($stats['coverage_pct'][$index] ?? 0),
            'spring' => $spring,
            'fall' => $fall,
            'total' => $spring + $fall,
            'marker_color' => $markerColor,
            'marker_ring' => $markerColor.'1A',
        ];
    });
@endphp

<x-app-shell
    title="Dashboard"
    active="dashboard"
    eyebrow="Governance Overview"
    heading="OMM ACE Dashboard"
    subheading="OMM Student Evaluations reporting across teaching, clinic, research, and didactics."
>
    <x-slot:head>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    </x-slot:head>

    <x-slot:navActions>
        <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
            Refreshed {{ $generatedAt->diffForHumans() }}
        </span>

    </x-slot:navActions>

    <section class="rounded-lg border border-amber-200/80 bg-white/76 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur sm:p-7">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="mb-3 text-[0.7rem] font-bold uppercase tracking-[0.36em] text-amber-700">
                    Student Evaluation Overview
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-950">Data Quality Overview</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                    Snapshot of student roster coverage, score health, and evaluation volume from the latest REDCap sync.
                </p>
            </div>

            <div class="grid w-full gap-3 sm:grid-cols-2 lg:max-w-xl">
                <div class="rounded-lg border border-slate-200 bg-white/80 p-4 shadow-sm">
                    <div class="mb-2 inline-flex size-6 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-800">1</div>
                    <p class="text-sm font-medium leading-6 text-slate-600">Review coverage against the expected student roster.</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white/80 p-4 shadow-sm">
                    <div class="mb-2 inline-flex size-6 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-800">2</div>
                    <p class="text-sm font-medium leading-6 text-slate-600">Use category trends to find evaluation gaps before reporting.</p>
                </div>
            </div>
        </div>

        @if ($stats['is_stale'] ?? false)
            <div class="mt-5 inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 ring-1 ring-amber-200">
                <flux:icon.clock variant="mini" />
                Cached snapshot
            </div>
        @endif
    </section>

    @if (! empty($stats['fetch_error']))
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>Dashboard data needs attention</flux:callout.heading>
            <flux:callout.text>{{ $stats['fetch_error'] }}</flux:callout.text>
        </flux:callout>
    @endif

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            <div class="rounded-lg border border-white/80 bg-white/82 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">{{ $kpi['label'] }}</div>
                        <div class="mt-3 text-3xl font-bold tracking-tight text-slate-950 tabular-nums">{{ $kpi['value'] }}</div>
                    </div>
                    <span class="{{ $kpi['tone'] }} grid size-10 place-items-center rounded-lg ring-1">
                        <flux:icon :name="$kpi['icon']" variant="mini" />
                    </span>
                </div>
                <div class="mt-4 text-sm text-slate-500">{{ $kpi['sub'] }}</div>
            </div>
        @endforeach
    </section>

    @if (! $stats['has_students'])
        <section class="rounded-lg border border-slate-200 bg-white/82 p-8 text-center shadow-sm">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-slate-100 text-slate-500">
                <flux:icon.inbox variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-950">No student records are available</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
                The destination REDCap project returned no records. Check the REDCap connection settings and roster load.
            </p>
        </section>
    @elseif (! $stats['has_evals'])
        <section class="rounded-lg border border-amber-200 bg-white/82 p-8 text-center shadow-sm">
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
            <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Submissions</div>
                        <h2 class="mt-2 text-lg font-bold text-slate-950">Average Score by Category</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Evaluation-weighted average score on a 0-100 scale.</p>
                    </div>
                    <flux:badge color="blue">Score</flux:badge>
                </div>
                <div class="mt-6 h-72"><canvas id="chartAvgByCategory"></canvas></div>
            </div>

            <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Activity</div>
                        <h2 class="mt-2 text-lg font-bold text-slate-950">Evaluation Volume</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Completed evaluation count by category and semester.</p>
                    </div>
                    <flux:badge color="amber">Spring/Fall</flux:badge>
                </div>
                <div class="mt-6 h-72"><canvas id="chartVolumeBySemester"></canvas></div>
            </div>

            <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Scores</div>
                        <h2 class="mt-2 text-lg font-bold text-slate-950">Score Distribution</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Student category averages grouped into score bands.</p>
                    </div>
                    <flux:badge color="violet">Bands</flux:badge>
                </div>
                <div class="mt-6 h-72"><canvas id="chartScoreDistribution"></canvas></div>
            </div>

            <div class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Coverage</div>
                        <h2 class="mt-2 text-lg font-bold text-slate-950">Category Coverage</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Students with 1+ eval in that category divided by total roster.</p>
                    </div>
                    <flux:badge color="emerald">Coverage</flux:badge>
                </div>
                <div class="mt-6 h-72"><canvas id="chartCoverage"></canvas></div>
            </div>
        </section>

        <section class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Data Classification</div>
                    <h2 class="mt-2 text-lg font-bold text-slate-950">Category Detail</h2>
                </div>
                <flux:badge color="sky">{{ $categoryRows->count() }} categories</flux:badge>
            </div>

            <div class="mt-5 rounded-lg border border-slate-200/80 bg-white/72 p-3 shadow-sm">
                <flux:table container:class="w-full" class="w-full min-w-[760px] overflow-hidden rounded-md bg-white/88">
                    <flux:table.columns>
                        <flux:table.column class="w-[30%] bg-slate-50/90 px-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="center">Category</flux:table.column>
                        <flux:table.column class="w-[18%] bg-slate-50/90 px-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="center">Avg score</flux:table.column>
                        <flux:table.column class="w-[13%] bg-slate-50/90 px-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="center">Spring</flux:table.column>
                        <flux:table.column class="w-[13%] bg-slate-50/90 px-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="center">Fall</flux:table.column>
                        <flux:table.column class="w-[26%] bg-slate-50/90 px-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="center">Coverage</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($categoryRows as $row)
                            <flux:table.row class="transition hover:bg-slate-50/80">
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
                                <flux:table.cell class="px-5 font-medium tabular-nums text-slate-600" align="center">{{ number_format($row['spring']) }}</flux:table.cell>
                                <flux:table.cell class="px-5 font-medium tabular-nums text-slate-600" align="center">{{ number_format($row['fall']) }}</flux:table.cell>
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
            </div>
        </section>
    @endif

    @if ($stats['has_students'] && $stats['has_evals'])
        <x-slot:scripts>
            <script>
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

                new Chart(document.getElementById('chartAvgByCategory'), {
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

                new Chart(document.getElementById('chartVolumeBySemester'), {
                    type: 'bar',
                    data: {
                        labels: stats.volume_by_semester.labels,
                        datasets: [
                            { label: 'Spring', data: stats.volume_by_semester.spring, backgroundColor: '#2563eb', borderRadius: 6, borderSkipped: false },
                            { label: 'Fall', data: stats.volume_by_semester.fall, backgroundColor: '#f59e0b', borderRadius: 6, borderSkipped: false },
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

                new Chart(document.getElementById('chartScoreDistribution'), {
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

                new Chart(document.getElementById('chartCoverage'), {
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
            </script>
        </x-slot:scripts>
    @endif
</x-app-shell>
