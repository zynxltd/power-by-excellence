<?php

return [
    'portal_title' => 'Käuferportal',

    'nav' => [
        'dashboard' => 'Dashboard',
        'leads' => 'Meine Leads',
        'billing' => 'Abrechnung',
        'integrations' => 'Integrationen',
    ],

    'common' => [
        'view' => 'Ansehen',
        'download_csv' => 'CSV herunterladen',
        'export_csv' => 'CSV exportieren',
        'back_to_leads' => 'Zurück zu Leads',
        'view_all_leads' => 'Alle Leads anzeigen',
        'view_leads' => 'Leads anzeigen',
        'date' => 'Datum',
        'type' => 'Typ',
        'amount' => 'Betrag',
        'balance' => 'Saldo',
        'description' => 'Beschreibung',
        'name' => 'Name',
        'cost' => 'Kosten',
        'feedback' => 'Feedback',
        'lead' => 'Lead',
        'required' => 'Erforderlich',
        'optional' => 'Optional',
        'no_activity' => 'Noch kein Feedback oder keine Retouren.',
    ],

    'dashboard' => [
        'title' => 'Käufer-Dashboard',
        'subtitle' => 'Leistung & Bestand für :name',
        'credit' => 'Guthaben',
        'leads_today' => 'Leads heute',
        'spend_7d' => 'Ausgaben (7 T.)',
        'conversion' => 'Conversion',
        'pending_returns_one' => ':count Retoure wartet auf Prüfung.',
        'pending_returns_many' => ':count Retouren warten auf Prüfung.',
        'leads_chart' => 'Gekaufte Leads — letzte 7 Tage',
        'spend_chart' => 'Ausgaben — letzte 7 Tage (:currency)',
        'recent_leads' => 'Zuletzt gekaufte Leads',
        'recent_activity' => 'Letzte Aktivität',
        'activity_return' => 'Retoure · :status',
        'activity_feedback' => 'Feedback · :status',
        'activity_converted' => ' · konvertiert',
        'chart_leads' => 'Leads',
        'chart_spend' => 'Ausgaben (:currency)',
    ],

    'billing' => [
        'title' => 'Guthaben & Abrechnung',
        'description' => 'Saldo und Kontobewegungen einsehen. Aufladungen werden von Ihrem Plattform-Administrator veranlasst.',
        'balance' => 'Saldo',
        'spend_30d' => 'Ausgaben (30 T.)',
        'prepay' => 'Vorauszahlung',
        'prepay_notice_title' => 'Vorauszahlung',
        'prepay_notice' => 'Leads belasten Ihr Guthaben beim Verkauf. Wenden Sie sich an Ihren Ansprechpartner zum Aufladen. Käufer können im Portal nicht selbst einzahlen.',
        'transactions' => 'Transaktionsverlauf',
    ],

    'ledger' => [
        'credit' => 'Gutschrift (Aufladung)',
        'debit' => 'Lead-Kauf',
        'goodwill' => 'Kulanzgutschrift',
        'correction' => 'Saldo-Korrektur',
        'refund' => 'Erstattung',
        'manual_debit' => 'Manuelle Belastung',
        'chargeback' => 'Rückbuchung',
        'adjustment' => 'Allgemeine Anpassung',
    ],

    'account' => [
        'title' => 'Konto & Limits',
        'intro' => 'Lieferlimits, Guthaben-Aufladungen und Retourenfreigaben werden von Ihrem Plattform-Administrator verwaltet.',
        'buyer_status' => 'Käuferstatus',
        'billing_mode' => 'Abrechnung',
        'prepay_required' => 'Vorauszahlung — Guthaben erforderlich',
        'postpaid' => 'Nachzahlung',
        'daily_cap' => 'Tägliches Lead-Limit',
        'daily_spend_cap' => 'Tägliches Ausgabenlimit',
        'active_deliveries' => 'Aktive Lieferungen',
        'low_credit_alert' => 'Niedrig-Guthaben-Warnung',
        'low_credit_warning' => 'Guthaben unter der Warnschwelle. Kontaktieren Sie Ihren Ansprechpartner — Käufer können im Portal nicht aufladen.',
    ],

    'leads' => [
        'title' => 'Meine Leads',
        'description' => 'Gekaufte Leads suchen, exportieren und melden.',
    ],

    'integrations' => [
        'title' => 'Integrationen',
        'my_leads' => 'Meine Leads',
    ],
];
