window.scholarDetailCharts ??= [];

window.copyScholarLink = function (button, url) {
    navigator.clipboard.writeText(url).then(() => {
        const original = button.innerHTML;
        button.innerHTML = original.replace('Copy link', 'Copied');
        setTimeout(() => {
            button.innerHTML = original;
        }, 1500);
    });
};

function renderScholarCharts(root = document) {
    const payloadNode = root.querySelector('[data-scholar-chart-payload]');

    if (! payloadNode || typeof Chart === 'undefined') {
        return;
    }

    const payload = JSON.parse(payloadNode.textContent || '{}');
    const palette = ['#2563eb', '#059669', '#d97706', '#7c3aed'];
    const gridColor = 'rgba(100, 116, 139, 0.16)';
    const tickColor = '#64748b';

    window.scholarDetailCharts.forEach((chart) => chart.destroy());
    window.scholarDetailCharts = [];

    const monthlyCanvas = root.querySelector('[data-scholar-chart="monthly"]');
    if (monthlyCanvas && payload.monthKeys?.length > 0) {
        window.scholarDetailCharts.push(new Chart(monthlyCanvas, {
            type: 'bar',
            data: {
                labels: payload.monthKeys,
                datasets: payload.categoryKeys.map((catKey, index) => ({
                    label: payload.categoryLabels[index],
                    data: payload.monthKeys.map((month) => (payload.mergedMonthly[month] && payload.mergedMonthly[month][catKey]) || 0),
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
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0, stepSize: 1 }, title: { display: true, text: '# Evals', color: '#94a3b8', font: { size: 11 } } },
                },
            },
        }));
    }

    root.querySelectorAll('[data-scholar-chart="semester"]').forEach((canvas) => {
        const sem = payload.semesters[Number(canvas.dataset.semesterIndex)];

        if (! sem) {
            return;
        }

        window.scholarDetailCharts.push(new Chart(canvas, {
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

                                return avg !== null ? `Avg: ${avg.toFixed(1)} / 100` : 'No score';
                            },
                        },
                    },
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0, stepSize: 1 } },
                },
            },
        }));
    });
}

export function bootScholarDetailCharts(Livewire) {
    document.addEventListener('DOMContentLoaded', () => renderScholarCharts(document));
    document.addEventListener('livewire:navigated', () => renderScholarCharts(document));

    Livewire.hook('morphed', ({ el }) => {
        if (el.querySelector('[data-scholar-chart-payload]')) {
            renderScholarCharts(el);
        }
    });
}
