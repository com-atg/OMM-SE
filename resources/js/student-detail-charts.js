window.studentDetailCharts ??= [];

window.copyStudentLink = function (button, url) {
    navigator.clipboard.writeText(url).then(() => {
        const original = button.innerHTML;
        button.innerHTML = original.replace('Copy link', 'Copied');
        setTimeout(() => {
            button.innerHTML = original;
        }, 1500);
    });
};

function renderStudentCharts(root = document) {
    const payloadNode = root.querySelector('[data-student-chart-payload]');

    if (! payloadNode || typeof Chart === 'undefined') {
        return;
    }

    const payload = JSON.parse(payloadNode.textContent || '{}');
    const palette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#e11d48'];
    const gridColor = 'rgba(100, 116, 139, 0.16)';
    const tickColor = '#64748b';

    window.studentDetailCharts.forEach((chart) => chart.destroy());
    window.studentDetailCharts = [];

    const monthlyCanvas = root.querySelector('[data-student-chart="monthly"]');
    if (monthlyCanvas && payload.monthKeys?.length > 0) {
        window.studentDetailCharts.push(new Chart(monthlyCanvas, {
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

    root.querySelectorAll('[data-student-chart="semester"]').forEach((canvas) => {
        const sem = payload.semesters[Number(canvas.dataset.semesterIndex)];

        if (! sem) {
            return;
        }

        window.studentDetailCharts.push(new Chart(canvas, {
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

export function bootStudentDetailCharts(Livewire) {
    document.addEventListener('DOMContentLoaded', () => renderStudentCharts(document));
    document.addEventListener('livewire:navigated', () => renderStudentCharts(document));

    Livewire.hook('morphed', ({ el }) => {
        if (el.querySelector('[data-student-chart-payload]')) {
            renderStudentCharts(el);
        }
    });
}
