<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type Faculty = { id: number; name: string; employee_id: string | null; designation: string | null; orcid_url: string | null; google_scholar_url: string | null; institutional_profile_url: string | null };
type Submission = {
    id: number; title: string; reference_code: string; research_type: string; research_type_label: string; publication_date: string | null; report_date?: string | null;
    status: string; claimed_points: number | string | null; fpes_points: number | string; faculty: Faculty;
    college: { name: string } | null; department: { name: string } | null;
    faculty_remarks: string | null;
    evaluation: { score: number | string | null; awarded_points: number | string | null; remarks: string | null; coordinator: { name: string } | null } | null;
    approval: { final_points: number | string | null; decision: string; remarks: string | null; admin: { name: string } | null } | null;
    sdgs: Array<{ number: number; name: string }>;
    research_agendas: Array<{ name: string }>;
};

const props = defineProps<{
    submissions: Submission[];
    settings: Record<string, string | null>;
    signatories: { prepared_by: string; reviewed_by: string; approved_by: string };
    period: { type: string; label: string; year: number };
    filters: { period: string; year: number; month: number; quarter: number; semester: number; college_id: number | null; department_id: number | null };
    scope: string;
    colleges: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string; college_id: number }>;
    coordinators: Array<{ id: number; name: string; department_id: number | null }>;
}>();

const filterForm = reactive({ ...props.filters });
const visibleDepartments = computed(() => filterForm.college_id
    ? props.departments.filter((department) => department.college_id === Number(filterForm.college_id))
    : props.departments);
const totalPoints = computed(() => props.submissions.reduce((sum, item) => sum + Number(item.fpes_points), 0));
const facultyReportRows = computed(() => props.submissions.map((item) => {
    const comments: Array<{ source: string; author: string; text: string }> = [];

    if (item.evaluation?.remarks) {
        comments.push({
            source: 'Coordinator',
            author: item.evaluation.coordinator?.name || 'Coordinator',
            text: item.evaluation.remarks,
        });
    }

    if (item.faculty_remarks) {
        comments.push({ source: 'Faculty', author: item.faculty.name, text: item.faculty_remarks });
    }

    if (item.approval?.remarks && item.approval.decision !== 'rejected') {
        comments.push({
            source: 'Admin',
            author: item.approval.admin?.name || 'Administrator',
            text: item.approval.remarks,
        });
    }

    return {
        ...item,
        approvedPoints: item.status === 'approved' ? Number(item.fpes_points) : 0,
        decisionMaker: item.approval?.admin?.name || null,
        rejectionReason: item.approval?.decision === 'rejected' ? item.approval.remarks : null,
        comments,
    };
}));
const approvedTotalPoints = computed(() => facultyReportRows.value.reduce((sum, item) => sum + item.approvedPoints, 0));
const facultySummaries = computed(() => {
    const groups = new Map<number, { faculty: Faculty; college: string; department: string; count: number; points: number }>();
    for (const item of props.submissions) {
        const group = groups.get(item.faculty.id) ?? { faculty: item.faculty, college: item.college?.name ?? '', department: item.department?.name ?? '', count: 0, points: 0 };
        group.count += 1;
        group.points += Number(item.fpes_points);
        groups.set(item.faculty.id, group);
    }
    return [...groups.values()];
});
const departmentSummaries = computed(() => {
    const groups = new Map<string, { college: string; department: string; count: number; faculty: Set<number>; points: number }>();
    for (const item of props.submissions) {
        const college = item.college?.name ?? 'Unassigned college';
        const department = item.department?.name ?? 'Unassigned department';
        const key = `${college}:${department}`;
        const group = groups.get(key) ?? { college, department, count: 0, faculty: new Set<number>(), points: 0 };
        group.count += 1;
        group.faculty.add(item.faculty.id);
        group.points += Number(item.fpes_points);
        groups.set(key, group);
    }
    return [...groups.values()];
});

function applyFilters() {
    router.get(route('reports.fpes'), filterForm, { preserveState: true, preserveScroll: true });
}

function printReport() {
    window.print();
}

function signatoryLabel(title: string): string {
    return `${title.trim().replace(/:+$/, '')}:`;
}

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function publicationDateLabel(date: string | null): string {
    return date
        ? new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(date))
        : 'Date not recorded';
}
</script>

