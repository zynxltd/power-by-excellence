<?php

return [
    'articles' => [
        'what-is-ping-tree-routing' => [
            'title' => 'What Is Ping-Tree Routing?',
            'excerpt' => 'How tiered buyer auctions maximise revenue per lead in real-time distribution.',
            'category' => 'Routing',
            'published_at' => '2026-06-01',
            'body' => "## Ping-tree routing explained\n\nPing-tree routing sends each lead through **ordered tiers of buyers**. Tier 1 typically runs a **real-time auction** - all eligible buyers are pinged in parallel, and the highest bid above your floor price wins.\n\nIf no buyer accepts at Tier 1, the lead cascades to Tier 2, then Tier 3, and so on until sold or exhausted.\n\n### Why agencies use it\n\n- Maximises revenue per lead\n- Reduces unsold inventory\n- Gives buyers fair competition on partial data\n- Supports exclusive and multi-sell campaigns\n\n### PowerByExcellence implementation\n\nConfigure tiers in **Ping Tree**, attach deliveries per tier, and monitor outcomes in **Live Operations** and **Reports**.",
        ],
        'real-time-bidding-for-lead-buyers' => [
            'title' => 'Real-Time Bidding for Lead Buyers',
            'excerpt' => 'Dynamic pricing via ping-post auctions - how Cost fields, floor prices, and winner-only posts work.',
            'category' => 'Monetisation',
            'published_at' => '2026-06-08',
            'body' => "## Dynamic bids from buyer responses\n\nIn a **ping-post** flow, buyers return a `Cost` (bid) on partial lead data. Your platform compares bids against the campaign **floor price** and selects the winner.\n\nPowerByExcellence runs **ping-only first** in auction tiers - only the winning buyer receives the full post, preventing duplicate sales.\n\n### Best practices\n\n1. Set realistic floor prices per vertical\n2. Use dynamic revenue type on ping-post deliveries\n3. Monitor outbid and rejection rates in Reports\n4. Cap buyer daily spend to control risk",
        ],
        'multi-vertical-lead-capture' => [
            'title' => 'Multi-Vertical Lead Capture',
            'excerpt' => 'Running Auto Insurance, Loans, Mortgage, Solar and more from one platform.',
            'category' => 'Operations',
            'published_at' => '2026-06-15',
            'body' => "## One platform, many verticals\n\nEach **campaign** maps to a vertical with its own fields, floor price, deliveries, and ping tree.\n\nUse the **Form Builder** for hosted capture pages per vertical, or ingest via REST API with `campaign_ref`.\n\n### Vertical-specific fields\n\nInsurance leads need vehicle data; mortgage leads need property value; solar leads need roof and bill data. Field templates are pre-configured per vertical in PowerByExcellence.",
        ],
        'supplier-postbacks-and-tracking' => [
            'title' => 'Supplier Postbacks & Conversion Tracking',
            'excerpt' => 'Fire affiliate pixels when leads are accepted, sold, or rejected - scoped per supplier.',
            'category' => 'Integrations',
            'published_at' => '2026-06-20',
            'body' => "## Postback Manager\n\nAffiliates and media buyers need conversion pixels when leads monetise. The **Postback Manager** fires GET/POST URLs on events:\n\n- `lead.accepted`\n- `lead.sold`\n- `lead.rejected`\n- `delivery.success`\n\nUse `[field]` tags in URLs for dynamic values like `lead_uuid`, `revenue`, and `sid`.",
        ],
    ],
];
