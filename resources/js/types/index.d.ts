export interface User {
    id: number;
    name: string;
    email: string;
    role: 'faculty' | 'coordinator' | 'admin';
    role_label?: string;
    designation?: string;
    academic_rank?: string | null;
    orcid_url?: string | null;
    google_scholar_url?: string | null;
    institutional_profile_url?: string | null;
    college_id?: number | null;
    department_id?: number | null;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    app?: {
        name: string;
        institution: string;
        office: string;
    };
};
