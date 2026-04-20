<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scholar Detail — {{ config('app.name', 'OMM Scholar Evaluations') }}</title>

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
        <header class="mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Scholar Detail</h1>
                <p class="mt-1 text-sm text-slate-500">Per-semester evaluation counts by category for a selected scholar.</p>
            </div>
            @unless ($lock_selection ?? false)
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Back to overview</a>
            @endunless
        </header>

        {{-- Scholar picker --}}
        @unless ($lock_selection ?? false)
        <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-6">
            <form method="GET" action="{{ route('scholar') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label for="scholar-select" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Choose a scholar</label>
                    <select
                        id="scholar-select"
                        name="id"
                        onchange="this.form.submit()"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none"
                    >
                        <option value="">— Select —</option>
                        @foreach ($roster as $scholar)
                            <option
                                value="{{ $scholar['record_id'] }}"
                                @selected($selected && $selected['record_id'] === $scholar['record_id'])
                            >{{ $scholar['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript>
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">View</button>
                </noscript>
            </form>
        </section>
        @endunless

        @if (! $selected)
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-8 text-center text-sm text-slate-500">
                Select a scholar above to see their evaluation breakdown.
            </div>
        @else
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-slate-900">{{ $selected['name'] }}</h2>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ($semesters as $i => $sem)
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
                        <div class="flex items-baseline justify-between mb-1">
                            <h3 class="text-sm font-semibold text-slate-700">{{ $sem['label'] }}</h3>
                            <span class="text-xs text-slate-500">{{ $sem['total'] }} total eval{{ $sem['total'] === 1 ? '' : 's' }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">Evaluations received per category this semester.</p>
                        <div class="h-64"><canvas id="chartSem{{ $i }}"></canvas></div>
                    </div>
                @endforeach
            </section>
        @endif
    </div>

    @if ($selected)
        <script>
            const semesters = @json($semesters);
            const palette = ['#2563eb', '#16a34a', '#ea580c', '#9333ea'];

            semesters.forEach((sem, i) => {
                new Chart(document.getElementById('chartSem' + i), {
                    type: 'bar',
                    data: {
                        labels: sem.category_labels,
                        datasets: [{
                            label: '# Evaluations',
                            data: sem.counts,
                            backgroundColor: palette,
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    afterLabel: (ctx) => {
                                        const avg = sem.averages[ctx.dataIndex];
                                        return avg !== null ? 'Avg score: ' + avg.toFixed(1) : 'No score';
                                    },
                                },
                            },
                        },
                        scales: {
                            x: { grid: { color: 'rgba(100,116,139,0.12)' }, ticks: { color: '#64748b' } },
                            y: {
                                grid: { color: 'rgba(100,116,139,0.12)' },
                                ticks: { color: '#64748b', precision: 0, stepSize: 1 },
                                beginAtZero: true,
                            },
                        },
                    },
                });
            });
        </script>
    @endif
</body>
</html>
