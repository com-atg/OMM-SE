<x-app-shell
    title="Student Detail"
    active="students"
    eyebrow="Student Detail"
    heading="Individual Student Evaluation"
    subheading="Review semester scores, evaluation cadence, faculty comments, and supporting detail for a selected student."
    width="wide"
>
    <x-slot:head>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    </x-slot:head>

    <livewire:student-detail
        :initial-selected-id="$selected['record_id'] ?? ''"
        :lock-selection="$lock_selection ?? false"
        :shareable-url="$shareable_url ?? null"
    />
</x-app-shell>
