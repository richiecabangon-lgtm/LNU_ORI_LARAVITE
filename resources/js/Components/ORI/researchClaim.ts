export type ResearchType = {
    value: string;
    label: string;
    group?: string;
    has_authors?: boolean;
    unit: 'hour' | 'day' | 'output';
    semester_ceiling: number | string | null;
    requirements: Array<{ id: number; name: string; is_required: boolean }>;
};

export function calculateResearchClaim(type: ResearchType | undefined, rateValue: number | string | null | undefined, quantity: string | number, legacy: boolean): { rate: number | null; points: number | null; error: string } {
    if (!type) return { rate: null, points: null, error: 'Select an involvement type.' };
    if (rateValue === null || rateValue === undefined || String(rateValue).trim() === '') return { rate: null, points: null, error: 'The administrator has not configured points for this involvement type.' };
    const rate = Number(rateValue);
    if (!Number.isFinite(rate) || rate < 0) return { rate: null, points: null, error: 'The configured point rate is invalid.' };

    const raw = String(quantity).trim();
    let multiplier = 1;
    if (type.unit !== 'output' && !(legacy && raw === '')) {
        const validFormat = type.unit === 'hour' ? /^\d+(?:\.\d{1,2})?$/ : /^\d+$/;
        const amount = Number(raw);
        const maximum = type.unit === 'hour' ? 999999.99 : 999999;
        if (!validFormat.test(raw) || amount <= 0 || amount > maximum) {
            return { rate, points: null, error: type.unit === 'hour' ? 'Enter hours greater than zero, up to 999999.99, with at most two decimal places.' : 'Enter a whole number of days from 1 to 999999.' };
        }
        multiplier = amount;
    }
    const scaled = rate * multiplier * 100;
    const points = Math.round(scaled + Number.EPSILON * Math.abs(scaled)) / 100;
    if (!Number.isFinite(points) || points > 999999.99) return { rate, points: null, error: 'The calculated points cannot exceed 999999.99.' };
    return { rate, points, error: '' };
}