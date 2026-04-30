<x-app-shell
    title="Dashboard"
    active="dashboard"
    eyebrow="Governance Overview"
    heading="OMM ACE Dashboard"
    subheading="OMM Student Evaluations — roster coverage, score health, and evaluation volume from the latest REDCap sync."
>
    <x-slot:head>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    </x-slot:head>

    <livewire:dashboard />
</x-app-shell>
