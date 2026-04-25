<x-app-shell
    title="Import Users via CSV"
    active="users"
    eyebrow="Bulk Import"
    heading="Import Users via CSV"
    subheading="Upload a CSV file to create multiple users at once. All rows are validated before any users are created."
    width="wide"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.users.index') }}" variant="ghost" icon="arrow-left">Return to Users</flux:button>
    </x-slot:headerActions>

    @livewire('admin.csv-user-import')
</x-app-shell>
