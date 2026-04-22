@php
    $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
    $overallAverage = $stats['kpis']['overall_avg'];
    $evaluatedPct = $stats['kpis']['total_scholars'] > 0
        ? round($stats['kpis']['scholars_evaluated'] / $stats['kpis']['total_scholars'] * 100)
        : 0;

    $kpis = [
        ['label' => 'Scholars', 'value' => number_format($stats['kpis']['total_scholars']), 'sub' => $stats['kpis']['scholars_evaluated'].' with evaluations', 'icon' => 'academic-cap'],
        ['label' => 'Evaluations', 'value' => number_format($stats['kpis']['total_evals']), 'sub' => 'Across spring and fall', 'icon' => 'clipboard-document-check'],
        ['label' => 'Overall average', 'value' => $overallAverage !== null ? number_format($overallAverage, 1) : '-', 'sub' => 'Weighted by evaluation count', 'icon' => 'chart-bar'],
        ['label' => 'Coverage', 'value' => $evaluatedPct.'%', 'sub' => number_format($stats['kpis']['scholars_without_evals']).' without evaluations', 'icon' => 'shield-check'],
    ];

    $categoryRows = collect($stats['category_labels'])->map(function (string $label, int $index) use ($stats) {
        $spring = (int) ($stats['volume_by_semester']['spring'][$index] ?? 0);
        $fall = (int) ($stats['volume_by_semester']['fall'][$index] ?? 0);

        return [
            'label' => $label,
            'average' => (float) ($stats['avg_by_category'][$index] ?? 0),
            'coverage' => (float) ($stats['coverage_pct'][$index] ?? 0),
            'spring' => $spring,
            'fall' => $fall,
            'total' => $spring + $fall,
        ];
    });
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'OMM Scholar Evaluations') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="{{ url('/flux/flux.css') }}">
    @endif
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    @include('partials.impersonation-banner')

    <div class="min-h-screen">
        <flux:header sticky class="z-30 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-lg bg-slate-900 text-white">
                    <flux:icon.academic-cap variant="mini" />
                </span>
                <span>
                    <span class="block text-sm font-semibold leading-5">OMM ACE</span>
                    <span class="block text-xs text-slate-500">Scholar Evaluations</span>
                </span>
            </a>

            <flux:navbar class="-mb-px ml-8 hidden lg:flex">
                <flux:navbar.item href="{{ route('dashboard') }}" icon="chart-pie" current>Overview</flux:navbar.item>
                <flux:navbar.item href="{{ route('scholar') }}" icon="users">Scholars</flux:navbar.item>
                @can('manage-users')
                    <flux:navbar.item href="{{ route('admin.users.index') }}" icon="shield-check">Users</flux:navbar.item>
                @endcan
            </flux:navbar>

            <flux:spacer />

            <div class="hidden items-center gap-3 md:flex">
                <span class="text-xs text-slate-500">
                    Refreshed {{ $generatedAt->diffForHumans() }}
                </span>

                @can('run-process')
                    <form method="POST" action="{{ route('process.run') }}">
                        @csrf
                        <flux:button
                            type="submit"
                            variant="primary"
                            icon="play"
                            onclick="this.disabled=true; this.innerText='Starting...'; this.form.submit();"
                        >
                            Process evaluations
                        </flux:button>
                    </form>
                @endcan
            </div>

            <form method="POST" action="{{ route('saml.logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" class="hidden sm:inline-flex">
                    Sign out
                </flux:button>
            </form>
        </flux:header>

        <div class="border-b border-slate-200 bg-white px-4 py-2 lg:hidden">
            <flux:navbar scrollable>
                <flux:navbar.item href="{{ route('dashboard') }}" icon="chart-pie" current>Overview</flux:navbar.item>
                <flux:navbar.item href="{{ route('scholar') }}" icon="users">Scholars</flux:navbar.item>
                @can('manage-users')
                    <flux:navbar.item href="{{ route('admin.users.index') }}" icon="shield-check">Users</flux:navbar.item>
                @endcan
            </flux:navbar>
        </div>

        <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <flux:badge color="blue" icon="chart-bar">Dashboard</flux:badge>
                        @if ($stats['is_stale'] ?? false)
                            <flux:badge color="amber" icon="clock">Cached snapshot</flux:badge>
                        @endif
                    </div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">OMM Scholar Evaluations</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Cohort-level reporting for teaching, clinic, research, and didactics evaluations.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button href="{{ route('scholar') }}" variant="ghost" icon="users">Scholar detail</flux:button>
                    @can('manage-users')
                        <flux:button href="{{ route('admin.users.index') }}" variant="ghost" icon="shield-check">Manage users</flux:button>
                    @endcan
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
                    <flux:card class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-slate-500">{{ $kpi['label'] }}</div>
                                <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 tabular-nums">{{ $kpi['value'] }}</div>
                            </div>
                            <span class="grid size-10 place-items-center rounded-lg bg-slate-100 text-slate-600">
                                <flux:icon :name="$kpi['icon']" variant="mini" />
                            </span>
                        </div>
                        <div class="mt-4 text-sm text-slate-500">{{ $kpi['sub'] }}</div>
                    </flux:card>
                @endforeach
            </section>

            @if (! $stats['has_scholars'])
                <flux:card class="p-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="grid size-11 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
                            <flux:icon.inbox variant="mini" />
                        </div>
                        <div class="max-w-2xl">
                            <flux:heading size="lg">No scholar records are available</flux:heading>
                            <flux:text class="mt-2">
                                The dashboard could not find destination REDCap records. If the roster should be populated, check the REDCap connection settings and clear the dashboard cache after correcting them.
                            </flux:text>
                        </div>
                    </div>
                </flux:card>
            @elseif (! $stats['has_evals'])
                <flux:card class="p-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="grid size-11 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600">
                            <flux:icon.clock variant="mini" />
                        </div>
                        <div class="max-w-2xl">
                            <flux:heading size="lg">No evaluations recorded yet</flux:heading>
                            <flux:text class="mt-2">
                                {{ $stats['kpis']['total_scholars'] }} scholar{{ $stats['kpis']['total_scholars'] === 1 ? '' : 's' }} are available. Charts will appear once evaluation records have been processed.
                            </flux:text>
                        </div>
                    </div>
                </flux:card>
            @else
                <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <flux:card class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <flux:heading size="lg">Average Score by Category</flux:heading>
                                <flux:text class="mt-1">Weighted cohort average across completed evaluations.</flux:text>
                            </div>
                            <flux:badge color="blue">Score</flux:badge>
                        </div>
                        <div class="mt-6 h-72"><canvas id="chartAvgByCategory"></canvas></div>
                    </flux:card>

                    <flux:card class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <flux:heading size="lg">Evaluation Volume</flux:heading>
                                <flux:text class="mt-1">Completed evaluations split by semester.</flux:text>
                            </div>
                            <flux:badge color="amber">Spring/Fall</flux:badge>
                        </div>
                        <div class="mt-6 h-72"><canvas id="chartVolumeBySemester"></canvas></div>
                    </flux:card>

                    <flux:card class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <flux:heading size="lg">Score Distribution</flux:heading>
                                <flux:text class="mt-1">Scholar-semester averages grouped by score band.</flux:text>
                            </div>
                            <flux:badge color="violet">Bands</flux:badge>
                        </div>
                        <div class="mt-6 h-72"><canvas id="chartScoreDistribution"></canvas></div>
                    </flux:card>

                    <flux:card class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <flux:heading size="lg">Category Coverage</flux:heading>
                                <flux:text class="mt-1">Scholars with at least one evaluation per category.</flux:text>
                            </div>
                            <flux:badge color="emerald">Coverage</flux:badge>
                        </div>
                        <div class="mt-6 h-72"><canvas id="chartCoverage"></canvas></div>
                    </flux:card>
                </section>

                <flux:card class="overflow-hidden p-0">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <flux:heading size="lg">Category Detail</flux:heading>
                        <flux:text class="mt-1">Volume, score, and coverage by evaluation category.</flux:text>
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Category</flux:table.column>
                            <flux:table.column align="end">Avg</flux:table.column>
                            <flux:table.column align="end">Spring</flux:table.column>
                            <flux:table.column align="end">Fall</flux:table.column>
                            <flux:table.column align="end">Coverage</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($categoryRows as $row)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <div class="font-medium text-slate-900">{{ $row['label'] }}</div>
                                        <div class="text-xs text-slate-500">{{ number_format($row['total']) }} total evaluations</div>
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        <span class="font-semibold tabular-nums">{{ $row['average'] > 0 ? number_format($row['average'], 1) : '-' }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($row['spring']) }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($row['fall']) }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($row['coverage'], 1) }}%</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endif
        </main>
    </div>

    @if ($stats['has_scholars'] && $stats['has_evals'])
        <script>
            const stats = @json($stats);
            const palette = ['#2563eb', '#059669', '#ea580c', '#7c3aed', '#0ea5e9', '#db2777'];
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
                        y: { ...baseOptions.scales.y, suggestedMax: 100 },
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
                options: baseOptions,
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
                options: baseOptions,
            });

            new Chart(document.getElementById('chartCoverage'), {
                type: 'bar',
                data: {
                    labels: stats.category_labels,
                    datasets: [{
                        label: '% of Scholars',
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
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: {
                                ...baseOptions.scales.x.ticks,
                                callback: (value) => `${value}%`,
                            },
                        },
                        y: baseOptions.scales.y,
                    },
                },
            });
        </script>
    @endif

    @livewireScripts
    @fluxScripts
</body>
</html>
