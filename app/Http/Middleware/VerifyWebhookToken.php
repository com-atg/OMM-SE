<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookToken
{
    /**
     * Validate the shared-secret token appended to the REDCap DET URL.
     * Configure the REDCap Data Entry Trigger URL as:
     *   https://your-server/omm_ace/notify?token=<WEBHOOK_SECRET>
     *
     * Check is bypassed only when running in local development with no
     * WEBHOOK_SECRET configured. All other environments (including testing
     * and production) must have a secret set or the request is rejected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = trim((string) config('redcap.webhook_secret'));

        if ($secret === '') {
            abort_unless(app()->environment('local'), 403, 'Webhook token verification is not configured.');

            return $next($request);
        }

        if (! hash_equals($secret, (string) $request->query('token', ''))) {
            abort(403, 'Invalid webhook token.');
        }

        return $next($request);
    }
}
