/**
 * PowerByExcellence Lead Ingest SDK (browser / Node ESM)
 *
 * Usage:
 *   import { createClient } from '/sdk/pbe-leads.js';
 *   const pbe = createClient({ apiKey: 'pk_live_...', baseUrl: 'https://your-domain.test/api/v1' });
 *   const result = await pbe.ingestLead({ campaign_ref: 'auto-insurance-uk', email: 'a@b.com', sync: true });
 */
export function createClient({ apiKey, baseUrl = '/api/v1' }) {
    if (!apiKey) {
        throw new Error('PowerByExcellence SDK: apiKey is required');
    }

    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${apiKey}`,
    };

    async function request(method, path, body) {
        const res = await fetch(`${baseUrl.replace(/\/$/, '')}${path}`, {
            method,
            headers,
            body: body ? JSON.stringify(body) : undefined,
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            const err = new Error(data.message || `Request failed (${res.status})`);
            err.status = res.status;
            err.data = data;
            throw err;
        }

        return data;
    }

    return {
        /** Submit a lead. Set sync:true for immediate pipeline result. */
        ingestLead(payload) {
            return request('POST', '/leads', payload);
        },

        /** Poll async queue status after ingest. */
        pollQueue(queueId) {
            return request('GET', `/leads/queue/${queueId}`);
        },

        /** Fetch lead by UUID. */
        getLead(uuid) {
            return request('GET', `/leads/${uuid}`);
        },

        /** Search leads (POST body per API spec). */
        searchLeads(criteria) {
            return request('POST', '/leads/search', criteria);
        },
    };
}

if (typeof window !== 'undefined') {
    window.PowerByExcellence = { createClient };
}
