<?php

return [
    /*
     * Leave CANONICAL_URL empty in local and staging environments. In
     * production it should be the single public origin used to generate links.
     */
    'url' => env('CANONICAL_URL'),

    /*
     * These are redirect-only transition hosts. They do not represent extra
     * application origins. An unexpected Host is rejected and is never
     * reflected into a Location response.
     */
    'legacy_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CANONICAL_LEGACY_HOSTS', 'casherp.com'))
    ))),
];
