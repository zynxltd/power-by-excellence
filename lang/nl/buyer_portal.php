<?php

return array_replace_recursive(
    require __DIR__.'/../en/buyer_portal.php',
    [
        'portal_title' => 'Koperportaal',
        'nav' => [
            'dashboard' => 'Dashboard',
            'leads' => 'Mijn leads',
            'billing' => 'Facturatie',
            'integrations' => 'Integraties',
        ],
        'common' => [
            'view' => 'Bekijken',
            'download_csv' => 'CSV downloaden',
            'export_csv' => 'CSV exporteren',
            'back_to_leads' => 'Terug naar leads',
            'view_all_leads' => 'Alle leads bekijken',
            'view_leads' => 'Leads bekijken',
        ],
        'dashboard' => [
            'title' => 'Koper-dashboard',
            'subtitle' => 'Prestaties en voorraad voor :name',
            'credit' => 'Tegoed',
            'leads_today' => 'Leads vandaag',
            'spend_7d' => 'Uitgaven (7 d)',
            'conversion' => 'Conversie',
        ],
        'billing' => [
            'title' => 'Tegoed en facturatie',
            'description' => 'Bekijk saldo en transacties. Opwaarderingen regelt uw platformbeheerder.',
        ],
        'leads' => [
            'title' => 'Mijn leads',
            'description' => 'Zoek, exporteer en rapporteer gekochte leads.',
        ],
    ]
);
