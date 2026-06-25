<?php

return [
    'driver' => env('VALIDATION_DRIVER', 'demo'),

    'demo' => [
        'reject_domains' => ['invalid.demo', 'bounce.demo', 'trap.demo'],
        'hlr_unreachable_prefixes' => ['07000', '08000'],
    ],

    'quarantine_on_email_fail' => true,
    'quarantine_on_hlr_fail' => true,
    'quarantine_hours' => 48,
    // release = retry distribution; reject = mark rejected (validation holds always reject)
    'quarantine_expire_action' => env('QUARANTINE_EXPIRE_ACTION', 'release'),
];
