<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireSamlAuth
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if (! $request->expectsJson()) {
                $intendedUrl = '/'.ltrim($request->path(), '/');

                if ($request->getQueryString()) {
                    $intendedUrl .= '?'.$request->getQueryString();
                }

                if (mb_strlen($intendedUrl) > 2000) {
                    $intendedUrl = route('dashboard', absolute: false);
                }

                $request->session()->put('url.intended', $intendedUrl);
            }

            return redirect()->route('saml.login');
        }

        return $next($request);
    }
}