<template>
    <Head title="FPES Report" />
        <div class="fpes-page" :class="{ 'faculty-report': scope === 'faculty' }">
            <AuthenticatedLayout>
        <div class="print:hidden">
            <PageHeader title="FPES reports" description="Performance summary of research activity in the selected reporting period.">
                <template #actions><button type="button" class="ori-btn-accent" @click="printReport">Print report</button></template>
            </PageHeader>
            <form class="mb-6 flex flex-wrap items-end gap-3 border-y border-ori-blue-100 py-4" @submit.prevent="applyFilters">
                <label class="grid gap-1 text-sm font-semibold text-ori-blue-800">Period
                    <select v-model="filterForm.period" class="rounded-md border-ori-blue-200 text-sm"><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="semiannual">Semiannual</option><option value="annual">Annual</option></select>
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ori-blue-800">Year
                    <input v-model.number="filterForm.year" type="number" min="2000" max="2100" class="w-28 rounded-md border-ori-blue-200 text-sm" />
                </label>
                <label v-if="filterForm.period === 'monthly'" class="grid gap-1 text-sm font-semibold text-ori-blue-800">Month
                    <select v-model.number="filterForm.month" class="rounded-md border-ori-blue-200 text-sm"><option v-for="(month, index) in ['January','February','March','April','May','June','July','August','September','October','November','December']" :key="month" :value="index + 1">{{ month }}</option></select>
                </label>
                <label v-if="filterForm.period === 'quarterly'" class="grid gap-1 text-sm font-semibold text-ori-blue-800">Quarter
                    <select v-model.number="filterForm.quarter" class="rounded-md border-ori-blue-200 text-sm"><option :value="1">Q1</option><option :value="2">Q2</option><option :value="3">Q3</option><option :value="4">Q4</option></select>
                </label>
                <label v-if="filterForm.period === 'semiannual'" class="grid gap-1 text-sm font-semibold text-ori-blue-800">Semester
                    <select v-model.number="filterForm.semester" class="rounded-md border-ori-blue-200 text-sm"><option :value="1">1st semester</option><option :value="2">2nd semester</option></select>
                </label>
                <label v-if="scope === 'admin'" class="grid gap-1 text-sm font-semibold text-ori-blue-800">College
                    <select v-model="filterForm.college_id" class="rounded-md border-ori-blue-200 text-sm"><option :value="null">All colleges</option><option v-for="college in colleges" :key="college.id" :value="college.id">{{ college.name }}</option></select>
                </label>
                <label v-if="scope === 'admin'" class="grid gap-1 text-sm font-semibold text-ori-blue-800">Department
                    <select v-model="filterForm.department_id" class="rounded-md border-ori-blue-200 text-sm"><option :value="null">All departments</option><option v-for="department in visibleDepartments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                </label>
                <button type="submit" class="rounded-md border border-ori-blue-200 px-4 py-2 text-sm font-semibold text-ori-blue-800 hover:bg-ori-blue-50">Generate</button>
            </form>
        </div>

        <article class="fpes-sheet bg-white text-slate-900">
            <header class="fpes-letterhead border-b-2 border-slate-800 pb-4">
                <div class="fpes-letterhead-grid">
                    <div class="fpes-logo-wrap">
                        <img v-if="settings.university_logo_url" :src="settings.university_logo_url" alt="Leyte Normal University logo" class="fpes-logo" />
                        <div v-else class="fpes-logo-fallback">LNU</div>
                    </div>
                    <div class="fpes-letterhead-copy">
                        <p class="text-base font-bold uppercase">{{ settings.institution_name || 'Leyte Normal University' }}</p>
                        <p class="mt-1 text-sm font-semibold">{{ settings.office_name || 'Office of Research and Innovation' }}</p>
                        <h1 class="mt-4 text-xl font-bold uppercase">{{ settings.report_heading || 'Faculty Performance Evaluation Sheet' }}</h1>
                        <p v-if="settings.report_subheading" class="mt-1 text-sm">{{ settings.report_subheading }}</p>
                        <p class="mt-2 text-sm font-semibold">{{ period.type }} report · {{ period.label }}</p>
                    </div>
                    <div class="fpes-logo-wrap">
                        <img v-if="settings.logo_url" :src="settings.logo_url" alt="Office of Research and Innovation logo" class="fpes-logo" />
                        <div v-else class="fpes-logo-fallback">ORI</div>
                    </div>
                </div>
            </header>

            <template v-if="scope === 'faculty'">
                <section class="my-5 space-y-1 text-sm">
                    <p><strong>Faculty Name:</strong> {{ facultySummaries[0]?.faculty.name ?? 'No faculty records' }}</p>
                    <p><strong>Department:</strong> {{ facultySummaries[0]?.department || 'Not assigned' }}</p>
                    <p><strong>Date:</strong> {{ period.label }}</p>
                </section>

                <section class="mb-6">
                    <h2 class="text-sm font-bold">Summary of FPES Report</h2>
                    <p class="mb-3 text-xs leading-relaxed">Research involvement includes research outputs, presentations, organizing and reviewing research activities, and related intellectual property or innovation work recorded for the selected period.</p>
                    <table class="fpes-faculty-table w-full border-collapse text-left text-xs">
                        <thead><tr><th>Submission / Reference</th><th>Research Involvement / Date</th><th>Approval Status / Decided By</th><th class="text-right">Points Approved</th><th>Rejection Reason / Confirmation / Comments</th></tr></thead>
                        <tbody>
                            <tr v-for="row in facultyReportRows" :key="row.id">
                                <td><strong>{{ row.title }}</strong><span class="block text-slate-600">{{ row.reference_code }}</span></td>
                                <td>{{ row.research_type_label }}<span class="block text-slate-600">{{ publicationDateLabel(row.publication_date ?? row.report_date ?? null) }}</span></td>
                                <td><strong>{{ statusLabel(row.status) }}</strong><span v-if="row.decisionMaker" class="block">{{ row.approval?.decision === 'rejected' ? 'Rejected' : 'Approved' }} by {{ row.decisionMaker }}</span><span v-else-if="row.status === 'evaluated'" class="block text-slate-600">Awaiting admin decision</span></td>
                                <td class="text-right tabular-nums">{{ row.approvedPoints.toFixed(2) }}</td>
                                <td>
                                    <div v-if="row.rejectionReason" class="mb-1"><strong>Reason:</strong> {{ row.rejectionReason }}<span v-if="row.decisionMaker" class="block text-slate-600">Rejected by {{ row.decisionMaker }}</span></div>
                                    <div v-else-if="row.approval?.decision === 'rejected'" class="mb-1"><strong>Reason:</strong> Not recorded<span v-if="row.decisionMaker" class="block text-slate-600">Rejected by {{ row.decisionMaker }}</span></div>
                                    <ul v-if="row.comments.length" class="space-y-1">
                                        <li v-for="(comment, index) in row.comments" :key="`${row.id}-comment-${index}`"><strong>{{ comment.source }} · {{ comment.author }}:</strong> {{ comment.text }}</li>
                                    </ul>
                                    <span v-if="!row.rejectionReason && row.approval?.decision !== 'rejected' && row.comments.length === 0">—</span>
                                </td>
                            </tr>
                            <tr class="font-bold"><td colspan="3">Total Approved Points</td><td class="text-right tabular-nums">{{ approvedTotalPoints.toFixed(2) }}</td><td></td></tr>
                            <tr v-if="facultyReportRows.length === 0"><td colspan="5" class="text-center">No research outputs for this reporting period.</td></tr>
                        </tbody>
                    </table>
                </section>

                <section class="fpes-faculty-signatures">
                    <div class="fpes-signature-row"><span>{{ signatoryLabel(settings.prepared_by_title || 'Prepared by') }}</span><span class="fpes-signature"><strong>{{ signatories.prepared_by || '\u00a0' }}</strong><small>Signature over Printed Name</small></span><span class="fpes-date-signature"></span></div>
                    <div class="fpes-signature-row"><span>{{ signatoryLabel(settings.reviewed_by_title || 'Evaluated by') }}</span><span class="fpes-signature"><strong>{{ signatories.reviewed_by || '\u00a0' }}</strong><small>{{ facultySummaries[0]?.college || 'College' }} Coordinator</small></span><span class="fpes-date-signature"><small>Date Signed</small></span></div>
                    <div class="fpes-signature-row"><span>{{ signatoryLabel(settings.approved_by_title || 'Approved by') }}</span><span class="fpes-signature"><strong>{{ signatories.approved_by || '\u00a0' }}</strong><small>Office of Research and Innovation</small></span><span class="fpes-date-signature"><small>Date Signed</small></span></div>
                </section>

                <p class="fpes-confirmation">I confirm the above-stated total points and descriptive rating. I understand that these points will be used in calculating my research involvement in the FPES.</p>
                <section class="fpes-faculty-signatures mt-10">
                    <div class="fpes-signature-row"><span></span><span class="fpes-signature"><strong>{{ signatories.prepared_by || '\u00a0' }}</strong><small>Signature over Printed Name</small></span><span class="fpes-date-signature"><small>Date Signed</small></span></div>
                </section>
            </template>

            <template v-else>
            <section class="my-5 grid gap-2 text-sm sm:grid-cols-2">
                <p><strong>Prepared for:</strong> {{ scope === 'faculty' ? facultySummaries[0]?.faculty.name ?? 'No faculty records' : 'Department / college summary' }}</p>
                <p v-if="scope === 'coordinator'"><strong>Evaluating coordinator:</strong> {{ coordinators.find((coordinator) => coordinator.department_id === $page.props.auth.user.department_id)?.name || 'Not assigned' }}</p>
                <p><strong>Records:</strong> {{ submissions.length }}</p>
                <p v-if="scope === 'faculty'"><strong>Designation:</strong> {{ facultySummaries[0]?.faculty.designation || 'Not specified' }}</p>
                <p><strong>Total awarded / claimed points:</strong> {{ totalPoints.toFixed(2) }}</p>
            </section>

            <section v-if="scope !== 'faculty'" class="mb-6">
                <h2 class="mb-2 text-sm font-bold uppercase">College and department summary</h2>
                <table class="w-full border-collapse text-left text-xs">
                    <thead><tr class="border-y border-slate-500 bg-slate-100"><th class="p-2">College</th><th class="p-2">Department</th><th class="p-2 text-right">Faculty</th><th class="p-2 text-right">Outputs</th><th class="p-2 text-right">Points</th></tr></thead>
                    <tbody><tr v-for="summary in departmentSummaries" :key="`${summary.college}:${summary.department}`" class="border-b border-slate-300"><td class="p-2">{{ summary.college }}</td><td class="p-2">{{ summary.department }}</td><td class="p-2 text-right">{{ summary.faculty.size }}</td><td class="p-2 text-right">{{ summary.count }}</td><td class="p-2 text-right">{{ summary.points.toFixed(2) }}</td></tr></tbody>
                </table>
            </section>

            <section class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead><tr class="border-y border-slate-500 bg-slate-100"><th class="p-2">Faculty</th><th class="p-2">College / department</th><th class="p-2">Research outputs</th><th class="p-2 text-right">Records</th><th class="p-2 text-right">Points</th></tr></thead>
                    <tbody>
                        <tr v-for="summary in facultySummaries" :key="summary.faculty.id" class="border-b border-slate-300 align-top">
                            <td class="p-2"><strong>{{ summary.faculty.name }}</strong><br />{{ summary.faculty.employee_id || '' }}<br />{{ summary.faculty.designation || '' }}
                                <div class="mt-1 flex flex-wrap gap-x-2 print:hidden"><a v-if="summary.faculty.orcid_url" :href="summary.faculty.orcid_url" target="_blank" rel="noreferrer">ORCID</a><a v-if="summary.faculty.google_scholar_url" :href="summary.faculty.google_scholar_url" target="_blank" rel="noreferrer">Scholar</a><a v-if="summary.faculty.institutional_profile_url" :href="summary.faculty.institutional_profile_url" target="_blank" rel="noreferrer">Institutional profile</a></div>
                            </td>
                            <td class="p-2">{{ summary.college }}<br />{{ summary.department }}</td>
                            <td class="p-2"><ul class="space-y-1"><li v-for="item in submissions.filter((record) => record.faculty.id === summary.faculty.id)" :key="item.id">{{ item.title }} ({{ item.research_type_label }}, {{ item.publication_date || item.report_date || 'date not set' }})<br /><span class="text-slate-600">{{ item.reference_code }} · {{ item.status }}</span><br /><span v-if="item.sdgs?.length" class="text-slate-600">{{ item.sdgs.map((sdg) => `SDG ${sdg.number}: ${sdg.name}`).join(', ') }}</span></li></ul></td>
                            <td class="p-2 text-right">{{ summary.count }}</td><td class="p-2 text-right">{{ summary.points.toFixed(2) }}</td>
                        </tr>
                        <tr v-if="facultySummaries.length === 0"><td colspan="5" class="p-6 text-center">No research outputs for this reporting period.</td></tr>
                    </tbody>
                </table>
            </section>

            <footer class="fpes-signatories mt-12 grid gap-8 sm:grid-cols-3">
                <div v-for="signatory in [{ title: settings.prepared_by_title || 'Prepared by', name: signatories.prepared_by }, { title: settings.reviewed_by_title || 'Reviewed by', name: signatories.reviewed_by }, { title: settings.approved_by_title || 'Approved by', name: signatories.approved_by }]" :key="signatory.title" class="px-2 pt-10 text-center text-xs">
                    <p class="min-h-5 border-t border-slate-700 pt-2 font-semibold">{{ signatory.name || '\u00a0' }}</p><p>{{ signatory.title }}</p>
                </div>
            </footer>
            </template>
            <p v-if="settings.footer_text" class="mt-8 border-t border-slate-300 pt-3 text-center text-xs">{{ settings.footer_text }}</p>
        </article>
      </AuthenticatedLayout>
    </div>
