<x-app-shell
    title="Configure Source Project"
    active="settings"
    eyebrow="Service Settings"
    heading="Configure the Source Project"
    subheading="Set the active REDCap source project for evaluation webhooks. Only one source project is active at a time."
    width="wide"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.settings.index') }}" variant="ghost" icon="arrow-left">Back to settings</flux:button>
    </x-slot:headerActions>

    <section class="space-y-5">
        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">1</span>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">REDCap project</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Confirm the destination project schema</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Verify the OMM ACE List destination project has <span class="font-mono">cohort_start_term</span> (Spring/Fall) and
                        <span class="font-mono">cohort_start_year</span> on every scholar record, plus the
                        <span class="font-mono">sem1_*</span>&hellip;<span class="font-mono">sem4_*</span> field families.
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">2</span>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">API token</div>
                    <h2 class="mt-2 text-xl font-bold text-slate-950">Get the source project API token</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">In the source REDCap project, request and generate an API token. You'll paste it below along with the project's PID.</p>
                </div>
            </div>
        </div>

        <livewire:admin.academic-year-wizard />
    </section>
</x-app-shell>
