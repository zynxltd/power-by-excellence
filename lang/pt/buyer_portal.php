<?php

return array_replace_recursive(
    require __DIR__.'/../en/buyer_portal.php',
    [
        'portal_title' => 'Portal do comprador',
        'nav' => [
            'dashboard' => 'Painel',
            'leads' => 'Os meus leads',
            'billing' => 'Faturação',
            'integrations' => 'Integrações',
        ],
        'common' => [
            'view' => 'Ver',
            'download_csv' => 'Transferir CSV',
            'export_csv' => 'Exportar CSV',
            'back_to_leads' => 'Voltar aos leads',
            'view_all_leads' => 'Ver todos os leads',
            'view_leads' => 'Ver leads',
        ],
        'dashboard' => [
            'title' => 'Painel do comprador',
            'subtitle' => 'Desempenho e inventário de :name',
            'credit' => 'Crédito',
            'leads_today' => 'Leads hoje',
            'spend_7d' => 'Gasto (7 d)',
            'conversion' => 'Conversão',
        ],
        'billing' => [
            'title' => 'Créditos e faturação',
            'description' => 'Consulte saldo e movimentos. Recargas são geridas pelo administrador da plataforma.',
        ],
        'leads' => [
            'title' => 'Os meus leads',
            'description' => 'Pesquisar, exportar e reportar leads comprados.',
        ],
    ]
);
