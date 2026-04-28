@extends(config('docs-viewer.layout', 'layouts.app'))

@section(config('docs-viewer.layout_section', 'content'))
@php $namePrefix = config('docs-viewer.route_name_prefix', 'admin.docs'); @endphp

<div class="min-h-screen bg-gray-50 px-4 py-8 dark:bg-gray-900 lg:px-8 lg:py-12">
    <div class="mx-auto max-w-7xl">

        {{-- Header --}}
        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Internal Reference</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Documentation</h1>
        </div>

        {{-- Doc cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($docs as $doc)
                <a href="{{ route($namePrefix . '.show', $doc['slug']) }}"
                   class="group flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:border-white/10 dark:bg-gray-800 dark:hover:bg-gray-750">

                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                        </span>
                        <h2 class="text-sm font-semibold leading-snug text-gray-900 dark:text-white">
                            {{ $doc['title'] }}
                        </h2>
                    </div>

                    @if($doc['description'])
                        <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            {{ $doc['description'] }}
                        </p>
                    @endif

                    <div class="mt-auto flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Read
                        <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>

    </div>
</div>
@endsection
