<?php

return [
    'portal_title' => 'Portail acheteur',

    'nav' => [
        'dashboard' => 'Tableau de bord',
        'leads' => 'Mes leads',
        'billing' => 'Facturation',
        'integrations' => 'Intégrations',
    ],

    'common' => [
        'view' => 'Voir',
        'download_csv' => 'Télécharger CSV',
        'export_csv' => 'Exporter CSV',
        'back_to_leads' => 'Retour aux leads',
        'view_all_leads' => 'Voir tous les leads',
        'view_leads' => 'Voir les leads',
        'date' => 'Date',
        'type' => 'Type',
        'amount' => 'Montant',
        'balance' => 'Solde',
        'description' => 'Description',
        'name' => 'Nom',
        'cost' => 'Coût',
        'feedback' => 'Retour',
        'lead' => 'Lead',
        'required' => 'Obligatoire',
        'optional' => 'Facultatif',
        'no_activity' => 'Aucun retour ni retour produit pour le moment.',
    ],

    'dashboard' => [
        'title' => 'Tableau de bord acheteur',
        'subtitle' => 'Performance et inventaire pour :name',
        'credit' => 'Crédit',
        'leads_today' => 'Leads aujourd\'hui',
        'spend_7d' => 'Dépenses (7 j)',
        'conversion' => 'Conversion',
        'pending_returns_one' => ':count retour en attente de validation.',
        'pending_returns_many' => ':count retours en attente de validation.',
        'leads_chart' => 'Leads achetés — 7 derniers jours',
        'spend_chart' => 'Dépenses — 7 derniers jours (:currency)',
        'recent_leads' => 'Leads achetés récemment',
        'recent_activity' => 'Activité récente',
        'activity_return' => 'Retour · :status',
        'activity_feedback' => 'Retour · :status',
        'activity_converted' => ' · converti',
        'chart_leads' => 'Leads',
        'chart_spend' => 'Dépenses (:currency)',
    ],

    'billing' => [
        'title' => 'Crédits et facturation',
        'description' => 'Consultez le solde et l\'historique. Les recharges sont gérées par votre administrateur de plateforme.',
        'balance' => 'Solde',
        'spend_30d' => 'Dépenses (30 j)',
        'prepay' => 'Prépaiement',
        'prepay_notice_title' => 'Facturation prépayée',
        'prepay_notice' => 'Les leads débitent votre solde à la vente. Contactez votre gestionnaire de compte pour recharger. Les acheteurs ne peuvent pas s\'auto-financer dans le portail.',
        'transactions' => 'Historique des transactions',
    ],

    'ledger' => [
        'credit' => 'Crédit (recharge)',
        'debit' => 'Achat de lead',
        'goodwill' => 'Crédit commercial',
        'correction' => 'Correction de solde',
        'refund' => 'Remboursement',
        'manual_debit' => 'Débit manuel',
        'chargeback' => 'Rétrofacturation',
        'adjustment' => 'Ajustement général',
    ],

    'account' => [
        'title' => 'Compte et limites',
        'intro' => 'Les plafonds, recharges et validations de retours sont gérés par votre administrateur de plateforme.',
        'buyer_status' => 'Statut acheteur',
        'billing_mode' => 'Facturation',
        'prepay_required' => 'Prépaiement — solde requis',
        'postpaid' => 'Post-payé',
        'daily_cap' => 'Plafond journalier de leads',
        'daily_spend_cap' => 'Plafond journalier de dépenses',
        'active_deliveries' => 'Livraisons actives',
        'low_credit_alert' => 'Alerte crédit faible',
        'low_credit_warning' => 'Crédit sous le seuil d\'alerte. Contactez votre gestionnaire — les acheteurs ne peuvent pas recharger dans le portail.',
    ],

    'leads' => [
        'title' => 'Mes leads',
        'description' => 'Rechercher, exporter et signaler les leads achetés.',
    ],

    'integrations' => [
        'title' => 'Intégrations',
        'my_leads' => 'Mes leads',
    ],
];
