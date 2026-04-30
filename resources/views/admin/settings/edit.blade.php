@php
    $settingsSubheading = 'Update '.$projectMapping->displayName().' / PID '.$projectMapping->redcap_pid.'.';
@endphp

<x-app-shell
    title="Edit Project Mapping"
    active="settings"
    eyebrow="Service Settings"
    heading="Edit Project Mapping"
    :subheading="$settingsSubheading"
    width="narrow"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.settings.index') }}" variant="ghost" icon="arrow-left">Back to settings</flux:button>
    </x-slot:headerActions>

    <section class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <form id="update-project-mapping-form" method="POST" action="{{ route('admin.settings.project-mappings.update', $projectMapping) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div>
                <label for="redcap_pid" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">REDCap PID</label>
                <input
                    type="text"
                    name="redcap_pid"
                    id="redcap_pid"
                    value="{{ old('redcap_pid', $projectMapping->redcap_pid) }}"
                    required
                    inputmode="numeric"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('redcap_pid') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                @error('redcap_pid')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="redcap_token" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">REDCap Token</label>
                <input
                    type="password"
                    name="redcap_token"
                    id="redcap_token"
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('redcap_token') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                <p class="mt-1 text-xs text-slate-500">Leave blank to keep the existing token ending in {{ substr($projectMapping->redcap_token, -4) }}.</p>
                @error('redcap_token')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </form>

        <div class="mt-7 flex items-center justify-between gap-3 border-t border-slate-200 pt-5">
            <form method="POST" action="{{ route('admin.settings.project-mappings.destroy', $projectMapping) }}" onsubmit="return confirm('Delete this project mapping? This can be restored.')">
                @csrf
                @method('DELETE')
                <flux:button type="submit" variant="danger">Delete mapping</flux:button>
            </form>

            <div class="flex gap-3">
                <flux:button href="{{ route('admin.settings.index') }}" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" form="update-project-mapping-form" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </section>
</x-app-shell>
