<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'OMM Scholar Evaluations') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" style="font-family: 'Instrument Sans', system-ui, sans-serif;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">
                    OMM Scholar Evaluations — Overview
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Cohort-level insights from the Scholar Evaluation List. Refreshed every 10 minutes.
                </p>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('scholar') }}" class="text-sm text-slate-600 hover:text-slate-900 underline underline-offset-4">
                    View individual scholar →
                </a>
                @can('run-process')
                    <form method="POST" action="{{ route('process.run') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 active:scale-95 transition-transform"
                            onclick="this.disabled=true; this.textContent='Starting…'; this.form.submit();"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                            </svg>
                            Process evaluations
                        </button>
                    </form>
                @endcan
                @can('manage-users')
                    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-600 hover:text-slate-900 underline underline-offset-4">
                        Users
                    </a>
                @endcan
                <form method="POST" action="{{ route('saml.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-slate-600 hover:text-slate-900 underline underline-offset-4">Sign out</button>
                </form>
                <div class="text-xs text-slate-400">
                    Generated {{ \Illuminate\Support\Carbon::parse($stats['generated_at'])->diffForHumans() }}
                </div>
            </div>
        </header>

        @if (! $stats['has_scholars'])
            <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-10 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500 text-xl">∅</div>
                <h2 class="text-lg font-semibold text-slate-900">No scholar data available</h2>
                <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                    The destination REDCap project returned no records. Check that
                    <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">REDCAP_TOKEN</code>
                    and <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">REDCAP_URL</code>
                    are configured and the scholar roster has been loaded.
                </p>
            </section>
        @else
            {{-- KPI tiles --}}
            <section class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-8">
                @php
                    $kpis = [
                        ['label' => 'Scholars', 'value' => $stats['kpis']['total_scholars']],
                        ['label' => 'Evaluations', 'value' => $stats['kpis']['total_evals']],
                        ['label' => 'Overall Avg', 'value' => $stats['kpis']['overall_avg'] !== null ? number_format($stats['kpis']['overall_avg'], 1) : '—'],
                        ['label' => 'Scholars Evaluated', 'value' => $stats['kpis']['scholars_evaluated']],
                        ['label' => 'No Evals Yet', 'value' => $stats['kpis']['scholars_without_evals']],
                    ];
                @endphp
                @foreach ($kpis as $kpi)
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $kpi['value'] }}</div>
                    </div>
                @endforeach
            </section>

            @if (! $stats['has_evals'])
                <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-10 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 text-xl">◔</div>
                    <h2 class="text-lg font-semibold text-slate-900">No evaluations recorded yet</h2>
                    <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                        {{ $stats['kpis']['total_scholars'] }} scholar{{ $stats['kpis']['total_scholars'] === 1 ? '' : 's' }}
                        in the roster, but no evaluations have been aggregated so far. Charts will appear once data is available.
                    </p>
                </section>
            @else
                {{-- Charts grid --}}
                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
                        <h2 class="text-sm font-semibold text-slate-700 mb-1">Average Score by Category</h2>
                        <p class="text-xs text-slate-500 mb-4">Cohort-wide weighted average across all completed evaluations.</p>
                        <div class="h-72"><canvas id="chartAvgByCategory"></canvas></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
                        <h2 class="text-sm font-semibold text-slate-700 mb-1">Evaluation Volume by Semester</h2>
                        <p class="text-xs text-slate-500 mb-4">Total evaluations completed per category, split by semester.</p>
                        <div class="h-72"><canvas id="chartVolumeBySemester"></canvas></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
                        <h2 class="text-sm font-semibold text-slate-700 mb-1">Score Distribution</h2>
                        <p class="text-xs text-slate-500 mb-4">Count of scholar-semester averages falling into each score band, by category.</p>
                        <div class="h-72"><canvas id="chartScoreDistribution"></canvas></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
                        <h2 class="text-sm font-semibold text-slate-700 mb-1">Category Coverage</h2>
                        <p class="text-xs text-slate-500 mb-4">% of scholars with at least one evaluation in each category.</p>
                        <div class="h-72"><canvas id="chartCoverage"></canvas></div>
                    </div>
                </section>
            @endif
        @endif

        <footer class="mt-10 text-center text-xs text-slate-400">
            OMM Scholar Evaluation System
        </footer>
    </div>

    @if ($stats['has_scholars'] && $stats['has_evals'])
    <script>
        const stats = @json($stats);

        const palette = ['#2563eb', '#16a34a', '#ea580c', '#9333ea', '#0ea5e9', '#db2777'];
        const gridColor = 'rgba(100,116,139,0.12)';
        const tickColor = '#64748b';

        const baseOpts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: tickColor, font: { size: 11 } } },
                tooltip: { intersect: false, mode: 'index' },
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                y: { grid: { color: gridColor }, ticks: { color: tickColor }, beginAtZero: true },
            },
        };

        // Average by category
        new Chart(document.getElementById('chartAvgByCategory'), {
            type: 'bar',
            data: {
                labels: stats.category_labels,
                datasets: [{
                    label: 'Average Score',
                    data: stats.avg_by_category,
                    backgroundColor: palette.slice(0, stats.category_labels.length),
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseOpts,
                plugins: { ...baseOpts.plugins, legend: { display: false } },
                scales: {
                    ...baseOpts.scales,
                    y: { ...baseOpts.scales.y, suggestedMax: 100 },
                },
            },
        });

        // Volume by semester (grouped bar)
        new Chart(document.getElementById('chartVolumeBySemester'), {
            type: 'bar',
            data: {
                labels: stats.volume_by_semester.labels,
                datasets: [
                    { label: 'Spring', data: stats.volume_by_semester.spring, backgroundColor: '#2563eb', borderRadius: 6 },
                    { label: 'Fall', data: stats.volume_by_semester.fall, backgroundColor: '#f59e0b', borderRadius: 6 },
                ],
            },
            options: baseOpts,
        });

        // Score distribution (grouped bar by category)
        new Chart(document.getElementById('chartScoreDistribution'), {
            type: 'bar',
            data: {
                labels: stats.histogram.labels,
                datasets: stats.histogram.series.map((s, i) => ({
                    label: s.label,
                    data: s.data,
                    backgroundColor: palette[i % palette.length],
                    borderRadius: 6,
                })),
            },
            options: baseOpts,
        });

        // Coverage (horizontal bar, %)
        new Chart(document.getElementById('chartCoverage'), {
            type: 'bar',
            data: {
                labels: stats.category_labels,
                datasets: [{
                    label: '% of Scholars',
                    data: stats.coverage_pct,
                    backgroundColor: palette.slice(0, stats.category_labels.length),
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseOpts,
                indexAxis: 'y',
                plugins: { ...baseOpts.plugins, legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor, callback: v => v + '%' }, beginAtZero: true, suggestedMax: 100 },
                    y: { grid: { color: gridColor }, ticks: { color: tickColor } },
                },
            },
        });
    </script>
    @endif
</body>
</html>
