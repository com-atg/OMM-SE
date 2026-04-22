@php
    $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
    $overallAverage = $stats['kpis']['overall_avg'];
    $evaluatedPct = $stats['kpis']['total_scholars'] > 0
        ? round($stats['kpis']['scholars_evaluated'] / $stats['kpis']['total_scholars'] * 100)
        : 0;

    $categoryTone = [
        ['bar' => 'bg-blue-500', 'soft' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/20'],
        ['bar' => 'bg-emerald-500', 'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20'],
        ['bar' => 'bg-orange-500', 'soft' => 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-400/20'],
        ['bar' => 'bg-violet-500', 'soft' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/20'],
    ];

    $categoryRows = collect($stats['category_labels'])->map(function (string $label, int $index) use ($stats, $categoryTone) {
        $spring = (int) ($stats['volume_by_semester']['spring'][$index] ?? 0);
        $fall = (int) ($stats['volume_by_semester']['fall'][$index] ?? 0);

        return [
            'label' => $label,
            'average' => (float) ($stats['avg_by_category'][$index] ?? 0),
            'coverage' => (float) ($stats['coverage_pct'][$index] ?? 0),
            'spring' => $spring,
            'fall' => $fall,
            'total' => $spring + $fall,
            'tone' => $categoryTone[$index % count($categoryTone)],
        ];
    });

    $kpis = [
        [
            'label' => 'Scholars',
            'value' => number_format($stats['kpis']['total_scholars']),
            'caption' => $stats['kpis']['scholars_evaluated'].' evaluated',
            'icon' => 'academic-cap',
            'badge' => $evaluatedPct.'% coverage',
            'color' => 'blue',
        ],
        [
            'label' => 'Evaluations',
            'value' => number_format($stats['kpis']['total_evals']),
            'caption' => 'Spring and fall combined',
            'icon' => 'clipboard-document-check',
            'badge' => 'Live roster',
            'color' => 'emerald',
        ],
        [
            'label' => 'Overall Avg',
            'value' => $overallAverage !== null ? number_format($overallAverage, 1) : '—',
            'caption' => $overallAverage !== null ? 'Weighted by evaluation count' : 'Awaiting scores',
            'icon' => 'chart-bar',
            'badge' => $overallAverage !== null && $overallAverage >= 90 ? 'Strong' : 'Monitor',
            'color' => $overallAverage !== null && $overallAverage >= 90 ? 'lime' : 'amber',
        ],
        [
            'label' => 'No Evals Yet',
            'value' => number_format($stats['kpis']['scholars_without_evals']),
            'caption' => 'Scholars needing coverage',
            'icon' => 'user-minus',
            'badge' => $stats['kpis']['scholars_without_evals'] === 0 ? 'Clear' : 'Follow up',
            'color' => $stats['kpis']['scholars_without_evals'] === 0 ? 'emerald' : 'rose',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'OMM Scholar Evaluations') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    @fluxAppearance

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="{{ url('/flux/flux.css') }}">
    @endif
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    @include('partials.impersonation-banner')

    <div class="min-h-screen">
        <flux:header sticky class="z-30 border-b border-zinc-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8 dark:border-white/10 dark:bg-zinc-950/85">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-lg bg-zinc-950 text-white shadow-sm dark:bg-white dark:text-zinc-950">
                    <flux:icon.academic-cap variant="mini" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold leading-5 tracking-tight">OMM ACE</span>
                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">Scholar Evaluations</span>
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

            <div class="hidden items-center gap-2 md:flex">
                <flux:badge color="zinc" icon="clock" class="font-medium">
                    {{ $generatedAt->diffForHumans() }}
                </flux:badge>

                <flux:dropdown x-data align="end">
                    <flux:button variant="subtle" square aria-label="Preferred color scheme">
                        <flux:icon.sun x-show="$flux.appearance === 'light'" variant="mini" />
                        <flux:icon.moon x-show="$flux.appearance === 'dark'" variant="mini" />
                        <flux:icon.computer-desktop x-show="$flux.appearance === 'system'" variant="mini" />
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                        <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                        <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>

            @can('run-process')
                <form method="POST" action="{{ route('process.run') }}" class="hidden sm:block">
                    @csrf
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="play"
                        onclick="this.disabled=true; this.innerText='Starting...'; this.form.submit();"
                    >
                        Process
                    </flux:button>
                </form>
            @endcan

            <form method="POST" action="{{ route('saml.logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" class="hidden sm:inline-flex">
                    Sign out
                </flux:button>
            </form>
        </flux:header>

        <div class="border-b border-zinc-200/70 bg-white px-4 py-2 lg:hidden dark:border-white/10 dark:bg-zinc-950">
            <flux:navbar scrollable>
                <flux:navbar.item href="{{ route('dashboard') }}" icon="chart-pie" current>Overview</flux:navbar.item>
                <flux:navbar.item href="{{ route('scholar') }}" icon="users">Scholars</flux:navbar.item>
                @can('manage-users')
                    <flux:navbar.item href="{{ route('admin.users.index') }}" icon="shield-check">Users</flux:navbar.item>
                @endcan
            </flux:navbar>
        </div>

        <main class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <section class="overflow-hidden rounded-xl bg-zinc-950 text-white shadow-sm ring-1 ring-black/10 dark:bg-zinc-900 dark:ring-white/10">
                <div class="relative grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)] lg:p-10">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.26),transparent_34%),linear-gradient(135deg,rgba(34,197,94,0.12),transparent_38%)]"></div>
                    <div class="relative">
                        <flux:badge color="sky" icon="sparkles" class="mb-5">ACE dashboard</flux:badge>
                        <h1 class="max-w-3xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                            OMM Scholar Evaluations
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-300 sm:text-base">
                            Cohort-level insight across teaching, clinic, research, and didactics evaluations, refreshed from the Scholar Evaluation List.
                        </p>

                        <div class="mt-8 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-lg bg-white/8 p-3 ring-1 ring-white/10">
                                <div class="text-2xl font-semibold tabular-nums">{{ number_format($stats['kpis']['total_scholars']) }}</div>
                                <div class="mt-1 text-xs text-zinc-400">Scholars</div>
                            </div>
                            <div class="rounded-lg bg-white/8 p-3 ring-1 ring-white/10">
                                <div class="text-2xl font-semibold tabular-nums">{{ number_format($stats['kpis']['total_evals']) }}</div>
                                <div class="mt-1 text-xs text-zinc-400">Evaluations</div>
                            </div>
                            <div class="rounded-lg bg-white/8 p-3 ring-1 ring-white/10">
                                <div class="text-2xl font-semibold tabular-nums">{{ $overallAverage !== null ? number_format($overallAverage, 1) : '—' }}</div>
                                <div class="mt-1 text-xs text-zinc-400">Overall avg</div>
                            </div>
                            <div class="rounded-lg bg-white/8 p-3 ring-1 ring-white/10">
                                <div class="text-2xl font-semibold tabular-nums">{{ $evaluatedPct }}%</div>
                                <div class="mt-1 text-xs text-zinc-400">Coverage</div>
                            </div>
                        </div>
                    </div>

                    <div class="relative rounded-xl bg-white p-5 text-zinc-950 shadow-2xl shadow-black/20 ring-1 ring-white/30 dark:bg-zinc-950 dark:text-white dark:ring-white/10">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <flux:heading size="lg">Category Pulse</flux:heading>
                                <flux:text class="mt-1">Average score and roster coverage</flux:text>
                            </div>
                            <flux:badge color="{{ $stats['has_evals'] ? 'emerald' : 'amber' }}" icon="{{ $stats['has_evals'] ? 'check-circle' : 'exclamation-triangle' }}">
                                {{ $stats['has_evals'] ? 'Active' : 'Waiting' }}
                            </flux:badge>
                        </div>

                        <div class="mt-6 space-y-5">
                            @foreach ($categoryRows as $row)
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium">{{ $row['label'] }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($row['coverage'], 1) }}% covered</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10">
                                        <div class="h-full rounded-full {{ $row['tone']['bar'] }}" style="width: {{ min(100, max(0, $row['coverage'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            @if (! $stats['has_scholars'])
                <flux:card class="p-10 text-center">
                    <div class="mx-auto mb-5 grid size-12 place-items-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300">
                        <flux:icon.inbox variant="mini" />
                    </div>
                    <flux:heading size="lg">No scholar data available</flux:heading>
                    <flux:text class="mx-auto mt-2 max-w-lg">
                        The destination REDCap project returned no records. Check that REDCAP_TOKEN and REDCAP_URL are configured and the scholar roster has been loaded.
                    </flux:text>
                </flux:card>
            @else
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($kpis as $kpi)
                        <flux:card class="overflow-hidden p-5">
                            <div class="flex items-start justify-between gap-4">
                                <span class="grid size-10 place-items-center rounded-lg bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200">
                                    <flux:icon :name="$kpi['icon']" variant="mini" />
                                </span>
                                <flux:badge color="{{ $kpi['color'] }}">{{ $kpi['badge'] }}</flux:badge>
                            </div>
                            <div class="mt-6">
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $kpi['label'] }}</div>
                                <div class="mt-1 text-3xl font-semibold tracking-tight tabular-nums text-zinc-950 dark:text-white">{{ $kpi['value'] }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $kpi['caption'] }}</div>
                            </div>
                        </flux:card>
                    @endforeach
                </section>

                @if (! $stats['has_evals'])
                    <flux:card class="p-10 text-center">
                        <div class="mx-auto mb-5 grid size-12 place-items-center rounded-lg bg-amber-50 text-amber-600 ring-1 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                            <flux:icon.clock variant="mini" />
                        </div>
                        <flux:heading size="lg">No evaluations recorded yet</flux:heading>
                        <flux:text class="mx-auto mt-2 max-w-lg">
                            {{ $stats['kpis']['total_scholars'] }} scholar{{ $stats['kpis']['total_scholars'] === 1 ? '' : 's' }} are in the roster. Charts will populate once evaluations are aggregated.
                        </flux:text>
                    </flux:card>
                @else
                    <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <flux:card class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <flux:heading size="lg">Average Score by Category</flux:heading>
                                    <flux:text class="mt-1">Weighted cohort averages across completed evaluations.</flux:text>
                                </div>
                                <flux:badge color="blue">Score</flux:badge>
                            </div>
                            <div class="mt-6 h-72"><canvas id="chartAvgByCategory"></canvas></div>
                        </flux:card>

                        <flux:card class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <flux:heading size="lg">Evaluation Volume</flux:heading>
                                    <flux:text class="mt-1">Completed evaluations by category and semester.</flux:text>
                                </div>
                                <flux:badge color="amber">Spring/Fall</flux:badge>
                            </div>
                            <div class="mt-6 h-72"><canvas id="chartVolumeBySemester"></canvas></div>
                        </flux:card>

                        <flux:card class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <flux:heading size="lg">Score Distribution</flux:heading>
                                    <flux:text class="mt-1">Scholar-semester averages grouped into score bands.</flux:text>
                                </div>
                                <flux:badge color="violet">Bands</flux:badge>
                            </div>
                            <div class="mt-6 h-72"><canvas id="chartScoreDistribution"></canvas></div>
                        </flux:card>

                        <flux:card class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <flux:heading size="lg">Category Coverage</flux:heading>
                                    <flux:text class="mt-1">Share of scholars with at least one evaluation.</flux:text>
                                </div>
                                <flux:badge color="emerald">Coverage</flux:badge>
                            </div>
                            <div class="mt-6 h-72"><canvas id="chartCoverage"></canvas></div>
                        </flux:card>
                    </section>

                    <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
                        <flux:card class="overflow-hidden p-0">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <flux:heading size="lg">Category Detail</flux:heading>
                                        <flux:text class="mt-1">Volume, average score, and coverage in one operational view.</flux:text>
                                    </div>
                                    <flux:button href="{{ route('scholar') }}" variant="ghost" icon:trailing="arrow-right">
                                        Scholar detail
                                    </flux:button>
                                </div>
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
                                                <div class="flex items-center gap-3">
                                                    <span class="size-2.5 rounded-full {{ $row['tone']['bar'] }}"></span>
                                                    <div>
                                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $row['label'] }}</div>
                                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($row['total']) }} total evals</div>
                                                    </div>
                                                </div>
                                            </flux:table.cell>
                                            <flux:table.cell align="end">
                                                <span class="font-semibold tabular-nums">{{ $row['average'] > 0 ? number_format($row['average'], 1) : '—' }}</span>
                                            </flux:table.cell>
                                            <flux:table.cell align="end">{{ number_format($row['spring']) }}</flux:table.cell>
                                            <flux:table.cell align="end">{{ number_format($row['fall']) }}</flux:table.cell>
                                            <flux:table.cell align="end">
                                                <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 {{ $row['tone']['soft'] }}">
                                                    {{ number_format($row['coverage'], 1) }}%
                                                </span>
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </flux:card>

                        <flux:card class="p-5">
                            <flux:heading size="lg">Operational Notes</flux:heading>
                            <div class="mt-5 space-y-4">
                                <div class="rounded-lg bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-white/5 dark:ring-white/10">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <flux:icon.arrow-path variant="mini" class="text-blue-500" />
                                        Data freshness
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        Generated {{ $generatedAt->format('M j, Y g:i A') }} and cached for ten minutes.
                                    </p>
                                </div>

                                <div class="rounded-lg bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-white/5 dark:ring-white/10">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <flux:icon.user-minus variant="mini" class="text-rose-500" />
                                        Follow-up queue
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        {{ number_format($stats['kpis']['scholars_without_evals']) }} scholar{{ $stats['kpis']['scholars_without_evals'] === 1 ? '' : 's' }} currently have no evaluations recorded.
                                    </p>
                                </div>

                                @can('run-process')
                                    <form method="POST" action="{{ route('process.run') }}">
                                        @csrf
                                        <flux:button type="submit" variant="primary" icon="play" class="w-full">
                                            Process evaluations
                                        </flux:button>
                                    </form>
                                @endcan
                            </div>
                        </flux:card>
                    </section>
                @endif
            @endif
        </main>
    </div>

    @if ($stats['has_scholars'] && $stats['has_evals'])
        <script>
            const stats = @json($stats);
            const palette = ['#3b82f6', '#10b981', '#f97316', '#8b5cf6', '#0ea5e9', '#e11d48'];
            const softPalette = ['rgba(59, 130, 246, .84)', 'rgba(16, 185, 129, .84)', 'rgba(249, 115, 22, .84)', 'rgba(139, 92, 246, .84)'];
            const gridColor = 'rgba(113, 113, 122, 0.16)';
            const tickColor = '#71717a';

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
                        backgroundColor: '#18181b',
                        titleColor: '#fff',
                        bodyColor: '#e4e4e7',
                        borderColor: 'rgba(255,255,255,.10)',
                        borderWidth: 1,
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
                        backgroundColor: softPalette,
                        borderRadius: 8,
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
                        { label: 'Spring', data: stats.volume_by_semester.spring, backgroundColor: '#3b82f6', borderRadius: 8, borderSkipped: false },
                        { label: 'Fall', data: stats.volume_by_semester.fall, backgroundColor: '#f59e0b', borderRadius: 8, borderSkipped: false },
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
                        borderRadius: 8,
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
                        backgroundColor: softPalette,
                        borderRadius: 8,
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
