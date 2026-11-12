export type ActivityDates =
    | { mode: 'single'; date: string }
    | { mode: 'range'; start: string; end: string }
    | { mode: 'multiple'; dates: string[] };

export type ActivityDateInput = {
    mode: '' | ActivityDates['mode'];
    date: string;
    start: string;
    end: string;
    dates: string[];
};

export function activityDateInput(saved: ActivityDates | null | undefined): ActivityDateInput {
    return {
        mode: saved?.mode ?? '',
        date: saved?.mode === 'single' ? saved.date : '',
        start: saved?.mode === 'range' ? saved.start : '',
        end: saved?.mode === 'range' ? saved.end : '',
        dates: saved?.mode === 'multiple' ? [...saved.dates] : ['', ''],
    };
}

export function activityDatePayload(input: ActivityDateInput): ActivityDates | null {
    switch (input.mode) {
        case 'single': return { mode: 'single', date: input.date };
        case 'range': return { mode: 'range', start: input.start, end: input.end };
        case 'multiple': return { mode: 'multiple', dates: [...input.dates] };
        default: return null;
    }
}