@extends(config('docs-viewer.layout', 'layouts.app'))

@section(config('docs-viewer.layout_section', 'content'))
@php $namePrefix = config('docs-viewer.route_name_prefix', 'admin.docs'); @endphp

<div class="min-h-screen bg-gray-50 px-4 py-8 dark:bg-gray-900 lg:px-8 lg:py-12">
    <div class="mx-auto max-w-7xl">

        <div class="flex flex-col gap-8 lg:flex-row lg:items-start">

            {{-- Sidebar --}}
            <aside class="w-full shrink-0 lg:sticky lg:top-20 lg:w-56 xl:w-64" aria-label="Documentation pages">
                <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="px-2 pb-2 pt-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        All Docs
                    </p>
                    <nav class="space-y-0.5">
                        @foreach($docs as $doc)
                            @php $isActive = $doc['slug'] === $slug; @endphp
                            <a href="{{ route($namePrefix . '.show', $doc['slug']) }}"
                               @if($isActive) aria-current="page" @endif
                               class="flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1
                                      {{ $isActive
                                          ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white'
                                          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white' }}">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                </svg>
                                <span class="leading-snug">{{ $doc['title'] }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <div class="mt-3 border-t border-gray-200 pt-3 dark:border-white/10">
                        <a href="{{ route($namePrefix . '.index') }}"
                           class="flex items-center gap-1.5 rounded-lg px-2 text-[11px] font-medium text-gray-400 transition-colors hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 dark:hover:text-gray-200">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                            </svg>
                            All Documentation
                        </a>
                    </div>
                </div>
            </aside>

            {{-- Main content --}}
            <main class="min-w-0 flex-1" id="doc-content">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <div class="p-6 lg:p-8 xl:p-10">
                        <div class="docs-prose">
                            {!! $html !!}
                        </div>
                    </div>
                </div>
            </main>

        </div>

    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script>
(function () {
    var isDark = document.documentElement.classList.contains('dark');
    mermaid.initialize({
        startOnLoad: true,
        theme: isDark ? 'dark' : 'neutral',
        flowchart: { curve: 'basis', htmlLabels: true },
        securityLevel: 'strict',
    });
}());
</script>
@endpush

@endsection
