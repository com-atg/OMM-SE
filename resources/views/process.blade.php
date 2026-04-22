<x-app-shell
    title="Processing Project"
    :active="$active ?? 'dashboard'"
    heading="Processing Source Project"
    :subheading="'Project '.$pid.' - aggregating evaluations and pushing to destination.'"
    width="wide"
>
    <x-slot:head>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    </x-slot:head>

    <section class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="spinner" class="size-5 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600"></div>
                <div id="statusLabel" class="text-sm font-medium text-slate-700">Starting...</div>
            </div>
            <div class="text-xs text-slate-500" id="progressText">-</div>
        </div>

        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div id="progressBar" class="h-full bg-blue-600 transition-all duration-300" style="width: 0%"></div>
        </div>

        <div class="mt-6 h-64"><canvas id="progressChart"></canvas></div>
    </section>

    <section id="summary" class="hidden rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <div id="summaryBanner" class="mb-5 rounded-lg px-4 py-3 text-sm font-medium"></div>

        <h2 class="mb-3 text-sm font-semibold text-slate-700">Run summary</h2>
        <dl id="summaryStats" class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"></dl>

        <div id="skipSection" class="hidden">
            <h3 class="mb-2 text-xs uppercase tracking-wide text-slate-500">Skipped groups</h3>
            <ul id="skipList" class="space-y-1 text-sm text-slate-700"></ul>
        </div>

        <div id="errorSection" class="mt-4 hidden">
            <h3 class="mb-2 text-xs uppercase tracking-wide text-rose-500">Error</h3>
            <pre id="errorText" class="rounded-lg bg-rose-50 p-3 text-xs whitespace-pre-wrap text-rose-800"></pre>
        </div>
    </section>

    <x-slot:scripts>
        <script>
            const jobId = @json($jobId);
            const projectLabel = @json($pid);
            const statusUrl = "{{ route('process.status', ['jobId' => '__ID__']) }}".replace('__ID__', jobId);

            const statusLabels = {
                pending: 'Queued - preparing...',
                running: 'Processing...',
                complete: 'Completed',
                failed: 'Failed',
                unknown: 'Unknown job',
            };

            const chart = new Chart(document.getElementById('progressChart'), {
                type: 'bar',
                data: {
                    labels: ['Processed', 'Updated', 'Unchanged', 'Skipped', 'Failed'],
                    datasets: [{
                        label: 'Count',
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: ['#2563eb', '#16a34a', '#64748b', '#f59e0b', '#dc2626'],
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
                        : (state.total_records ? `${state.total_records} records queued` : 'Fetching records...');

                const skipped = Object.values(state.skip_reasons || {}).reduce((a, b) => a + Number(b), 0);
                chart.data.datasets[0].data = [
                    processed,
                    state.updated || 0,
                    state.unchanged || 0,
                    skipped,
                    state.failed || 0,
                ];
                chart.update('none');
            }

            function onDone(state) {
                document.getElementById('spinner').classList.add('hidden');

                const summary = document.getElementById('summary');
                summary.classList.remove('hidden');

                const unchanged = state.unchanged || 0;
                const banner = document.getElementById('summaryBanner');
                if (state.status === 'complete') {
                    banner.className = 'mb-5 rounded-lg px-4 py-3 text-sm font-medium bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200';
                    banner.textContent = `Completed - processed ${state.processed_groups || 0} scholar-semester group(s), ${unchanged} unchanged.`;
                } else {
                    banner.className = 'mb-5 rounded-lg px-4 py-3 text-sm font-medium bg-rose-50 text-rose-800 ring-1 ring-rose-200';
                    banner.textContent = 'Failed - see error details below.';
                }

                const skipped = Object.values(state.skip_reasons || {}).reduce((a, b) => a + Number(b), 0);
                const duration = (state.started_at && state.finished_at)
                    ? Math.round((new Date(state.finished_at) - new Date(state.started_at)) / 1000) + 's'
                    : '-';

                const stats = [
                    ['Source records', state.total_records || 0],
                    ['Groups', state.total_groups || 0],
                    ['Updated', state.updated || 0],
                    ['Unchanged', unchanged],
                    ['Skipped', skipped],
                    ['Failed', state.failed || 0],
                    ['Duration', duration],
                    ['Project', projectLabel],
                    ['Job ID', jobId.split('-')[0] + '...'],
                ];
                const dl = document.getElementById('summaryStats');
                dl.innerHTML = stats.map(([k, v]) => `
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">${k}</dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-900">${v}</dd>
                    </div>
                `).join('');

                const reasons = state.skip_reasons || {};
                const reasonKeys = Object.keys(reasons);
                if (reasonKeys.length > 0) {
                    document.getElementById('skipSection').classList.remove('hidden');
                    document.getElementById('skipList').innerHTML = reasonKeys.map(
                        r => `<li><span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">${r}</span> <span class="text-slate-600">x ${reasons[r]}</span></li>`
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
    </x-slot:scripts>
</x-app-shell>
