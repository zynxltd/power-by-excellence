<?php

return array_replace_recursive(
    require __DIR__.'/../en/buyer_portal.php',
    [
        'portal_title' => 'Portale acquirente',
        'nav' => [
            'dashboard' => 'Dashboard',
            'leads' => 'I miei lead',
            'billing' => 'Fatturazione',
            'integrations' => 'Integrazioni',
        ],
        'common' => [
            'view' => 'Visualizza',
            'download_csv' => 'Scarica CSV',
            'export_csv' => 'Esporta CSV',
            'back_to_leads' => 'Torna ai lead',
            'view_all_leads' => 'Vedi tutti i lead',
            'view_leads' => 'Vedi lead',
        ],
        'dashboard' => [
            'title' => 'Dashboard acquirente',
            'subtitle' => 'Performance e inventario per :name',
            'credit' => 'Credito',
            'leads_today' => 'Lead oggi',
            'spend_7d' => 'Spesa (7 gg)',
            'conversion' => 'Conversione',
        ],
        'billing' => [
            'title' => 'Crediti e fatturazione',
            'description' => 'Visualizza saldo e movimenti. Le ricariche sono gestite dall\'amministratore della piattaforma.',
        ],
        'leads' => [
            'title' => 'I miei lead',
            'description' => 'Cerca, esporta e segnala i lead acquistati.',
        ],
    ]
);
