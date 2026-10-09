<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import AgendaContextDisclosure from '@/Components/ORI/AgendaContextDisclosure.vue';
import AttachmentViewer from '@/Components/ORI/AttachmentViewer.vue';
import LocalFilePreview from '@/Components/ORI/LocalFilePreview.vue';
import AuthorizationProofLink from '@/Components/ORI/AuthorizationProofLink.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    uploadLimits: { file: number; total: number };
    submission: any;
    academicYears: Array<{ id: number; name: string; is_current: boolean }>;
    researchTypes: Array<{ value: string; label: string; group?: string; has_authors?: boolean }>;
    researchTypePoints: Record<string, number | string>;
    currentFaculty: { id: number; name: string; email: string };
    facultyDirectory: Array<{ id: number; name: string; email: string; college: { name: string } | null; department: { name: string } | null }>;
    sdgs: Array<{ id: number; number: number; name: string; image_url: string | null }>;
    researchAgendas: Array<{ id: number; name: string; description: string | null; image_url: string | null }>;
}>();

const groupedResearchTypes = computed(() => {
    const groups = new Map<string, typeof props.researchTypes>();
    props.researchTypes.forEach((type) => groups.set(type.group ?? 'Other', [...(groups.get(type.group ?? 'Other') ?? []), type]));
    return [...groups].map(([label, types]) => ({ label, types }));
});

type ExistingAuthor = {
    name: string;
    email: string | null;
    institution: string | null;
    allocated_points: number | string;
    authorization_original_name: string | null;
};

const existingAuthors: ExistingAuthor[] = props.submission.authorship_allocations?.length
    ? props.submission.authorship_allocations as ExistingAuthor[]
    : ((props.submission.authors ?? []) as string[]).map((name: string) => ({ name, email: '', institution: '', allocated_points: 0, authorization_original_name: null }));

function isLinkedLnuFaculty(author: ExistingAuthor): boolean {
    return props.facultyDirectory.some((faculty) =>
        (author.email && faculty.email.toLowerCase() === author.email.toLowerCase())
        || faculty.name.toLowerCase() === author.name.toLowerCase(),
    );
}

const form = useForm({
    title: props.submission.title,
    research_type: props.submission.research_type,
    academic_year_id: props.submission.academic_year_id,
    abstract: props.submission.abstract || '',
    authors: existingAuthors.map((author: any) => author.name),
    sdgs: (props.submission.sdgs ?? []).map((sdg: { id: number }) => sdg.id),
    research_agendas: (props.submission.research_agendas ?? []).map((agenda: { id: number }) => agenda.id),
    author_emails: existingAuthors.map((author: any) => author.email ?? ''),
    author_institutions: existingAuthors.map((author) => isLinkedLnuFaculty(author) ? 'Leyte Normal University' : author.institution ?? ''),
    author_points: existingAuthors.map((author: any) => Number(author.allocated_points ?? 0)),
    authorization_files: existingAuthors.map(() => null as File | null),
    venue: props.submission.venue || '',
    publisher: props.submission.publisher || '',
    doi: props.submission.doi || '',
    issn_isbn: props.submission.issn_isbn || '',
    indexing: props.submission.indexing || '',
    publication_date: props.submission.publication_date?.substring?.(0, 10) || props.submission.publication_date || '',
    claimed_points: props.submission.claimed_points || '',
    faculty_remarks: props.submission.faculty_remarks || '',
    attachments: [] as File[],
    submit_now: false,
});

const authorCount = computed(() => Math.max(1, form.authors.filter((author) => author.trim()).length));
const configuredPoints = computed(() => Number(props.researchTypePoints[form.research_type] ?? 0));
const pointScheduleConfigured = computed(() => Object.prototype.hasOwnProperty.call(props.researchTypePoints, form.research_type));
const allocatedPointsTotal = computed(() => form.author_points.reduce((total, points) => total + Number(points || 0), 0));
const uploadFiles = computed(() => [...form.attachments, ...form.authorization_files.filter((file): file is File => file !== null)]);
const uploadProblem = computed(() => {
    const megabytes = (bytes: number) => `${Math.floor(bytes / 1048576)} MB`;
    const oversized = uploadFiles.value.find((file) => file.size > props.uploadLimits.file);
    if (oversized) return `${oversized.name} is larger than the ${megabytes(props.uploadLimits.file)} per-file limit.`;
    const total = uploadFiles.value.reduce((sum, file) => sum + file.size, 0);
    return total > props.uploadLimits.total * 0.95 ? `The selected files total ${(total / 1048576).toFixed(1)} MB, above the ${megabytes(props.uploadLimits.total)} limit for one submission. Remove some files or save the draft first and add the rest later.` : '';
});
const involvementHasAuthors = computed(() => props.researchTypes.find((type) => type.value === form.research_type)?.has_authors ?? true);
const titleLabel = computed(() => involvementHasAuthors.value ? 'Research title' : 'Title of the paper, thesis or activity');
const allocationsMatch = computed(() => !involvementHasAuthors.value || Math.abs(allocatedPointsTotal.value - configuredPoints.value) <= 0.01);
const authorDetailsError = computed(() => (form.errors as Record<string, string>).author_details);

