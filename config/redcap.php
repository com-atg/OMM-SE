<?php

return [
    'url' => env('REDCAP_URL', ''),

    /*
    | Default project token (destination — OMMScholarEvalList).
    */
    'token' => env('REDCAP_TOKEN', ''),

    /*
    | Source evaluation project token.
    | This project is recreated each academic year with a new PID and token.
    | Update REDCAP_SOURCE_TOKEN in .env at the start of each academic year.
    */
    'source_token' => env('REDCAP_SOURCE_TOKEN', ''),

    /*
    | Shared secret appended as ?token= to the REDCap Data Entry Trigger URL.
    | Leave empty to disable verification (local / CI environments).
    */
    'webhook_secret' => env('WEBHOOK_SECRET', ''),
];
