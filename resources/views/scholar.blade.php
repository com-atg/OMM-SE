@php
    $categoryKeys = $semesters[0]['category_keys'] ?? [];
    $categoryLabels = $semesters[0]['category_labels'] ?? [];
    $monthKeys = [];
    $mergedMonthly = [];

    foreach ($semesters as $sem) {
        foreach (array_keys($sem['monthly']) as $month) {
            $monthKeys[] = $month;
        }

        foreach ($sem['monthly'] as $month => $cats) {
            foreach ($cats as $catKey => $count) {
                $mergedMonthly[$month][$catKey] = ($mergedMonthly[$month][$catKey] ?? 0) + $count;
            }
        }
    }

    $monthKeys = array_values(array_unique($monthKeys));
    $totalEvaluations = collect($semesters)->sum('total');
    $totalComments = collect($semesters)->sum('comments_count');
    $selectedInitials = collect(explode(' ', $selected['name'] ?? ''))
        ->filter()
        ->map(fn (string $part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<x-app-shell
    title="Scholar Detail"
    active="scholars"
    eyebrow="Scholar Detail"
    heading="Individual Scholar Evaluation"
    subheading="Review semester scores, evaluation cadence, faculty comments, and supporting detail for a selected scholar."
>
    <x-slot:head>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    </x-slot:head>

    @unless ($lock_selection ?? false)
        <x-slot:headerActions>
            <flux:button href="{{ route('dashboard') }}" variant="ghost" icon="arrow-left">
                Dashboard
            </flux:button>
        </x-slot:headerActions>
    @endunless

    @unless ($lock_selection ?? false)
        <section class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <form method="GET" action="{{ route('scholar') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
                <flux:select
                    class="min-w-0 flex-1"
                    name="id"
                    variant="listbox"
                    searchable
                    clearable
                    value="{{ $selected['record_id'] ?? '' }}"
                    label="Choose a scholar"
                    placeholder="Search by scholar name..."
                    empty="No scholars found"
                >
                    @foreach ($roster as $scholar)
                        <flux:select.option value="{{ $scholar['record_id'] }}">
                            {{ $scholar['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass">
                        View
                    </flux:button>

                    @if ($selected)
                        <flux:button href="{{ route('scholar') }}" variant="ghost" icon="x-mark">
                            Clear
                        </flux:button>
                    @endif
                </div>
            </form>
        </section>
    @endunless

    @if (! $selected)
        <section class="rounded-lg border border-slate-200 bg-white/84 p-10 text-center shadow-sm">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-slate-100 text-slate-500">
                <flux:icon.user-group variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-950">Select a scholar</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
                Use the scholar selector to open an individual evaluation profile.
            </p>
        </section>
    @else
        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[320px_1fr]">
            <aside class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                    @if ($selected['photo_url'])
                        <img
                            src="{{ $selected['photo_url'] }}"
                            alt="{{ $selected['name'] }}"
                            class="aspect-[4/5] w-full object-cover"
                            onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('grid');"
                        >
                        <div class="hidden aspect-[4/5] w-full place-items-center bg-slate-900 text-5xl font-bold text-white">
                            {{ $selectedInitials }}
                        </div>
                    @else
                        <div class="grid aspect-[4/5] w-full place-items-center bg-slate-900 text-5xl font-bold text-white">
                            {{ $selectedInitials }}
                        </div>
                    @endif
                </div>

                <div class="mt-5">
                    <div class="text-[0.7rem] font-bold uppercase tracking-[0.32em] text-sky-700">Scholar Profile</div>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $selected['name'] }}</h2>

                    <dl class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Record</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ $selected['record_id'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Datatel</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ $selected['datatelid'] ?? '-' }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Evals</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ number_format($totalEvaluations) }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Comments</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ number_format($totalComments) }}</dd>
                        </div>
                    </dl>

                    @if (! empty($shareable_url))
                        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Shareable Link</div>
                            <code class="mt-2 block truncate rounded-md bg-white px-2.5 py-2 text-xs text-slate-600 ring-1 ring-slate-200">{{ $shareable_url }}</code>
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="clipboard"
                                class="mt-2 w-full"
                                onclick="copyScholarLink(this, @js($shareable_url))"
                            >
                                Copy link
                            </flux:button>
                        </div>
                    @endif
                </div>
            </aside>

            <div class="flex min-w-0 flex-col gap-5">
                @if (count($monthKeys) > 0)
                    <section class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Activity</div>
                                <h2 class="mt-2 text-lg font-bold text-slate-950">Monthly Evaluation Volume</h2>
                            </div>
                            <flux:badge color="sky">{{ count($monthKeys) }} months</flux:badge>
                        </div>
                        <div class="mt-5 h-72"><canvas id="chartMonthly"></canvas></div>
                    </section>
                @endif

                @foreach ($semesters as $i => $sem)
                    <section class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">{{ $sem['label'] }} Semester</div>
                                <h2 class="mt-2 text-lg font-bold text-slate-950">Evaluation Summary</h2>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($sem['leadership'] !== null)
                                    <flux:badge color="violet">Leadership {{ $sem['leadership'] }}/10</flux:badge>
                                @endif
                                @if ($sem['final_score'] !== null)
                                    <flux:badge color="blue">Final {{ number_format($sem['final_score'], 2) }}</flux:badge>
                                @endif
                                <flux:badge color="zinc">{{ number_format($sem['total']) }} evals</flux:badge>
                            </div>
                        </div>

                        @if ($sem['total'] === 0)
                            <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                                No evaluations recorded this semester.
                            </div>
                        @else
                            <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white/90">
                                    <flux:table>
                                        <flux:table.columns>
                                            <flux:table.column class="bg-slate-50/90 ps-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Category</flux:table.column>
                                            <flux:table.column class="bg-slate-50/90 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="end">Evals</flux:table.column>
                                            <flux:table.column class="bg-slate-50/90 pe-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="end">Avg</flux:table.column>
                                        </flux:table.columns>
                                        <flux:table.rows>
                                            @foreach ($sem['category_keys'] as $j => $catKey)
                                                <flux:table.row class="transition hover:bg-slate-50/80">
                                                    <flux:table.cell class="ps-4 font-semibold text-slate-900">{{ $sem['category_labels'][$j] }}</flux:table.cell>
                                                    <flux:table.cell class="font-medium tabular-nums text-slate-600" align="end">{{ $sem['counts'][$j] }}</flux:table.cell>
                                                    <flux:table.cell class="pe-4 tabular-nums" align="end">
                                                        @if ($sem['averages'][$j] !== null)
                                                            <span class="font-semibold text-slate-950">{{ number_format($sem['averages'][$j], 1) }}</span>
                                                            <span class="text-xs text-slate-400">/100</span>
                                                        @else
                                                            <span class="text-slate-400">-</span>
                                                        @endif
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </div>

                                <div class="rounded-lg border border-slate-200 bg-white/90 p-4">
                                    <div class="mb-3 text-sm font-semibold text-slate-700">Evaluations by Category</div>
                                    <div class="h-56"><canvas id="chartSem{{ $i }}"></canvas></div>
                                </div>
                            </div>

                            @php $hasDates = collect($sem['dates'])->some(fn ($entries) => count($entries) > 0); @endphp
                            @if ($hasDates)
                                <div class="mt-5 rounded-lg border border-slate-200 bg-white/90 p-5">
                                    <div class="mb-4 text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Evaluation Dates by Category</div>
                                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($sem['category_keys'] as $j => $catKey)
                                            @if (! empty($sem['dates'][$catKey]))
                                                <div class="min-w-0">
                                                    <div class="mb-2 flex items-center gap-2">
                                                        <span class="size-2 rounded-full bg-sky-500"></span>
                                                        <p class="text-sm font-semibold text-slate-800">{{ $sem['category_labels'][$j] }}</p>
                                                    </div>
                                                    <ul class="space-y-1.5">
                                                        @foreach ($sem['dates'][$catKey] as $entry)
                                                            <li class="text-sm leading-5 text-slate-600">{{ $entry }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (count($sem['comments']) > 0)
                                <div class="mt-5 rounded-lg border border-slate-200 bg-white/90 p-5">
                                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Faculty Comments</div>
                                        <flux:badge color="zinc">{{ $sem['comments_count'] }}</flux:badge>
                                    </div>

                                    <div class="space-y-4">
                                        @foreach ($sem['comments'] as $comment)
                                            <article class="border-l-2 border-sky-200 pl-4">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-semibold text-slate-900">{{ $comment['faculty'] }}</span>
                                                    @if ($comment['date'] !== '')
                                                        <span class="text-sm text-slate-400">{{ $comment['date'] }}</span>
                                                    @endif
                                                    @if ($comment['category'] !== '')
                                                        <flux:badge size="sm" color="zinc">{{ $comment['category'] }}</flux:badge>
                                                    @endif
                                                </div>
                                                <p class="mt-2 text-base leading-7 text-slate-700">{{ $comment['comment'] }}</p>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </section>
                @endforeach
            </div>
        </section>

        <x-slot:scripts>
            <script>
                function copyScholarLink(button, url) {
                    navigator.clipboard.writeText(url).then(() => {
                        const original = button.innerHTML;
                        button.innerHTML = original.replace('Copy link', 'Copied');
                        setTimeout(() => {
                            button.innerHTML = original;
                        }, 1500);
                    });
                }

                const semesters = @json($semesters);
                const categoryLabels = @json($categoryLabels);
                const categoryKeys = @json($categoryKeys);
                const mergedMonthly = @json($mergedMonthly);
                const monthKeys = @json($monthKeys);
                const palette = ['#2563eb', '#059669', '#d97706', '#7c3aed'];
                const gridColor = 'rgba(100, 116, 139, 0.16)';
                const tickColor = '#64748b';

                const monthlyCanvas = document.getElementById('chartMonthly');
                if (monthlyCanvas && monthKeys.length > 0) {
                    new Chart(monthlyCanvas, {
                        type: 'bar',
                        data: {
                            labels: monthKeys,
                            datasets: categoryKeys.map((catKey, index) => ({
                                label: categoryLabels[index],
                                data: monthKeys.map((month) => (mergedMonthly[month] && mergedMonthly[month][catKey]) || 0),
                                backgroundColor: palette[index],
                                borderRadius: 6,
                            })),
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { color: tickColor, boxWidth: 10, font: { size: 11 } } },
                            },
                            scales: {
                                x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: gridColor },
                                    ticks: { color: tickColor, precision: 0, stepSize: 1 },
                                    title: { display: true, text: '# Evals', color: '#94a3b8', font: { size: 11 } },
                                },
                            },
                        },
                    });
                }

                semesters.forEach((sem, index) => {
                    const canvas = document.getElementById('chartSem' + index);
                    if (! canvas) {
                        return;
                    }

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
                                x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0, stepSize: 1 } },
                            },
                        },
                    });
                });
            </script>
        </x-slot:scripts>
    @endif
</x-app-shell>
