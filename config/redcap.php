<?php

return [
    'url' => env('REDCAP_URL', ''),

    /*
    | Default project token (destination — OMMScholarEvalList).
    | The destination project is the source of truth for the scholar roster
    | (record_id stable across academic years, with graduation_year per scholar).
    */
    'token' => env('REDCAP_TOKEN', ''),

    /*
    | Shared secret appended as ?token= to the REDCap Data Entry Trigger URL.
    | Leave empty to disable verification (local / CI environments).
    */
    'webhook_secret' => env('WEBHOOK_SECRET', ''),
];
