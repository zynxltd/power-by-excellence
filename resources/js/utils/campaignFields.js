/**
 * @param {{ fields?: Array, api_spec?: { fields?: Array } } | null | undefined} campaign
 * @returns {Array<{ name: string, label: string, type?: string }>}
 */
export function fieldOptionsFromCampaign(campaign) {
    if (!campaign) {
        return [];
    }

    const fields = (campaign.fields ?? []).map((f) => ({
        name: f.name,
        label: f.label || f.name,
        type: f.type,
    }));

    const apiFields = (campaign.api_spec?.fields ?? [])
        .map((f) => ({
            name: f.name ?? '',
            label: `${f.label || f.name || ''} (API)`,
            type: f.type,
        }))
        .filter((f) => f.name !== '');

    const seen = new Set();

    return [...fields, ...apiFields].filter((f) => {
        if (seen.has(f.name)) {
            return false;
        }
        seen.add(f.name);
        return true;
    });
}
