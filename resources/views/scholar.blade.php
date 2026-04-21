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

        {{-- Header --}}
        <header class="mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Scholar Detail</h1>
                <p class="mt-1 text-sm text-slate-500">Per-semester evaluation breakdown.</p>
            </div>
            @unless ($lock_selection ?? false)
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Back to overview</a>
            @endunless
        </header>

        {{-- Scholar picker (Service / Admin only) --}}
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

            {{-- Scholar name + shareable URL --}}
            <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
                <h2 class="text-2xl font-semibold text-slate-900">{{ $selected['name'] }}</h2>
                @if (! empty($shareable_url))
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-xs text-slate-500 whitespace-nowrap">Shareable link:</span>
                        <code class="truncate max-w-xs rounded bg-slate-100 px-2 py-1 text-xs text-slate-700 select-all">{{ $shareable_url }}</code>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText('{{ $shareable_url }}').then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 1500); })"
                            class="shrink-0 rounded bg-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-300"
                        >Copy</button>
                    </div>
                @endif
            </div>

            {{-- ── Monthly activity chart (all months across semesters) ── --}}
            @php
                $allMonthly = collect($semesters)->flatMap(fn($s) => $s['monthly'])->toArray();
                $monthKeys = [];
                foreach ($semesters as $sem) {
                    foreach (array_keys($sem['monthly']) as $m) {
                        $monthKeys[] = $m;
                    }
                }
                $monthKeys = array_values(array_unique($monthKeys));

                // Merge monthly counts across semesters for the same month key
                $mergedMonthly = [];
                foreach ($semesters as $sem) {
                    foreach ($sem['monthly'] as $month => $cats) {
                        foreach ($cats as $catKey => $cnt) {
                            $mergedMonthly[$month][$catKey] = ($mergedMonthly[$month][$catKey] ?? 0) + $cnt;
                        }
                    }
                }

                $categoryKeys = $semesters[0]['category_keys'] ?? [];
                $categoryLabels = $semesters[0]['category_labels'] ?? [];
            @endphp

            @if (count($monthKeys) > 0)
            <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-6">
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Monthly Activity</h3>
                <p class="text-xs text-slate-500 mb-4">Evaluations received per month, grouped by category.</p>
                <div class="h-64"><canvas id="chartMonthly"></canvas></div>
            </section>
            @endif

            {{-- ── Per-semester panels ── --}}
            @foreach ($semesters as $i => $sem)
            <section class="mb-8">
                {{-- Semester heading + score badges --}}
                <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">{{ $sem['label'] }} Semester</h3>
                    <div class="flex items-center gap-3 flex-wrap">
                        @if ($sem['leadership'] !== null)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-800">
                                Leadership: {{ $sem['leadership'] }}/10
                            </span>
                        @endif
                        @if ($sem['final_score'] !== null)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-900">
                                Final score: {{ number_format($sem['final_score'], 2) }}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($sem['total'] === 0)
                    <p class="text-sm text-slate-500">No evaluations recorded this semester.</p>
                @else

                {{-- Category score table + per-category chart side by side --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                    {{-- Score table --}}
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Category</th>
                                    <th class="px-4 py-3 text-center">Evals</th>
                                    <th class="px-4 py-3 text-center">Avg Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($sem['category_keys'] as $j => $catKey)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium text-slate-700">{{ $sem['category_labels'][$j] }}</td>
                                        <td class="px-4 py-2.5 text-center text-slate-600">{{ $sem['counts'][$j] }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            @if ($sem['averages'][$j] !== null)
                                                <span class="font-semibold text-slate-900">{{ number_format($sem['averages'][$j], 1) }}</span>
                                                <span class="text-xs text-slate-400">/ 100</span>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Per-category bar chart --}}
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
                        <p class="text-xs text-slate-500 mb-3">Evaluations per category</p>
                        <div class="h-52"><canvas id="chartSem{{ $i }}"></canvas></div>
                    </div>
                </div>

                {{-- Dates per category --}}
                @php $hasDates = collect($sem['dates'])->some(fn($e) => count($e) > 0); @endphp
                @if ($hasDates)
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-6">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">Evaluation Dates by Category</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach ($sem['category_keys'] as $j => $catKey)
                            @if (! empty($sem['dates'][$catKey]))
                                <div>
                                    <p class="text-xs font-medium text-slate-600 mb-1.5">{{ $sem['category_labels'][$j] }}</p>
                                    <ul class="space-y-1">
                                        @foreach ($sem['dates'][$catKey] as $entry)
                                            <li class="text-xs text-slate-600 leading-snug">{{ $entry }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Comments --}}
                @if (count($sem['comments']) > 0)
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">
                        Faculty Comments ({{ $sem['comments_count'] }})
                    </h4>
                    <div class="space-y-4">
                        @foreach ($sem['comments'] as $c)
                            <div class="border-l-2 border-slate-200 pl-4">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="font-medium text-xs text-slate-700">{{ $c['faculty'] }}</span>
                                    @if ($c['date'] !== '')
                                        <span class="text-xs text-slate-400">{{ $c['date'] }}</span>
                                    @endif
                                    @if ($c['category'] !== '')
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $c['category'] }}</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $c['comment'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @endif {{-- /total > 0 --}}
            </section>
            @endforeach

        @endif {{-- /selected --}}
    </div>

    @if ($selected)
    <script>
        const semesters   = @json($semesters);
        const categoryLabels = @json($categoryLabels);
        const categoryKeys   = @json($categoryKeys);
        const mergedMonthly  = @json($mergedMonthly ?? []);
        const monthKeys      = @json($monthKeys ?? []);

        const palette = ['#2563eb', '#16a34a', '#ea580c', '#9333ea'];

        // ── Monthly grouped bar chart ──────────────────────────────────────
        const monthlyCanvas = document.getElementById('chartMonthly');
        if (monthlyCanvas && monthKeys.length > 0) {
            new Chart(monthlyCanvas, {
                type: 'bar',
                data: {
                    labels: monthKeys,
                    datasets: categoryKeys.map((catKey, idx) => ({
                        label: categoryLabels[idx],
                        data: monthKeys.map(m => (mergedMonthly[m] && mergedMonthly[m][catKey]) || 0),
                        backgroundColor: palette[idx],
                        borderRadius: 4,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                    scales: {
                        x: { grid: { color: 'rgba(100,116,139,0.12)' }, ticks: { color: '#64748b', font: { size: 11 } } },
                        y: {
                            grid: { color: 'rgba(100,116,139,0.12)' },
                            ticks: { color: '#64748b', precision: 0, stepSize: 1 },
                            beginAtZero: true,
                            title: { display: true, text: '# Evals', color: '#94a3b8', font: { size: 11 } },
                        },
                    },
                },
            });
        }

        // ── Per-semester category charts ───────────────────────────────────
        semesters.forEach((sem, i) => {
            const canvas = document.getElementById('chartSem' + i);
            if (!canvas) return;

            new Chart(canvas, {
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
                                    return avg !== null ? 'Avg: ' + avg.toFixed(1) + ' / 100' : 'No score';
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