const addAuthor = () => {
    form.authors.push('');
    form.author_emails.push('');
    form.author_institutions.push('');
    form.author_points.push(0);
    form.authorization_files.push(null);
    setEqualAuthorShares();
};

const removeAuthor = (index: number) => {
    form.authors.splice(index, 1);
    form.author_emails.splice(index, 1);
    form.author_institutions.splice(index, 1);
    form.author_points.splice(index, 1);
    form.authorization_files.splice(index, 1);
    if (form.authors.length === 0) form.authors = [''];
    if (form.author_emails.length === 0) form.author_emails = [''];
    if (form.author_institutions.length === 0) form.author_institutions = [''];
    if (form.author_points.length === 0) form.author_points = [0];
    if (form.authorization_files.length === 0) form.authorization_files = [null];
    setEqualAuthorShares();
};

const setEqualAuthorShares = () => {
    const indices = form.authors.map((name, index) => name.trim() ? index : -1).filter((index) => index >= 0);
    if (indices.length === 0 && form.authors.length > 0) indices.push(0);
    const count = Math.max(1, indices.length);
    const totalCents = Math.round(configuredPoints.value * 100);
    const baseCents = Math.floor(totalCents / count);
    let allocatedCents = 0;
    form.author_points = form.authors.map((_, index) => {
        const position = indices.indexOf(index);
        if (position < 0) return 0;
        const cents = position === count - 1 ? totalCents - allocatedCents : baseCents;
        allocatedCents += cents;
        return cents / 100;
    });
};

watch(() => form.authors.filter((name) => name.trim()).length, setEqualAuthorShares);

const matchFacultyEmail = (index: number) => {
    const match = props.facultyDirectory.find((faculty) => faculty.email.toLowerCase() === form.author_emails[index]?.trim().toLowerCase());
    if (match) {
        form.authors[index] = match.name;
        form.author_institutions[index] = 'Leyte Normal University';
    }
};

const matchFacultyName = (index: number) => {
    const matchingFaculty = props.facultyDirectory.filter((faculty) => faculty.name.toLowerCase() === form.authors[index]?.trim().toLowerCase());
    if (matchingFaculty.length === 1) {
        form.author_emails[index] = matchingFaculty[0].email;
        form.author_institutions[index] = 'Leyte Normal University';
    }
};

const setAuthorizationFile = (event: Event, index: number) => {
    const input = event.target as HTMLInputElement;
    form.authorization_files[index] = input.files?.[0] ?? null;
};

const onFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const known = new Set(form.attachments.map((file) => `${file.name}:${file.size}`));
    form.attachments = [...form.attachments, ...Array.from(input.files ?? []).filter((file) => !known.has(`${file.name}:${file.size}`))];
    input.value = '';
};

const removeSavedAttachment = (attachment: { id: number; original_name: string }) => {
    if (confirm(`Remove ${attachment.original_name}?`)) {
        router.delete(route('submission-attachments.destroy', attachment.id), { preserveScroll: true });
    }
};

