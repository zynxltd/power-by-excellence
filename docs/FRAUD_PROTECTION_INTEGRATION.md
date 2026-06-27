# Fraud Protection Add-on - Integration Plan

Maps marketing pricing to platform behaviour. Reference: [Pricing.vue](../resources/js/Pages/Marketing/Pricing.vue).

| Plan | Fraud | Cap (validated leads/mo) | URL scanner |
|------|-------|--------------------------|-------------|
| Starter | +£29/mo add-on | 5,000 | Email, phone, IP only |
| Growth | Included | 25,000 | Full (incl. URL) |
| Enterprise | Included | Custom / unlimited | Full |

---

## Status legend

- ✅ Done
- ⏳ Pending - not started
- ➖ N/A

---

## 1. Entitlement & metering (backend)

| Item | Status | Notes |
|------|--------|-------|
| `config/fraud_protection.php` plan caps | ✅ | `FraudProtectionService` |
| `FraudProtectionService` | ✅ | `isEntitled()`, `canValidateLead()`, `recordValidatedLead()`, `summary()` |
| Account settings: `subscription_plan`, `fraud_protection` | ✅ | On `accounts.settings` JSON |
| Monthly usage counter + period reset | ✅ | `usage_count`, `usage_period` (Y-m) |
| `ValidationService` gates IPQS when not entitled / over cap | ✅ | Skips checks; logs `fraud.cap_exceeded` |
| `ValidationProviderResolver` only returns IPQS when entitled | ✅ | Falls back to demo |
| Auto-provision integration flags on Growth/Enterprise | ✅ | `provisionSettingsForPlan()` on tenant billing save |
| Tests: entitlement, cap, pipeline skip | ✅ | `FraudProtectionTest` |

---

## 2. Super-admin / tenant billing

| Item | Status | Notes |
|------|--------|-------|
| `/accounts/billing` - plan tier select | ✅ | starter \| growth \| enterprise |
| `/accounts/billing` - Fraud add-on toggle (Starter) | ✅ | `fraud_protection_enabled` |
| Billing index - plan + fraud column | ✅ | |
| Billing save syncs validation integration defaults | ✅ | Growth → IPQS + URL on Starter addon → IPQS without URL |

---

## 3. Tenant admin UI

| Item | Status | Notes |
|------|--------|-------|
| Shared Inertia `auth.fraudProtection` prop | ✅ | `HandleInertiaRequests` |
| `/integrations/validation` - entitlement banner | ✅ | Plan, usage, cap, upgrade copy |
| `/integrations/validation` - block IPQS save if not entitled | ✅ | Forces demo provider |
| `/integrations` index - fraud status reflects entitlement | ✅ | connected \| upgrade \| cap_reached |
| `/leads` show - quality panel notes when fraud off | ⏳ | Optional copy |
| `/reports` - fraud metrics only when entitled | ⏳ | Hide IP rows if no fraud |
| Campaign form - fraud requires add-on note | ⏳ | Link to integrations |
| Tenant-facing billing page (read-only fraud usage) | ⏳ | `/billing` account section |

---

## 4. Lead pipeline & distribution

| Item | Status | Notes |
|------|--------|-------|
| `LeadPipeline` → `ValidationService` | ✅ | |
| `LeadQualityService` fraud scoring | ✅ | When checks ran |
| `BuyerEligibilityService` min quality score | ✅ | |
| Quarantine on fraud fail | ✅ | |
| Cap exceeded → skip fraud, accept lead | ✅ | Soft limit |
| Not entitled → skip fraud, no penalty | ✅ | Completeness-only score |

---

## 5. API & ingest

| Item | Status | Notes |
|------|--------|-------|
| API ingest runs pipeline validation | ✅ | |
| 402 on billing lock | ✅ | Separate from fraud |
| Fraud cap does not block ingest | ✅ | |
| API response includes fraud metadata | ✅ | When checks run |

---

## 6. Marketing & docs

| Item | Status | Notes |
|------|--------|-------|
| `/pricing` fraud tiers | ✅ | |
| Homepage pricing preview | ✅ | |
| This integration doc | ✅ | |
| `IMPLEMENTATION_STATUS.md` update | ⏳ | |
| Help centre fraud article | ⏳ | |

---

## 7. Out of scope (v1)

- Stripe self-serve add-on checkout
- Per-tenant IPQS API keys (platform pool key only)
- Transaction scoring (SMB Basic+ IPQS feature)
- Device fingerprint SDK
- Automatic overage invoicing

---

## Settings schema

```json
{
  "subscription_plan": "growth",
  "fraud_protection": {
    "enabled": true,
    "included": true,
    "usage_count": 0,
    "usage_period": "2026-06"
  },
  "validation_integration": {
    "provider": "ipqs",
    "enabled": true,
    "email_validation": true,
    "hlr_validation": true,
    "ip_validation": true,
    "url_validation": true
  }
}
```

---

## Operator workflow

1. Super admin → **Tenant billing** → set plan (Growth = fraud on by default).
2. Starter tenants → enable **Fraud Protection add-on** checkbox.
3. Tenant admin → **Integrations → Validation** → configure IPQS thresholds (platform API key from env).
4. Usage visible on validation page and tenant billing edit.
