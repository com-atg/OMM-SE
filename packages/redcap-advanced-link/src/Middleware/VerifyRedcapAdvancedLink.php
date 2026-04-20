<?php

namespace Omm\RedcapAdvancedLink\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Omm\RedcapAdvancedLink\RedcapAdvancedLinkService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyRedcapAdvancedLink
{
    public function __construct(private RedcapAdvancedLinkService $advancedLink) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('redcap-advanced-link.enabled')) {
            return $next($request);
        }

        $authorizedRoles = config('redcap-advanced-link.authorized_roles', []);
        $sessionKey = (string) config('redcap-advanced-link.session_key', 'redcap.advanced_link.user');
        $hasSession = $request->hasSession();
        $sessionUser = $hasSession ? $request->session()->get($sessionKey) : null;

        if (is_array($sessionUser)) {
            $role = (string) ($sessionUser['unique_role_name'] ?? '');

            if ($this->advancedLink->isRoleAuthorized($role, $authorizedRoles)) {
                $request->attributes->set('redcap_advanced_link_user', $sessionUser);

                return $next($request);
            }

            $request->session()->forget($sessionKey);
        }

        $authKey = trim((string) $request->input('authkey', ''));

        if ($authKey === '') {
            abort(403, 'You are not authorized to access this page.');
        }

        try {
            $user = $this->advancedLink->authorize($authKey, $authorizedRoles);
        } catch (Throwable $e) {
            Log::error('REDCap Advanced Link authorization failed.', [
                'path' => $request->path(),
                'message' => $e->getMessage(),
            ]);

            abort(503, 'Unable to verify REDCap authorization right now.');
        }

        if ($user === null) {
            Log::warning('REDCap Advanced Link denied request.', [
                'path' => $request->path(),
            ]);

            abort(403, 'You are not authorized to access this page.');
        }

        if ($hasSession) {
            $request->session()->put($sessionKey, $user);
        }

        $request->attributes->set('redcap_advanced_link_user', $user);

        return $next($request);
    }
}