const save = (submitNow: boolean) => {
    form.submit_now = submitNow;
    form.transform((data) => {
        const authorDetails = data.authors.map((name, index) => ({
            name: name.trim(),
            email: data.author_emails[index]?.trim() || '',
            institution: data.author_institutions[index]?.trim() || '',
            allocated_points: data.author_points[index] ?? 0,
            authorization_file: data.authorization_files[index] ?? null,
        })).filter((author) => author.name).map((author) => ({
            ...author,
            email: author.email || (author.name.toLowerCase() === props.currentFaculty.name.toLowerCase() ? props.currentFaculty.email : ''),
        }));

        return {
            ...data,
            authors: authorDetails.map((author) => author.name),
            author_details: authorDetails,
            claimed_points: configuredPoints.value,
            _method: 'put',
        };
    }).post(route('faculty.submissions.update', props.submission.uuid), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Edit Submission" />

    <AuthenticatedLayout>
        <PageHeader
            title="Edit research involvement"
            :description="`Reference ${submission.reference_code}`"
        />

        <form class="ori-card space-y-6 p-6" @submit.prevent="save(false)">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="ori-label">{{ titleLabel }}</label>
                    <input v-model="form.title" type="text" class="ori-input" required />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Research type</label>
                    <select v-model="form.research_type" class="ori-input" required @change="setEqualAuthorShares">
                        <optgroup v-for="group in groupedResearchTypes" :key="group.label" :label="group.label">
                            <option v-for="type in group.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="ori-label">Academic year</label>
                    <select v-model="form.academic_year_id" class="ori-input" required>
                        <option v-for="year in academicYears" :key="year.id" :value="year.id">
                            {{ year.name }}
                        </option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="ori-label">Abstract / summary</label>
                    <textarea v-model="form.abstract" rows="4" class="ori-input" />
                </div>
                <fieldset class="md:col-span-2 border-y border-ori-blue-100 py-4"><legend class="ori-label">Sustainable Development Goals</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><label v-for="sdg in sdgs" :key="sdg.id" class="flex cursor-pointer items-center gap-3 border border-ori-blue-100 p-2 text-sm text-ori-blue-800 hover:bg-ori-blue-50"><input v-model="form.sdgs" type="checkbox" :value="sdg.id" class="rounded border-ori-blue-300 text-ori-blue-700" /><img v-if="sdg.image_url" :src="sdg.image_url" :alt="`SDG ${sdg.number}: ${sdg.name}`" class="h-12 w-12 shrink-0 rounded object-cover" /><span v-else class="h-12 w-12 shrink-0 rounded bg-ori-blue-50" aria-hidden="true"></span><span><strong>SDG {{ sdg.number }}</strong><span class="block">{{ sdg.name }}</span></span></label></div><InputError :message="form.errors.sdgs" class="mt-2" /></fieldset>
                <fieldset class="md:col-span-2 border-b border-ori-blue-100 py-4">
                    <legend class="ori-label">Institutional research agendas</legend>
                    <div v-if="researchAgendas.length" class="grid gap-2 sm:grid-cols-2">
                        <div v-for="agenda in researchAgendas" :key="agenda.id" class="min-w-0 border border-ori-blue-100 p-3 text-sm text-ori-blue-800 hover:bg-ori-blue-50">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input v-model="form.research_agendas" type="checkbox" :value="agenda.id" class="mt-1 rounded border-ori-blue-300 text-ori-blue-700" />
                                <img v-if="agenda.image_url" :src="agenda.image_url" :alt="`${agenda.name} image`" class="h-12 w-12 shrink-0 rounded object-cover" />
                                <span v-else class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded border border-dashed border-ori-blue-200 bg-white text-center text-[8px] leading-tight text-ori-blue-400"><span aria-hidden="true">No</span><span>image</span></span>
                                <strong class="min-w-0">{{ agenda.name }}</strong>
                            </label>
                            <AgendaContextDisclosure v-if="agenda.description" :agenda-id="agenda.id" :description="agenda.description" />
                        </div>
                    </div>
                    <p v-else class="text-sm text-amber-700">No active research agendas are configured. Contact an administrator.</p>
                    <InputError :message="form.errors.research_agendas" class="mt-2" />
                </fieldset>
                <p v-if="!involvementHasAuthors" class="md:col-span-2 border-l-2 border-ori-yellow-400 bg-ori-blue-50 px-4 py-3 text-sm text-ori-blue-700">
                    You are credited with {{ configuredPoints.toFixed(2) }} points for your role. Paper authors are not required for this type of involvement.
                </p>
                <div v-else class="md:col-span-2">
                    <div class="mb-3 flex items-center justify-between gap-3"><div><label class="ori-label mb-0">Paper authors and point shares</label><p class="text-xs text-ori-blue-500">Equal shares are recalculated if the author list or research type changes.</p></div><button type="button" class="ori-btn-ghost" @click="addAuthor">Add co-author</button></div>
                    <p v-if="!pointScheduleConfigured" class="mb-3 text-sm text-rose-700">The administrator has not configured points for this research type yet.</p>
                    <div class="space-y-4">
                        <div v-for="(_, index) in form.authors" :key="index" class="grid gap-3 border-l-2 border-ori-yellow-400 ps-4 sm:grid-cols-2">
                            <div class="sm:col-span-2 flex items-center justify-between"><span class="text-xs font-semibold uppercase text-ori-blue-500">Author {{ index + 1 }}{{ index === 0 ? ' · Lead author' : '' }}</span><button v-if="form.authors.length > 1" type="button" class="text-xs font-semibold text-rose-700" @click="removeAuthor(index)">Remove</button></div>
                            <input v-model="form.authors[index]" type="text" class="ori-input" placeholder="Full author name" required @change="matchFacultyName(index)" />
                            <input v-model="form.author_emails[index]" type="email" list="faculty-email-options" class="ori-input" placeholder="Faculty email (links account)" @change="matchFacultyEmail(index)" />
                            <input v-model="form.author_institutions[index]" type="text" class="ori-input" placeholder="Institution (e.g. Leyte Normal University)" />
                            <label><span class="ori-label">Allocated points</span><input v-model.number="form.author_points[index]" type="number" min="0" step="0.01" class="ori-input" /></label>
                            <label v-if="form.author_points[index] < configuredPoints / authorCount - 0.02" class="sm:col-span-2"><span class="ori-label">Authorization proof for reduced share</span><input type="file" accept=".pdf,.jpg,.jpeg,.png" class="ori-input" @change="setAuthorizationFile($event, index)" /><LocalFilePreview :file="form.authorization_files[index]" class="mt-1 block" /><span v-if="existingAuthors[index]?.authorization_original_name" class="mt-1 block text-xs text-emerald-700">Existing proof: {{ existingAuthors[index].authorization_original_name }} <AuthorizationProofLink v-if="(existingAuthors[index] as any).id" :author="(existingAuthors[index] as any)" class="ml-2 text-xs" /></span></label>
                        </div>
                    </div>
                    <p class="mt-3 text-right text-sm" :class="allocationsMatch ? 'text-emerald-700' : 'text-rose-700'">Allocated {{ allocatedPointsTotal.toFixed(2) }} / {{ configuredPoints.toFixed(2) }} points</p>
                    <datalist id="faculty-email-options"><option v-for="faculty in facultyDirectory" :key="faculty.id" :value="faculty.email">{{ faculty.name }}</option></datalist>
                    <InputError :message="form.errors.authors || authorDetailsError" class="mt-2" />
                </div>
                <div>
                    <label class="ori-label">Venue</label>
                    <input v-model="form.venue" type="text" class="ori-input" />
                </div>
                <div>
                    <label class="ori-label">Publisher</label>
                    <input v-model="form.publisher" type="text" class="ori-input" />
                </div>
                <div>
                    <label class="ori-label">DOI</label>
                    <input v-model="form.doi" type="text" class="ori-input" />
                </div>
                <div>
                    <label class="ori-label">ISSN / ISBN</label>
                    <input v-model="form.issn_isbn" type="text" class="ori-input" />
                </div>
                <div>
                    <label class="ori-label">Indexing</label>
                    <input v-model="form.indexing" type="text" class="ori-input" />
                </div>
                <div>
                    <label class="ori-label">Publication date</label>
                    <input v-model="form.publication_date" type="date" class="ori-input" />
                </div>
                <div>
                    <label class="ori-label">Total points for this research type</label>
                    <input :value="configuredPoints.toFixed(2)" type="text" readonly class="ori-input bg-ori-blue-50 tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="ori-label">Remarks</label>
                    <textarea v-model="form.faculty_remarks" rows="3" class="ori-input" />
                </div>
                <div class="md:col-span-2">
                    <label class="ori-label" for="evidence-files">Attachments</label>
                    <div v-if="submission.attachments?.length" class="mb-4">
                        <AttachmentViewer :attachments="submission.attachments" title="Saved attachments" />
                        <ul class="mt-3 divide-y divide-ori-blue-100 border-y border-ori-blue-100 text-sm">
                            <li v-for="file in submission.attachments" :key="file.id" class="flex items-center justify-between gap-3 py-2">
                                <span class="min-w-0 break-words">{{ file.original_name }}</span>
                                <button type="button" class="text-xs font-semibold text-rose-700" @click="removeSavedAttachment(file)">Remove</button>
                            </li>
                        </ul>
                    </div>
                    <input id="evidence-files" type="file" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="ori-input" @change="onFiles" />
                    <p class="mt-1 text-xs text-ori-blue-500">Add more files (up to 20 per save). PDF, DOC, DOCX, JPG, PNG, or WebP up to 10MB each.</p>
                    <ul v-if="form.attachments.length" class="mt-3 divide-y divide-ori-blue-100 border-y border-ori-blue-100 text-sm">
                        <li v-for="(file, index) in form.attachments" :key="`${file.name}-${file.size}`" class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 break-words">{{ file.name }} <span class="text-xs text-ori-blue-400">{{ (file.size / 1048576).toFixed(2) }} MB</span></span>
                            <span class="flex items-center gap-4"><LocalFilePreview :file="file" /><button type="button" class="text-xs font-semibold text-rose-700" @click="form.attachments.splice(index, 1)">Remove</button></span>
                        </li>
                    </ul>
                    <InputError :message="form.errors.attachments" class="mt-1" />
                    <p v-if="uploadProblem" class="mt-2 text-sm text-rose-700" role="alert">{{ uploadProblem }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-ori-blue-100 pt-5">
                <button type="submit" class="ori-btn-ghost" :disabled="form.processing || !allocationsMatch || !pointScheduleConfigured || !!uploadProblem">Save changes</button>
                <button type="button" class="ori-btn-accent" :disabled="form.processing || !allocationsMatch || !pointScheduleConfigured || !!uploadProblem" @click="save(true)">
                    Save & submit
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
