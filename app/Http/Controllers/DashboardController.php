<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && $user->isStudent()) {
            return redirect()->route('student');
        }

        abort_unless($user && $user->canViewDashboard(), 403);

        return view('dashboard');
    }
}
