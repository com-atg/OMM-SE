<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RedcapDestinationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('role')->orderBy('email')->get();
        $trashedUsers = User::onlyTrashed()->orderBy('email')->get();

        $counts = [
            'service' => $users->where('role', Role::Service)->count(),
            'admin' => $users->where('role', Role::Admin)->count(),
            'faculty' => $users->where('role', Role::Faculty)->count(),
            'student' => $users->where('role', Role::Student)->count(),
            'deleted' => $trashedUsers->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'trashedUsers' => $trashedUsers,
            'roles' => Role::cases(),
            'counts' => $counts,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        User::create([
            'email' => strtolower(trim($validated['email'])),
            'name' => $validated['name'],
            'role' => Role::from($validated['role']),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Created {$validated['email']}.");
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

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot impersonate yourself.');
        abort_if($user->isService(), 403, 'Cannot impersonate a Service account.');
        abort_if($request->session()->has('impersonating_original_id'), 403, 'Already in an impersonation session.');

        $request->session()->put('impersonating_original_id', $request->user()->id);
        Auth::loginUsingId($user->id);

        return redirect()->route('dashboard');
    }

    public function stopImpersonation(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull('impersonating_original_id');

        if ($originalId !== null) {
            Auth::loginUsingId($originalId);
        }

        return redirect()->route('admin.users.index');
    }

    public function import(RedcapDestinationService $destination): RedirectResponse
    {
        Cache::forget('destination:all_students');
        $students = $destination->getAllStudentRecords();

        $created = 0;
        $skipped = 0;

        foreach ($students as $student) {
            $email = strtolower(trim((string) ($student['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            if (User::withTrashed()->where('email', $email)->exists()) {
                $skipped++;

                continue;
            }

            $firstName = trim((string) ($student['goes_by'] ?: ($student['first_name'] ?? '')));
            $lastName = trim((string) ($student['last_name'] ?? ''));
            $name = trim("{$firstName} {$lastName}") ?: $email;

            User::create([
                'email' => $email,
                'name' => $name,
                'role' => Role::Student,
                'redcap_record_id' => (string) ($student['record_id'] ?? '') ?: null,
            ]);

            $created++;
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', "REDCap import complete: {$created} created, {$skipped} already existed.");
    }
}
