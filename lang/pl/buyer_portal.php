<?php

return array_replace_recursive(
    require __DIR__.'/../en/buyer_portal.php',
    [
        'portal_title' => 'Portal kupującego',
        'nav' => [
            'dashboard' => 'Panel',
            'leads' => 'Moje leady',
            'billing' => 'Rozliczenia',
            'integrations' => 'Integracje',
        ],
        'common' => [
            'view' => 'Zobacz',
            'download_csv' => 'Pobierz CSV',
            'export_csv' => 'Eksportuj CSV',
            'back_to_leads' => 'Wróć do leadów',
            'view_all_leads' => 'Wszystkie leady',
            'view_leads' => 'Zobacz leady',
        ],
        'dashboard' => [
            'title' => 'Panel kupującego',
            'subtitle' => 'Wyniki i zapasy dla :name',
            'credit' => 'Saldo',
            'leads_today' => 'Leady dziś',
            'spend_7d' => 'Wydatki (7 dni)',
            'conversion' => 'Konwersja',
        ],
        'billing' => [
            'title' => 'Saldo i rozliczenia',
            'description' => 'Przeglądaj saldo i historię. Doładowania organizuje administrator platformy.',
        ],
        'leads' => [
            'title' => 'Moje leady',
            'description' => 'Wyszukuj, eksportuj i zgłaszaj zakupione leady.',
        ],
    ]
);
