@php $namePrefix = config('docs-viewer.route_name_prefix', 'admin.docs'); @endphp

<x-app-shell
    :title="$title"
    active="docs"
    width="wide"
>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        <aside class="w-full shrink-0 lg:sticky lg:top-20 lg:w-56 xl:w-64" aria-label="Documentation pages">
            <div class="rounded-lg border border-white/80 bg-white/86 p-3 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <p class="px-2 pb-2 pt-1 text-[10px] font-bold uppercase tracking-[0.3em] text-slate-500">All Docs</p>

                <nav class="space-y-0.5">
                    @foreach ($docs as $doc)
                        @php $isActive = $doc['slug'] === $slug; @endphp
                        <a href="{{ route($namePrefix.'.show', $doc['slug']) }}"
                           @if ($isActive) aria-current="page" @endif
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/70
                                  {{ $isActive
                                      ? 'bg-amber-100 text-amber-950 shadow-sm ring-1 ring-amber-200'
                                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                            <flux:icon.document-text variant="mini" class="size-3.5 shrink-0 {{ $isActive ? 'text-amber-700' : 'text-slate-400' }}" />
                            <span class="leading-snug">{{ $doc['title'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-3 border-t border-slate-200/80 pt-3">
                    <a href="{{ route($namePrefix.'.index') }}"
                       class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-medium text-slate-500 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/70">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        All Documentation
                    </a>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1" id="doc-content">
            <div class="rounded-lg border border-white/80 bg-white/90 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="p-6 lg:p-8 xl:p-10">
                    <div class="docs-prose">
                        {{-- Source: local Markdown files under base_path('Docs') (see config/docs-viewer.php).
                             Not user input — safe to render unescaped. --}}
                        {!! $html !!}
                    </div>
                </div>
            </div>
        </main>

    </div>

    <x-slot:scripts>
        <script type="module">
            import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

            mermaid.initialize({
                startOnLoad: false,
                theme: 'neutral',
                themeVariables: {
                    primaryColor: '#fef3c7',
                    primaryTextColor: '#0f172a',
                    primaryBorderColor: '#fcd34d',
                    lineColor: '#64748b',
                    background: '#ffffff',
                },
                flowchart: { curve: 'basis', htmlLabels: true },
                securityLevel: 'strict',
            });

            await mermaid.run({ querySelector: 'pre.mermaid' });
        </script>
    </x-slot:scripts>
</x-app-shell>
