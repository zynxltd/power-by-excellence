<?php

return [
    'portal_title' => 'Buyer Portal',

    'nav' => [
        'dashboard' => 'Dashboard',
        'leads' => 'My Leads',
        'billing' => 'Billing',
        'integrations' => 'Integrations',
    ],

    'common' => [
        'view' => 'View',
        'download_csv' => 'Download CSV',
        'export_csv' => 'Export CSV',
        'back_to_leads' => 'Back to leads',
        'view_all_leads' => 'View all leads',
        'view_leads' => 'View leads',
        'date' => 'Date',
        'type' => 'Type',
        'amount' => 'Amount',
        'balance' => 'Balance',
        'description' => 'Description',
        'name' => 'Name',
        'cost' => 'Cost',
        'feedback' => 'Feedback',
        'lead' => 'Lead',
        'required' => 'Required',
        'optional' => 'Optional',
        'no_activity' => 'No feedback or returns yet.',
    ],

    'dashboard' => [
        'title' => 'Buyer Dashboard',
        'subtitle' => 'Performance & inventory for :name',
        'credit' => 'Credit',
        'leads_today' => 'Leads today',
        'spend_7d' => 'Spend (7d)',
        'conversion' => 'Conversion',
        'pending_returns_one' => ':count return awaiting platform review.',
        'pending_returns_many' => ':count returns awaiting platform review.',
        'leads_chart' => 'Leads purchased — last 7 days',
        'spend_chart' => 'Spend — last 7 days (:currency)',
        'recent_leads' => 'Recent purchased leads',
        'recent_activity' => 'Recent activity',
        'activity_return' => 'Return · :status',
        'activity_feedback' => 'Feedback · :status',
        'activity_converted' => ' · converted',
        'chart_leads' => 'Leads',
        'chart_spend' => 'Spend (:currency)',
    ],

    'billing' => [
        'title' => 'Credits & Billing',
        'description' => 'View balance and ledger activity. Credit top-ups are arranged by your platform administrator.',
        'balance' => 'Balance',
        'spend_30d' => 'Spend (30d)',
        'prepay' => 'Prepay',
        'prepay_notice_title' => 'Prepay billing',
        'prepay_notice' => 'Leads debit your balance when sold. Contact your account manager to top up credit. Buyers cannot self-fund in the portal.',
        'transactions' => 'Transaction history',
    ],

    'ledger' => [
        'credit' => 'Credit (top-up)',
        'debit' => 'Lead purchase',
        'goodwill' => 'Goodwill credit',
        'correction' => 'Balance correction',
        'refund' => 'Refund',
        'manual_debit' => 'Manual debit',
        'chargeback' => 'Chargeback',
        'adjustment' => 'General adjustment',
    ],

    'account' => [
        'title' => 'Account & limits',
        'intro' => 'Delivery caps, credit top-ups, and return approvals are managed by your platform administrator.',
        'buyer_status' => 'Buyer status',
        'billing_mode' => 'Billing',
        'prepay_required' => 'Prepay — balance required',
        'postpaid' => 'Post-paid',
        'daily_cap' => 'Daily lead cap',
        'daily_spend_cap' => 'Daily spend cap',
        'active_deliveries' => 'Active deliveries',
        'low_credit_alert' => 'Low-credit alert',
        'low_credit_warning' => 'Credit is below your alert threshold. Contact your account manager to top up — buyers cannot add credit in the portal.',
    ],

    'leads' => [
        'title' => 'My Leads',
        'description' => 'Search, export, and report on leads purchased for your account.',
    ],

    'integrations' => [
        'title' => 'Integrations',
        'my_leads' => 'My Leads',
    ],
];
