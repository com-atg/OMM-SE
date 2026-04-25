<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class FacultyController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->canViewFacultyDetail(), 403);

        return view('faculty');
    }
}
