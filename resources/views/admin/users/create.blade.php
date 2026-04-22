<x-app-shell
    title="Add User"
    active="users"
    eyebrow="System Access"
    heading="Add User"
    subheading="Create a managed account for Okta SAML access and REDCap role mapping."
    width="narrow"
>
    <x-slot:headerActions>
        <flux:button href="{{ route('admin.users.index') }}" variant="ghost" icon="arrow-left">Back to users</flux:button>
    </x-slot:headerActions>

    <section class="rounded-lg border border-white/80 bg-white/86 p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('email') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                <p class="mt-1 text-xs text-slate-500">Must match the user's Okta or institutional email.</p>
                @error('email')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Display Name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    required
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('name') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                >
                <p class="mt-1 text-xs text-slate-500">Okta may update this name on first login.</p>
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
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>
                            {{ $role->label() }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs leading-5 text-slate-500">Service has full access, Admin can view all scholars, and Student sees their own evaluations.</p>
                @error('role')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button href="{{ route('admin.users.index') }}" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create user</flux:button>
            </div>
        </form>
    </section>
</x-app-shell>
