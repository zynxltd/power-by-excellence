/**
 * Lightweight markdown parser for Help Centre articles.
 * Supports: ##/### headings, paragraphs, - lists, 1. lists, tables, ``` code fences, **bold**, `code`
 */

export function parseHelpMarkdown(body = '') {
    const lines = (body ?? '').split('\n');
    const blocks = [];
    let i = 0;

    while (i < lines.length) {
        const trimmed = lines[i].trim();

        if (!trimmed) {
            i++;
            continue;
        }

        if (trimmed.startsWith('```')) {
            const lang = trimmed.slice(3).trim();
            const codeLines = [];
            i++;
            while (i < lines.length && !lines[i].trim().startsWith('```')) {
                codeLines.push(lines[i]);
                i++;
            }
            blocks.push({ type: 'code', content: codeLines.join('\n'), lang });
            i++;
            continue;
        }

        if (trimmed.startsWith('### ')) {
            blocks.push({ type: 'h3', text: trimmed.slice(4) });
            i++;
            continue;
        }

        if (trimmed.startsWith('## ')) {
            blocks.push({ type: 'h2', text: trimmed.slice(3) });
            i++;
            continue;
        }

        if (trimmed.startsWith('|')) {
            const tableLines = [];
            while (i < lines.length && lines[i].trim().startsWith('|')) {
                tableLines.push(lines[i].trim());
                i++;
            }
            const rows = tableLines
                .filter((row) => !/^\|[\s\-:|]+\|$/.test(row))
                .map((row) => row.split('|').slice(1, -1).map((c) => c.trim()));
            if (rows.length) {
                blocks.push({ type: 'table', headers: rows[0], rows: rows.slice(1) });
            }
            continue;
        }

        if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
            const items = [];
            while (i < lines.length) {
                const t = lines[i].trim();
                if (!t.startsWith('- ') && !t.startsWith('* ')) break;
                items.push(t.slice(2));
                i++;
            }
            blocks.push({ type: 'ul', items });
            continue;
        }

        if (/^\d+\.\s/.test(trimmed)) {
            const items = [];
            while (i < lines.length && /^\d+\.\s/.test(lines[i].trim())) {
                items.push(lines[i].trim().replace(/^\d+\.\s/, ''));
                i++;
            }
            blocks.push({ type: 'ol', items });
            continue;
        }

        blocks.push({ type: 'p', text: trimmed });
        i++;
    }

    return blocks;
}

/** @returns {{ type: string, value: string }[]} */
export function parseInline(text = '') {
    const parts = [];
    const re = /(\*\*[^*]+\*\*|`[^`]+`)/g;
    let last = 0;
    let match;

    while ((match = re.exec(text)) !== null) {
        if (match.index > last) {
            parts.push({ type: 'text', value: text.slice(last, match.index) });
        }
        const token = match[0];
        if (token.startsWith('**')) {
            parts.push({ type: 'bold', value: token.slice(2, -2) });
        } else {
            parts.push({ type: 'code', value: token.slice(1, -1) });
        }
        last = match.index + token.length;
    }

    if (last < text.length) {
        parts.push({ type: 'text', value: text.slice(last) });
    }

    return parts.length ? parts : [{ type: 'text', value: text }];
}
