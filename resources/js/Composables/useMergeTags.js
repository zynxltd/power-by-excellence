export const MERGE_TAGS = ['[firstname]', '[lastname]', '[email]', '[phone1]', '[zipcode]'];

export const SAMPLE_FIELDS = {
    firstname: 'Alex',
    lastname: 'Morgan',
    email: 'alex@example.com',
    phone1: '555-0142',
    zipcode: 'M5H 2N2',
};

export function interpolatePreview(text, fields = SAMPLE_FIELDS) {
    if (!text) {
        return '';
    }

    let result = text.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (_, key) => fields[key] ?? `[${key}]`);

    return result.replace(/\[([a-zA-Z0-9_]+)\]/g, (_, key) => fields[key] ?? `[${key}]`);
}

export function normalizeMergeTags(text) {
    if (!text) {
        return '';
    }

    return text.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, '[$1]');
}
