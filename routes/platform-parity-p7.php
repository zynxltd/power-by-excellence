<?php

/**
 * P7 — Supplier portal CSV import (routes already wired in routes/web.php).
 *
 *   GET  portal.supplier.leads.import       → SupplierPortalController@importLeads
 *   POST portal.supplier.leads.import.store → SupplierPortalController@storeImport
 *
 * Integration Lead: add supplier nav link to portal.supplier.leads.import if not present.
 */
