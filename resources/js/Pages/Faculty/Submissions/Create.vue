<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import AgendaContextDisclosure from '@/Components/ORI/AgendaContextDisclosure.vue';
import LocalFilePreview from '@/Components/ORI/LocalFilePreview.vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    uploadLimits: { file: number; total: number };
    academicYears: Array<{ id: number; name: string; is_current: boolean }>;
    researchTypes: Array<{ value: string; label: string; group?: string; has_authors?: boolean }>;
    researchTypePoints: Record<string, number | string>;
    sdgs: Array<{ id: number; number: number; name: string; image_url: string | null }>;
    researchAgendas: Array<{ id: number; name: string; description: string | null; image_url: string | null }>;
    currentFaculty: { id: number; name: string; email: string; institutional_profile_url: string | null };
    facultyDirectory: Array<{ id: number; name: string; email: string; college: { name: string } | null; department: { name: string } | null }>;
}>();

const groupedResearchTypes = computed(() => {
    const groups = new Map<string, typeof props.researchTypes>();
    props.researchTypes.forEach((type) => groups.set(type.group ?? 'Other', [...(groups.get(type.group ?? 'Other') ?? []), type]));
    return [...groups].map(([label, types]) => ({ label, types }));
});

const currentYear = props.academicYears.find((y) => y.is_current)?.id || props.academicYears[0]?.id || '';

const form = useForm({
    title: '',
    research_type: props.researchTypes[0]?.value || '',
    academic_year_id: currentYear,
    abstract: '',
    authors: [''],
    sdgs: [] as number[],
    research_agendas: props.researchAgendas.length ? [props.researchAgendas[0].id] : [] as number[],
    author_emails: [''],
    author_institutions: ['Leyte Normal University'],
    author_points: [0],
    authorization_files: [null as File | null],
    venue: '',
    publisher: '',
    doi: '',
    issn_isbn: '',
    indexing: '',
    publication_date: '',
    claimed_points: '',
    faculty_remarks: '',
    attachments: [] as File[],
    submit_now: false,
});

const extractingPaper = ref(false);
const extractionMessage = ref('');
const savingAction = ref<'draft' | 'submit' | null>(null);
const authorCount = computed(() => Math.max(1, form.authors.filter((author) => author.trim()).length));
const configuredPoints = computed(() => Number(props.researchTypePoints[form.research_type] ?? 0));
const pointScheduleConfigured = computed(() => Object.prototype.hasOwnProperty.call(props.researchTypePoints, form.research_type));
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
const allocatedPointsTotal = computed(() => form.author_points.reduce((total, points) => total + Number(points || 0), 0));
const allocationsMatch = computed(() => !involvementHasAuthors.value || Math.abs(allocatedPointsTotal.value - configuredPoints.value) <= 0.01);
const authorDetailsError = computed(() => (form.errors as Record<string, string>).author_details);
const validationErrors = computed(() => Object.entries(form.errors).map(([field, message]) => ({
    field: formatValidationField(field),
    message,
})));
let extractionRequestId = 0;

function formatValidationField(field: string): string {
    const authorField = field.match(/^author_details\.(\d+)\.(name|allocated_points|authorization_file)$/);
    if (authorField) {
        const fieldLabel = {
            name: 'name',
            allocated_points: 'allocated points',
            authorization_file: 'authorization proof',
        }[authorField[2]];
        return `Author ${Number(authorField[1]) + 1} ${fieldLabel}`;
    }

    const attachmentField = field.match(/^attachments\.(\d+)$/);
    if (attachmentField) return `Evidence attachment ${Number(attachmentField[1]) + 1}`;

    return ({
        title: 'Research title',
        research_type: 'Research type',
        academic_year_id: 'Academic year',
        authors: 'Authors',
        author_details: 'Authors and point shares',
        attachments: 'Evidence attachments',
    } as Record<string, string>)[field] ?? field.replaceAll('_', ' ');
}

const setEqualAuthorShares = () => {
    const populatedIndices = form.authors
        .map((author, index) => author.trim() ? index : -1)
        .filter((index) => index >= 0);
    if (populatedIndices.length === 0 && form.authors.length > 0) populatedIndices.push(0);
    const count = Math.max(1, populatedIndices.length);
    const totalCents = Math.round(configuredPoints.value * 100);
    const baseCents = Math.floor(totalCents / count);
    let allocatedCents = 0;

    form.author_points = form.authors.map((_, index) => {
        const position = populatedIndices.indexOf(index);
        if (position < 0) return 0;
        const cents = position === count - 1 ? totalCents - allocatedCents : baseCents;
        allocatedCents += cents;
        return cents / 100;
    });
};

