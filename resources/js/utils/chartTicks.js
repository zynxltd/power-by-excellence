/**
 * Pick readable x-axis ticks for time-series charts.
 */
export function chartXTicks(labels, maxTicks = 7) {
    const items = labels ?? [];
    const n = items.length;
    if (!n) {
        return [];
    }

    const step = n <= maxTicks ? 1 : Math.ceil(n / maxTicks);
    const indices = new Set([0, n - 1]);

    for (let i = 0; i < n; i += step) {
        indices.add(i);
    }

    return [...indices]
        .sort((a, b) => a - b)
        .map((index) => ({
            index,
            label: shortenChartLabel(items[index], n),
            position: n <= 1 ? 0.5 : index / (n - 1),
        }));
}

export function shortenChartLabel(label, totalDays) {
    const text = String(label ?? '');
    const parts = text.split(/\s+/).filter(Boolean);

    if (totalDays <= 10) {
        return text;
    }

    if (totalDays <= 14) {
        return parts.length >= 2 ? `${parts[0]} ${parts[1]}` : text;
    }

    if (parts.length >= 3) {
        return `${parts[1]} ${parts[2]}`;
    }

    if (parts.length >= 2) {
        return parts[1];
    }

    return text;
}

export function xPositionPercent(position, paddingLeft = 2, paddingRight = 2) {
    const inner = 100 - paddingLeft - paddingRight;

    return paddingLeft + position * inner;
}
