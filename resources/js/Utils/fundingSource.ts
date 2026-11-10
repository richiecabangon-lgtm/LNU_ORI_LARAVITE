export function formatFundingSource(source: string | null | undefined, organization: string | null | undefined): string {
    switch (source) {
        case 'personal': return 'Personal (sole)';
        case 'internal': return 'Internal (LNU funded)';
        case 'external': return organization ? `External (${organization})` : 'External';
        default: return 'Not specified';
    }
}