</template>

<style>
.fpes-letterhead-grid {
    display: grid;
    grid-template-columns: 5.5rem minmax(0, 1fr) 5.5rem;
    align-items: center;
    gap: 1rem;
}

.fpes-logo-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
}

.fpes-logo {
    width: 5rem;
    height: 5rem;
    object-fit: contain;
}

.fpes-logo-fallback {
    display: flex;
    width: 4rem;
    height: 4rem;
    align-items: center;
    justify-content: center;
    border: 1px solid #1e4d8c;
    color: #1e4d8c;
    font-size: 0.75rem;
    font-weight: 700;
}

.fpes-letterhead-copy {
    text-align: center;
}

.fpes-sheet {
    box-sizing: border-box;
    padding-inline: 5mm;
}

.fpes-faculty-table th,
.fpes-faculty-table td {
    border: 1px solid #475569;
    padding: 0.4rem;
    vertical-align: top;
}

.fpes-faculty-signatures {
    display: grid;
    gap: 1.5rem;
    margin-top: 2rem;
}

.fpes-signature-row {
    display: grid;
    grid-template-columns: minmax(5.5rem, 1fr) minmax(10rem, 2fr) minmax(5rem, 1fr);
    align-items: end;
    gap: 0.75rem;
    font-size: 0.75rem;
}

