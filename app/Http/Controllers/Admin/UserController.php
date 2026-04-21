<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('role')->orderBy('email')->get();
        $trashedUsers = User::onlyTrashed()->orderBy('email')->get();

        return view('admin.users.index', [
            'users' => $users,
            'trashedUsers' => $trashedUsers,
            'roles' => Role::cases(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
            'redcap_record_id' => ['nullable', 'string', 'max:64'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'role' => Role::from($validated['role']),
            'redcap_record_id' => $validated['redcap_record_id'] ?: null,
        ])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Updated {$user->email}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        $email = $user->email;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Deleted {$email}.");
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);

        $user->restore();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Restored {$user->email}.");
    }
}
