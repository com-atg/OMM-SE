@php
    $userSubheading = 'Last login: '.($user->last_login_at?->diffForHumans() ?? 'never').'. Created '.$user->created_at?->toFormattedDateString().'.';
@endphp

<x-app-shell
    :title="'Edit '.$user->email"
    active="users"
    eyebrow="System Access"
    heading="Edit User"
    :subheading="$userSubheading"
    width="narrow"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.users.index') }}" variant="ghost" icon="arrow-left">Back to users</flux:button>
    </x-slot:headerActions>

    <section class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <form id="update-user-form" method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Email</label>
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                    <span class="min-w-0 truncate">{{ $user->email }}</span>
                    <span class="ml-auto inline-flex rounded-full px-2.5 py-1 text-xs font-bold
                        @if ($user->isService()) bg-violet-100 text-violet-800
                        @elseif ($user->isAdmin()) bg-sky-100 text-sky-800
                        @elseif ($user->isFaculty()) bg-teal-100 text-teal-800
                        @else bg-emerald-100 text-emerald-800
                        @endif">
                        {{ $user->role?->label() }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Email is set by Okta SAML and cannot be changed here.</p>
            </div>

            <div>
                <label for="name" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Display Name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $user->name) }}"
                    required
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('name') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                @error('name')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="role" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Role</label>
                <select
                    name="role"
                    id="role"
                    required
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('role') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>
                            {{ $role->label() }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="redcap_record_id" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">REDCap Record ID</label>
                <input
                    type="text"
                    name="redcap_record_id"
                    id="redcap_record_id"
                    value="{{ old('redcap_record_id', $user->redcap_record_id) }}"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('redcap_record_id') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                <p class="mt-1 text-xs text-slate-500">Clear this value to force a re-lookup on student login.</p>
                @error('redcap_record_id')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </form>

        <div class="mt-7 flex items-center justify-between gap-3 border-t border-slate-200 pt-5">
            @if ($user->id !== auth()->id())
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user? This can be undone.')">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger">Delete user</flux:button>
                </form>
            @else
                <span></span>
            @endif

            <div class="flex gap-3">
                <flux:button href="{{ route('admin.users.index') }}" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" form="update-user-form" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </section>
</x-app-shell>
