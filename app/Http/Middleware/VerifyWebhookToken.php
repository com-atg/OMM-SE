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
     * Check is bypassed when WEBHOOK_SECRET is not configured (local / CI).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('redcap.webhook_secret');

        if ($secret && ! hash_equals($secret, (string) $request->query('token', ''))) {
            abort(403, 'Invalid webhook token.');
        }

        return $next($request);
    }
}
