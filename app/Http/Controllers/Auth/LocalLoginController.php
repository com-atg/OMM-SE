<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LocalLoginController extends Controller
{
    public function index(): View
    {
        abort_unless(app()->environment('local'), 404);

        $users = User::orderBy('role')->orderBy('name')->get();

        return view('auth.local-login', compact('users'));
    }

    public function login(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'in:service,admin,student,faculty'],
        ]);

        if (! empty($validated['user_id'])) {
            $user = User::findOrFail($validated['user_id']);
        } else {
            $role = Role::from($validated['role'] ?? 'service');
            $email = "local-{$role->value}@local.test";
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => "Local {$role->label()} User", 'role' => $role, 'last_login_at' => now()],
            );
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
