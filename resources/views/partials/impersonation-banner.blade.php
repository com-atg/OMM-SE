@if (session('impersonating_original_id'))
<div class="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 rounded-2xl bg-amber-500 px-5 py-3 text-sm font-medium text-white shadow-xl ring-1 ring-amber-600">
    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
    </svg>
    <span>Viewing as <strong>{{ auth()->user()?->email }}</strong></span>
    <form method="POST" action="{{ route('users.impersonate.stop') }}" class="ml-1">
        @csrf
        <button type="submit" class="rounded-lg bg-white/20 px-3 py-1 text-xs font-semibold hover:bg-white/35 transition-colors">
            Stop impersonating
        </button>
    </form>
</div>
@endif
