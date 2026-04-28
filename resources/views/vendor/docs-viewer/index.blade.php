@php $namePrefix = config('docs-viewer.route_name_prefix', 'admin.docs'); @endphp

<x-app-shell
    title="Documentation"
    active="docs"
    eyebrow="Internal Reference"
    heading="Documentation"
    subheading="Architecture decisions, runbooks, and operational guides for OMM ACE."
    width="wide"
>
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($docs as $doc)
            <a href="{{ route($namePrefix.'.show', $doc['slug']) }}"
               class="group flex flex-col gap-3 rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur transition duration-150 hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-[0_22px_60px_rgba(15,23,42,0.12)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/70">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-slate-950 text-white shadow-sm transition-colors group-hover:bg-amber-500">
                        <flux:icon.document-text variant="mini" />
                    </span>
                    <h2 class="text-sm font-semibold leading-snug text-slate-950 transition-colors group-hover:text-amber-900">
                        {{ $doc['title'] }}
                    </h2>
                </div>

                @if ($doc['description'])
                    <p class="text-xs leading-relaxed text-slate-600">
                        {{ $doc['description'] }}
                    </p>
                @endif

                <div class="mt-auto flex items-center gap-1 text-[11px] font-bold uppercase tracking-[0.3em] text-slate-400 transition-colors group-hover:text-amber-700">
                    Read
                    <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </a>
        @endforeach
    </section>
</x-app-shell>
