<?php

return [
    'url' => env('REDCAP_URL', ''),

    /*
    | Default project token (destination — OMMACEList, PID 2115).
    | The destination project is the source of truth for the scholar roster
    | (record_id stable across cohorts, with batch + is_active per scholar).
    */
    'token' => env('REDCAP_TOKEN', ''),

    /*
    | Shared secret appended as ?token= to the REDCap Data Entry Trigger URL.
    | Leave empty to disable verification (local / CI environments).
    */
    'webhook_secret' => env('WEBHOOK_SECRET', ''),
];
