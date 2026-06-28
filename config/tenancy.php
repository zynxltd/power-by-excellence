<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base domain for tenant subdomains
    |--------------------------------------------------------------------------
    |
    | Tenant portals are served at {accounts.domain} or {accounts.slug}.{base_domain}.
    | Example: solar-us.powerbyexcellence.test
    |
    */

    'base_domain' => env('APP_BASE_DOMAIN', parse_url((string) env('APP_URL', 'http://powerbyexcellence.test'), PHP_URL_HOST) ?: 'powerbyexcellence.test'),

    /*
    |--------------------------------------------------------------------------
    | Central (marketing / super-admin) hosts
    |--------------------------------------------------------------------------
    |
    | Only super admins may sign in on these hosts. Partner admins, buyers, and
    | suppliers must use their tenant domain.
    |
    */

    'central_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('APP_CENTRAL_HOSTS', 'powerbyexcellence.test,localhost,127.0.0.1'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Portal custom domain CNAME target
    |--------------------------------------------------------------------------
    |
    | Verified custom portal domains must CNAME to this host. Defaults to each
    | tenant slug subdomain when unset (e.g. excellence-uk.powerbyexcellence.test).
    |
    */

    'portal_cname_target' => env('APP_PORTAL_CNAME_TARGET'),

];