const removeAuthor = (index: number) => {
    form.authors.splice(index, 1);
    form.author_emails.splice(index, 1);
    form.author_institutions.splice(index, 1);
    form.authorization_files.splice(index, 1);
    if (form.authors.length === 0) form.authors = [''];
    while (form.author_emails.length < form.authors.length) form.author_emails.push('');
    while (form.author_institutions.length < form.authors.length) form.author_institutions.push('');
    while (form.authorization_files.length < form.authors.length) form.authorization_files.push(null);
    setEqualAuthorShares();
};

const addAuthor = () => {
    if (form.authors.length === 1 && !form.authors[0].trim()) return;
    form.authors.push('');
    form.author_emails.push('');
    form.author_institutions.push('Leyte Normal University');
    form.authorization_files.push(null);
    setEqualAuthorShares();
};

const setAuthorizationFile = (event: Event, index: number) => {
    const input = event.target as HTMLInputElement;
    form.authorization_files[index] = input.files?.[0] ?? null;
};

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

setEqualAuthorShares();
watch(() => form.authors.filter((author) => author.trim()).length, setEqualAuthorShares);

const onFiles = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const picked = input.files ? Array.from(input.files) : [];
    const known = new Set(form.attachments.map((file) => `${file.name}:${file.size}`));
    form.attachments = [...form.attachments, ...picked.filter((file) => !known.has(`${file.name}:${file.size}`))];
    input.value = '';

    const requestId = ++extractionRequestId;
    const paper = picked.find((file) => /\.(pdf|docx|png|jpe?g|webp)$/i.test(file.name));
    if (!paper) {
        extractionMessage.value = form.attachments.length
            ? 'Metadata extraction supports PDF, DOCX, PNG, JPG, and WebP papers. The files can still be attached as evidence.'
            : '';
        extractingPaper.value = false;
        return;
    }

    extractingPaper.value = true;
    extractionMessage.value = 'Reading the paper locally. You can continue reviewing the form while it runs.';

    const upload = new FormData();
    upload.append('paper', paper);

    try {
        const { data } = await axios.post(route('faculty.submissions.extract-metadata'), upload);
        if (requestId !== extractionRequestId) return;

        const fields = data.fields as Record<string, string | string[]>;
        if (!form.title.trim() && typeof fields.title === 'string') form.title = fields.title;
        if (!form.abstract.trim() && typeof fields.abstract === 'string') form.abstract = fields.abstract;
        if (!form.doi.trim() && typeof fields.doi === 'string') form.doi = fields.doi;
        if (!form.publication_date && typeof fields.publication_date === 'string') form.publication_date = fields.publication_date;
        if (form.authors.every((author) => !author.trim()) && Array.isArray(fields.authors)) {
            form.authors = fields.authors;
            form.author_emails = fields.authors.map((name) => {
                const matches = props.facultyDirectory.filter((faculty) => faculty.name.toLowerCase() === name.trim().toLowerCase());
                return matches.length === 1 ? matches[0].email : '';
            });
            form.author_institutions = fields.authors.map((name) => {
                const matches = props.facultyDirectory.filter((faculty) => faculty.name.toLowerCase() === name.trim().toLowerCase());
                return matches.length === 1 ? 'Leyte Normal University' : '';
            });
            form.authorization_files = fields.authors.map(() => null);
            setEqualAuthorShares();
        }

        const count = (data.extracted_fields as string[]).length;
        extractionMessage.value = count
            ? `${count} field${count === 1 ? '' : 's'} suggested from the paper. Please verify each value before submitting.`
            : 'No reliable details were detected. You can still fill the fields manually and attach the paper.';
    } catch (error) {
        if (requestId !== extractionRequestId) return;
        extractionMessage.value = axios.isAxiosError(error)
            ? error.response?.data?.message ?? 'Paper text could not be extracted. You can still fill the fields manually.'
            : 'Paper text could not be extracted. You can still fill the fields manually.';
    } finally {
        if (requestId === extractionRequestId) extractingPaper.value = false;
    }
};

