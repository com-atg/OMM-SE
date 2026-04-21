<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processing PID {{ $pid }} — {{ config('app.name', 'OMM Scholar Evaluations') }}</title>

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
    @include('partials.impersonation-banner')
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">
                    Processing Source Project
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    PID <span class="font-mono font-semibold text-slate-700">{{ $pid }}</span> — aggregating evaluations and pushing to destination.
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-4">← Dashboard</a>
        </header>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div id="spinner" class="h-5 w-5 rounded-full border-2 border-slate-300 border-t-blue-600 animate-spin"></div>
                    <div id="statusLabel" class="text-sm font-medium text-slate-700">Starting…</div>
                </div>
                <div class="text-xs text-slate-500" id="progressText">—</div>
            </div>

            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-blue-600 h-full transition-all duration-300" style="width: 0%"></div>
            </div>

            <div class="mt-6 h-64"><canvas id="progressChart"></canvas></div>
        </section>

        <section id="summary" class="hidden bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-6">
            <div id="summaryBanner" class="rounded-lg px-4 py-3 mb-5 text-sm font-medium"></div>

            <h2 class="text-sm font-semibold text-slate-700 mb-3">Run summary</h2>
            <dl id="summaryStats" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5"></dl>

            <div id="skipSection" class="hidden">
                <h3 class="text-xs uppercase tracking-wide text-slate-500 mb-2">Skipped groups</h3>
                <ul id="skipList" class="text-sm text-slate-700 space-y-1"></ul>
            </div>

            <div id="errorSection" class="hidden mt-4">
                <h3 class="text-xs uppercase tracking-wide text-rose-500 mb-2">Error</h3>
                <pre id="errorText" class="text-xs bg-rose-50 text-rose-800 p-3 rounded-lg whitespace-pre-wrap"></pre>
            </div>
        </section>
    </div>

    <script>
        const jobId = @json($jobId);
        const statusUrl = "{{ route('process.status', ['jobId' => '__ID__']) }}".replace('__ID__', jobId);

        const statusLabels = {
            pending: 'Queued — preparing…',
            running: 'Processing…',
            complete: 'Completed',
            failed: 'Failed',
            unknown: 'Unknown job',
        };

        const chart = new Chart(document.getElementById('progressChart'), {
            type: 'bar',
            data: {
                labels: ['Processed', 'Updated', 'Skipped', 'Failed'],
                datasets: [{
                    label: 'Count',
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#dc2626'],
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(100,116,139,0.12)' }, ticks: { color: '#64748b' } },
                    y: { grid: { color: 'rgba(100,116,139,0.12)' }, ticks: { color: '#64748b', precision: 0 }, beginAtZero: true },
                },
            },
        });

        let pollTimer = null;

        async function poll() {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Status fetch failed: ' + res.status);
                const state = await res.json();
                render(state);

                if (state.status === 'complete' || state.status === 'failed') {
                    clearInterval(pollTimer);
                    onDone(state);
                }
            } catch (err) {
                console.error(err);
            }
        }

        function render(state) {
            const label = statusLabels[state.status] || state.status;
            document.getElementById('statusLabel').textContent = label;

            const total = Math.max(state.total_groups || 0, 1);
            const processed = state.processed_groups || 0;
            const pct = Math.min(100, Math.round(processed / total * 100));
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressText').textContent =
                state.total_groups > 0
                    ? `${processed} / ${state.total_groups} groups (${pct}%)`
                    : (state.total_records ? `${state.total_records} records queued` : 'Fetching records…');

            const skipped = Object.values(state.skip_reasons || {}).reduce((a, b) => a + Number(b), 0);
            chart.data.datasets[0].data = [
                processed,
                state.updated || 0,
                skipped,
                state.failed || 0,
            ];
            chart.update('none');
        }

        function onDone(state) {
            document.getElementById('spinner').classList.add('hidden');

            const summary = document.getElementById('summary');
            summary.classList.remove('hidden');

            const banner = document.getElementById('summaryBanner');
            if (state.status === 'complete') {
                banner.className = 'rounded-lg px-4 py-3 mb-5 text-sm font-medium bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200';
                banner.textContent = `✓ Completed — processed ${state.processed_groups || 0} scholar-semester group(s).`;
            } else {
                banner.className = 'rounded-lg px-4 py-3 mb-5 text-sm font-medium bg-rose-50 text-rose-800 ring-1 ring-rose-200';
                banner.textContent = '✗ Failed — see error details below.';
            }

            const skipped = Object.values(state.skip_reasons || {}).reduce((a, b) => a + Number(b), 0);
            const duration = (state.started_at && state.finished_at)
                ? Math.round((new Date(state.finished_at) - new Date(state.started_at)) / 1000) + 's'
                : '—';

            const stats = [
                ['Source records', state.total_records || 0],
                ['Groups', state.total_groups || 0],
                ['Updated', state.updated || 0],
                ['Skipped', skipped],
                ['Failed', state.failed || 0],
                ['Duration', duration],
                ['PID', @json($pid)],
                ['Job ID', jobId.split('-')[0] + '…'],
            ];
            const dl = document.getElementById('summaryStats');
            dl.innerHTML = stats.map(([k, v]) => `
                <div class="bg-slate-50 rounded-lg px-3 py-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">${k}</dt>
                    <dd class="text-base font-semibold text-slate-900 mt-0.5">${v}</dd>
                </div>
            `).join('');

            const reasons = state.skip_reasons || {};
            const reasonKeys = Object.keys(reasons);
            if (reasonKeys.length > 0) {
                document.getElementById('skipSection').classList.remove('hidden');
                document.getElementById('skipList').innerHTML = reasonKeys.map(
                    r => `<li><span class="font-mono text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded">${r}</span> <span class="text-slate-600">× ${reasons[r]}</span></li>`
                ).join('');
            }

            if (state.error) {
                document.getElementById('errorSection').classList.remove('hidden');
                document.getElementById('errorText').textContent = state.error;
            }
        }

        poll();
        pollTimer = setInterval(poll, 1000);
    </script>
</body>
</html>
