<?php

return [
    'portal_title' => 'Portal del comprador',

    'nav' => [
        'dashboard' => 'Panel',
        'leads' => 'Mis leads',
        'billing' => 'Facturación',
        'integrations' => 'Integraciones',
    ],

    'common' => [
        'view' => 'Ver',
        'download_csv' => 'Descargar CSV',
        'export_csv' => 'Exportar CSV',
        'back_to_leads' => 'Volver a leads',
        'view_all_leads' => 'Ver todos los leads',
        'view_leads' => 'Ver leads',
        'date' => 'Fecha',
        'type' => 'Tipo',
        'amount' => 'Importe',
        'balance' => 'Saldo',
        'description' => 'Descripción',
        'name' => 'Nombre',
        'cost' => 'Coste',
        'feedback' => 'Feedback',
        'lead' => 'Lead',
        'required' => 'Obligatorio',
        'optional' => 'Opcional',
        'no_activity' => 'Sin feedback ni devoluciones todavía.',
    ],

    'dashboard' => [
        'title' => 'Panel del comprador',
        'subtitle' => 'Rendimiento e inventario de :name',
        'credit' => 'Crédito',
        'leads_today' => 'Leads hoy',
        'spend_7d' => 'Gasto (7 d)',
        'conversion' => 'Conversión',
        'pending_returns_one' => ':count devolución pendiente de revisión.',
        'pending_returns_many' => ':count devoluciones pendientes de revisión.',
        'leads_chart' => 'Leads comprados — últimos 7 días',
        'spend_chart' => 'Gasto — últimos 7 días (:currency)',
        'recent_leads' => 'Leads comprados recientemente',
        'recent_activity' => 'Actividad reciente',
        'activity_return' => 'Devolución · :status',
        'activity_feedback' => 'Feedback · :status',
        'activity_converted' => ' · convertido',
        'chart_leads' => 'Leads',
        'chart_spend' => 'Gasto (:currency)',
    ],

    'billing' => [
        'title' => 'Créditos y facturación',
        'description' => 'Consulte saldo e historial. Las recargas las gestiona el administrador de la plataforma.',
        'balance' => 'Saldo',
        'spend_30d' => 'Gasto (30 d)',
        'prepay' => 'Prepago',
        'prepay_notice_title' => 'Facturación prepago',
        'prepay_notice' => 'Los leads debitan su saldo al venderse. Contacte a su gestor para recargar. Los compradores no pueden autofinanciarse en el portal.',
        'transactions' => 'Historial de transacciones',
    ],

    'ledger' => [
        'credit' => 'Crédito (recarga)',
        'debit' => 'Compra de lead',
        'goodwill' => 'Crédito comercial',
        'correction' => 'Corrección de saldo',
        'refund' => 'Reembolso',
        'manual_debit' => 'Débito manual',
        'chargeback' => 'Contracargo',
        'adjustment' => 'Ajuste general',
    ],

    'account' => [
        'title' => 'Cuenta y límites',
        'intro' => 'Límites de entrega, recargas y aprobaciones de devolución las gestiona el administrador de la plataforma.',
        'buyer_status' => 'Estado del comprador',
        'billing_mode' => 'Facturación',
        'prepay_required' => 'Prepago — saldo requerido',
        'postpaid' => 'Postpago',
        'daily_cap' => 'Límite diario de leads',
        'daily_spend_cap' => 'Límite diario de gasto',
        'active_deliveries' => 'Entregas activas',
        'low_credit_alert' => 'Alerta de crédito bajo',
        'low_credit_warning' => 'Crédito por debajo del umbral. Contacte a su gestor — los compradores no pueden recargar en el portal.',
    ],

    'leads' => [
        'title' => 'Mis leads',
        'description' => 'Buscar, exportar e informar sobre leads comprados.',
    ],

    'integrations' => [
        'title' => 'Integraciones',
        'my_leads' => 'Mis leads',
    ],
];