const save = (submitNow: boolean) => {
    savingAction.value = submitNow ? 'submit' : 'draft';
    form.submit_now = submitNow;
    form.transform((data) => {
        const authorDetails = data.authors
            .map((name, index) => ({
                name: name.trim(),
                email: data.author_emails[index]?.trim() || '',
                institution: data.author_institutions[index]?.trim() || '',
                allocated_points: data.author_points[index] ?? 0,
                authorization_file: data.authorization_files[index] ?? null,
            }))
            .filter((author) => author.name)
            .map((author) => ({
                ...author,
                email: author.email || (author.name.toLowerCase() === props.currentFaculty.name.toLowerCase() ? props.currentFaculty.email : ''),
            }));

        return {
            ...data,
            authors: authorDetails.map((author) => author.name),
            author_details: authorDetails,
            claimed_points: configuredPoints.value,
        };
    }).post(route('faculty.submissions.store'), {
        forceFormData: true,
        onSuccess: () => { savingAction.value = null; },
        onError: () => { savingAction.value = null; },
    });
};

</script>

<template>
    <Head title="New Submission" />

    <AuthenticatedLayout>
        <PageHeader
            title="New research involvement"
            description="Capture your research output details and supporting evidence for ORI evaluation."
        />

        <form class="ori-card space-y-6 p-6" @submit.prevent="save(false)">
            <div class="flex items-center gap-2 text-xs text-ori-blue-500"><span aria-hidden="true" class="font-bold text-rose-600">*</span><span>Required field</span></div>
            <div v-if="validationErrors.length" role="alert" aria-live="assertive" class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">We couldn’t save this submission yet. Please review:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li v-for="(error, index) in validationErrors" :key="`${error.field}-${index}`"><strong>{{ error.field }}:</strong> {{ error.message }}</li>
                </ul>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="ori-label">{{ titleLabel }} <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></label>
                    <input v-model="form.title" type="text" class="ori-input" required />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <div>
                    <label class="ori-label">Research type <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></label>
                    <select v-model="form.research_type" class="ori-input" required @change="setEqualAuthorShares">
                        <optgroup v-for="group in groupedResearchTypes" :key="group.label" :label="group.label">
                            <option v-for="type in group.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </optgroup>
                    </select>
                    <InputError :message="form.errors.research_type" class="mt-1" />
                </div>

                <div>
                    <label class="ori-label">Academic year <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></label>
                    <select v-model="form.academic_year_id" class="ori-input" required>
                        <option v-for="year in academicYears" :key="year.id" :value="year.id">
                            {{ year.name }}{{ year.is_current ? ' (Current)' : '' }}
                        </option>
                    </select>
                    <InputError :message="form.errors.academic_year_id" class="mt-1" />
                </div>

                <div class="md:col-span-2">
                    <label class="ori-label">Abstract / summary</label>
                    <textarea v-model="form.abstract" rows="4" class="ori-input" />
                </div>

                <fieldset class="md:col-span-2 border-y border-ori-blue-100 py-4">
                    <legend class="ori-label">Sustainable Development Goals</legend>
                    <p class="mb-3 text-xs text-ori-blue-500">Select the goals this research directly supports.</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="sdg in sdgs" :key="sdg.id" class="flex cursor-pointer items-center gap-3 border border-ori-blue-100 p-2 text-sm text-ori-blue-800 hover:bg-ori-blue-50"><input v-model="form.sdgs" type="checkbox" :value="sdg.id" class="rounded border-ori-blue-300 text-ori-blue-700" /><img v-if="sdg.image_url" :src="sdg.image_url" :alt="`SDG ${sdg.number}: ${sdg.name}`" class="h-12 w-12 shrink-0 rounded object-cover" /><span v-else class="h-12 w-12 shrink-0 rounded bg-ori-blue-50" aria-hidden="true"></span><span><strong>SDG {{ sdg.number }}</strong><span class="block">{{ sdg.name }}</span></span></label>
                    </div>
                    <p class="mt-2 text-xs text-ori-blue-500">Selection is optional; choose goals directly relevant to this research.</p>
                    <InputError :message="form.errors.sdgs" class="mt-2" />
                </fieldset>
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
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <label class="ori-label mb-0">Paper authors and point shares</label>
                            <p class="text-xs text-ori-blue-500">The administrator-set {{ configuredPoints.toFixed(2) }} point total is divided equally by default.</p>
                        </div>
                        <button type="button" class="ori-btn-ghost" @click="addAuthor">Add co-author</button>
                    </div>
                    <div class="space-y-4">
                        <div v-for="(_, index) in form.authors" :key="index" class="grid gap-3 border-l-2 border-ori-yellow-400 ps-4 sm:grid-cols-2">
                            <div class="sm:col-span-2 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase text-ori-blue-500">Author {{ index + 1 }}{{ index === 0 ? ' · Lead author' : '' }}</span>
                                <button v-if="form.authors.length > 1 || form.authors[0]" type="button" class="text-xs font-semibold text-rose-700" @click="removeAuthor(index)">Remove</button>
                            </div>
                            <label class="block"><span class="ori-label">Full author name <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></span><input v-model="form.authors[index]" type="text" class="ori-input" placeholder="Full author name" required @change="matchFacultyName(index)" /></label>
                            <input v-model="form.author_emails[index]" type="email" list="faculty-email-options" class="ori-input" placeholder="Faculty email (links account)" @change="matchFacultyEmail(index)" />
                            <input v-model="form.author_institutions[index]" type="text" class="ori-input" placeholder="Institution (e.g. Leyte Normal University)" />
                            <label class="block">
                                <span class="ori-label">Allocated points <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></span>
                                <input v-model.number="form.author_points[index]" type="number" min="0" step="0.01" class="ori-input" required />
                            </label>
                            <label v-if="form.author_points[index] < configuredPoints / authorCount - 0.02" class="block sm:col-span-2">
                                <span class="ori-label">Authorization proof for reduced share <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></span>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="ori-input" required @change="setAuthorizationFile($event, index)" />
                                <LocalFilePreview :file="form.authorization_files[index]" class="mt-1 inline-block" />
                                <span class="mt-1 block text-xs text-ori-blue-500">Required when this author gives part of their equal share to another author.</span>
                            </label>
                        </div>
                    </div>
                    <p v-if="!pointScheduleConfigured" class="mt-3 text-sm text-rose-700">The administrator has not configured points for this research type yet.</p>
                    <p class="mt-3 text-right text-sm" :class="allocationsMatch ? 'text-emerald-700' : 'text-rose-700'">Allocated {{ allocatedPointsTotal.toFixed(2) }} / {{ configuredPoints.toFixed(2) }} points</p>
                    <datalist id="faculty-email-options"><option v-for="faculty in facultyDirectory" :key="faculty.id" :value="faculty.email">{{ faculty.name }}</option></datalist>
                    <InputError :message="form.errors.authors || authorDetailsError" class="mt-2" />
                </div>

                <div>
                    <label class="ori-label">Venue / Journal / Conference</label>
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
                    <input v-model="form.indexing" type="text" class="ori-input" placeholder="Scopus, WoS, CHED, etc." />
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
                    <label class="ori-label" for="evidence-files">Evidence attachments</label>
                    <input id="evidence-files" type="file" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="ori-input" @change="onFiles" />
                    <p class="mt-1 text-xs text-ori-blue-500">Add as many files as needed (up to 20), selecting more than once if you wish. PDF, DOC, DOCX, JPG, PNG, or WebP up to 10MB each. The first supported paper is analyzed locally for suggested details.</p>
                    <ul v-if="form.attachments.length" class="mt-3 divide-y divide-ori-blue-100 border-y border-ori-blue-100 text-sm">
                        <li v-for="(file, index) in form.attachments" :key="`${file.name}-${file.size}`" class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 break-words">{{ file.name }} <span class="text-xs text-ori-blue-400">{{ (file.size / 1048576).toFixed(2) }} MB</span></span>
                            <span class="flex items-center gap-4"><LocalFilePreview :file="file" /><button type="button" class="text-xs font-semibold text-rose-700" @click="form.attachments.splice(index, 1)">Remove</button></span>
                        </li>
                    </ul>
                    <InputError :message="form.errors.attachments" class="mt-1" />
                    <p v-if="uploadProblem" class="mt-2 text-sm text-rose-700" role="alert">{{ uploadProblem }}</p>
                    <p v-if="extractionMessage" class="mt-2 text-sm" :class="extractingPaper ? 'text-ori-blue-600' : 'text-emerald-800'" role="status" aria-live="polite">
                        <span v-if="extractingPaper" class="mr-1 inline-block h-3 w-3 animate-spin rounded-full border-2 border-ori-blue-300 border-t-ori-blue-800 align-[-2px]" />
                        {{ extractionMessage }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-ori-blue-100 pt-5">
                <button type="submit" class="ori-btn-ghost" :disabled="form.processing || extractingPaper || !allocationsMatch || !pointScheduleConfigured || !!uploadProblem">{{ savingAction === 'draft' ? 'Saving draft…' : 'Save as draft' }}</button>
                <button type="button" class="ori-btn-accent" :disabled="form.processing || extractingPaper || !allocationsMatch || !pointScheduleConfigured || !!uploadProblem" @click="save(true)">
                    {{ savingAction === 'submit' ? 'Submitting…' : 'Submit for evaluation' }}
                </button>
            </div>
            <p class="text-xs text-ori-blue-500">College and university rankings are calculated automatically from approved research points and are available on coordinator and admin dashboards.</p>
        </form>
    </AuthenticatedLayout>
</template>