.fpes-signature,
.fpes-date-signature {
    display: flex;
    min-height: 2.5rem;
    flex-direction: column;
    align-items: center;
    justify-content: end;
    border-bottom: 1px solid #334155;
    text-align: center;
}

.fpes-signature small,
.fpes-date-signature small {
    position: relative;
    top: 1.15rem;
    white-space: nowrap;
}

.fpes-confirmation {
    margin-top: 3rem;
    font-size: 0.75rem;
    font-weight: 700;
}

@media print {
    @page { size: landscape; margin: 14mm; }
    @page fpes-portrait { size: portrait; margin: 13mm; }
    .fpes-page.faculty-report { page: fpes-portrait; }
    body { background: white !important; }
    .fpes-page nav { display: none !important; }
    .fpes-page main { max-width: none !important; margin: 0 !important; padding: 0 !important; }
    .fpes-sheet { color: black; font-size: 10pt; padding-inline: 5mm; }
    .fpes-sheet table { break-inside: auto; }
    .fpes-sheet tr { break-inside: avoid; }
    .fpes-letterhead-grid { grid-template-columns: 6rem minmax(0, 1fr) 6rem; }
    .fpes-logo { width: 5.5rem; height: 5.5rem; }
    .fpes-signatories { break-inside: avoid; }
    .fpes-faculty-signatures,
    .fpes-confirmation { break-inside: avoid; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>