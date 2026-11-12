<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import AttachmentViewer from '@/Components/ORI/AttachmentViewer.vue';
import AuthorizationProofLink from '@/Components/ORI/AuthorizationProofLink.vue';
import EvidenceHistory from '@/Components/ORI/EvidenceHistory.vue';
import ClaimCalculation from '@/Components/ORI/ClaimCalculation.vue';
import ActivityDateDisplay from '@/Components/ORI/ActivityDateDisplay.vue';
import { formatFundingSource } from '@/Utils/fundingSource';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    submission: any;
    recommendations: Array<{ value: string; label: string }>;
    canEvaluate: boolean;
}>();

type SdgItem = { number: number; name: string; image_url: string | null };
type AgendaItem = { name: string; image_url: string | null };
const submission = props.submission as typeof props.submission & { sdgs?: SdgItem[]; research_agendas?: AgendaItem[]; research_type_label?: string };

function formatSdgs(items?: SdgItem[]): string {
    return items?.map((item) => `SDG ${item.number}: ${item.name}`).join(' · ') ?? '';
}

function formatAgendas(items?: AgendaItem[]): string {
    return items?.map((item) => item.name).join(' · ') ?? '';
}

const previousEvaluation = props.submission.evaluation;
const form = useForm({
    score: previousEvaluation?.score != null ? String(previousEvaluation.score) : '',
    quality_rating: previousEvaluation?.quality_rating ?? '' as number | '',
    relevance_rating: previousEvaluation?.relevance_rating ?? '' as number | '',
    documentation_rating: previousEvaluation?.documentation_rating ?? '' as number | '',
    recommendation: previousEvaluation?.recommendation || props.recommendations[0]?.value || 'recommend_approval',
    awarded_points: previousEvaluation?.awarded_points ?? props.submission.claimed_points ?? '',
    remarks: previousEvaluation?.remarks ?? '',
    review_confirmed: false,
});

const useSuggestedScore = ref(previousEvaluation?.score == null);
const latestSubmissionHistory = computed(() => (submission.status_histories ?? [])
    .filter((history: any) => history.action === 'submitted')
    .sort((first: any, second: any) => second.id - first.id)[0]);
const evidenceSnapshot = computed(() => latestSubmissionHistory.value?.meta?.evidence_requirements);
const screeningFields = computed(() => [
    { label: 'Title', present: Boolean(submission.title?.trim()) },
    { label: 'Abstract', present: Boolean(submission.abstract?.trim()) },
    { label: 'Venue', present: Boolean(submission.venue?.trim()) },
    { label: 'Indexing statement', present: Boolean(submission.indexing?.trim()) },
    { label: 'Current evidence files', present: Boolean(submission.attachments?.length) },
]);

const suggestedScore = computed(() => {
    const ratings = [form.quality_rating, form.relevance_rating, form.documentation_rating];
    if (ratings.some((rating) => typeof rating !== 'number' || !Number.isInteger(rating) || rating < 1 || rating > 5)) return null;
    return Math.round((ratings as number[]).reduce((total, rating) => total + rating, 0) / 15 * 10000) / 100;
});

watch(suggestedScore, (score) => {
    if (useSuggestedScore.value) form.score = score === null ? '' : String(score);
}, { immediate: true });

watch(() => [form.score, form.quality_rating, form.relevance_rating, form.documentation_rating, form.recommendation, form.awarded_points, form.remarks], () => {
    form.review_confirmed = false;
});

function resetSuggestedScore() {
    useSuggestedScore.value = true;
    form.score = suggestedScore.value === null ? '' : String(suggestedScore.value);
}

const submit = () => {
    form.post(route('coordinator.evaluations.store', props.submission.uuid));
};
</script>

<template>
    <Head :title="`Evaluate ${submission.reference_code}`" />

    <AuthenticatedLayout>
        <PageHeader :title="submission.title" :description="`${submission.reference_code} · ${submission.faculty?.name}`">
            <template #actions>
                <Link :href="route('research-profiles.show', submission.faculty.id)" class="ori-btn-ghost">Faculty research profile</Link>
                <StatusBadge :status="submission.status" />
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-5">
            <section class="ori-card space-y-4 p-6 lg:col-span-3">
                <h2 class="font-display text-xl font-semibold">Submission dossier</h2>
                <ClaimCalculation :submission="submission" />
                <ActivityDateDisplay :dates="submission.activity_dates" :legacy="submission.publication_date" :has-authors="submission.has_authors" />
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-ori-blue-400">Funding source</dt>
                        <dd class="break-words font-medium">{{ formatFundingSource(submission.funding_source, submission.funding_organization) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ori-blue-400">College</dt>
                        <dd class="font-medium">{{ submission.college?.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ori-blue-400">Department</dt>
                        <dd class="font-medium">{{ submission.department?.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ori-blue-400">Research type</dt>
                        <dd class="font-medium">{{ submission.research_type_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-ori-blue-400">Venue</dt>
                        <dd class="font-medium">{{ submission.venue || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ori-blue-400">Indexing</dt>
                        <dd class="font-medium">{{ submission.indexing || '—' }}</dd>
                    </div>
                    <div v-if="submission.has_authors !== false" class="sm:col-span-2">
                        <dt class="text-ori-blue-400">Authors</dt>
                        <dd class="font-medium">{{ submission.authors?.join(', ') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-ori-blue-400">Abstract</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-justify text-ori-blue-700">{{ submission.abstract || '—' }}</dd>
                    </div>
                </dl>
                <div v-if="submission.sdgs?.length || submission.research_agendas?.length" class="grid gap-4 border-t border-ori-blue-100 pt-4 sm:grid-cols-2 text-sm"><div v-if="submission.sdgs?.length"><strong>SDGs</strong><div class="mt-2 flex flex-wrap gap-2"><span v-for="item in submission.sdgs" :key="item.number" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1"><img v-if="item.image_url" :src="item.image_url" :alt="`SDG ${item.number}: ${item.name}`" class="h-8 w-8 rounded object-cover" /><span>SDG {{ item.number }} · {{ item.name }}</span></span></div></div><div v-if="submission.research_agendas?.length"><strong>Research agendas</strong><div class="mt-2 flex flex-wrap gap-2"><span v-for="item in submission.research_agendas" :key="item.id" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1"><img v-if="item.image_url" :src="item.image_url" :alt="`${item.name} image`" class="h-8 w-8 rounded object-cover" /><span>{{ item.name }}</span></span></div></div></div>

                <div v-if="submission.authorship_allocations?.length">
                    <h3 class="text-sm font-semibold">{{ submission.has_authors === false ? 'Credited faculty' : 'Author points and authorization' }}</h3>
                    <ul class="mt-2 divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                        <li v-for="author in submission.authorship_allocations" :key="author.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span>{{ author.name }}<span v-if="author.institution" class="text-ori-blue-500"> · {{ author.institution }}</span></span>
                            <span class="flex items-center gap-3"><strong>{{ Number(author.allocated_points).toFixed(2) }} pts</strong><AuthorizationProofLink v-if="author.has_authorization" :author="author" /></span>
                        </li>
                    </ul>
                </div>

                <AttachmentViewer v-if="submission.attachments?.length" :attachments="submission.attachments" title="Evidence files" />
                <EvidenceHistory :histories="submission.status_histories ?? []" />
                <section class="border-t border-ori-blue-100 pt-4 text-sm">
                    <h3 class="font-semibold text-ori-blue-900">Automatic screening: presence only</h3>
                    <p class="mt-1 text-ori-blue-600">These observations are tentative completeness checks, not assessments of quality, relevance, document validity, or indexing. An unprovided field is not necessarily required for this research type. Indexing claims, including Scopus, are not verified.</p>
                    <ul class="mt-3 space-y-1">
                        <li v-for="item in screeningFields" :key="item.label" class="flex justify-between gap-3">
                            <span>{{ item.label }}</span><span class="text-ori-blue-600">{{ item.present ? 'Provided' : 'Not provided' }}</span>
                        </li>
                    </ul>
                    <h4 class="mt-3 font-semibold">Latest submission evidence snapshot</h4>
                    <p v-if="!Array.isArray(evidenceSnapshot)" class="mt-1 text-ori-blue-600">Unknown: no submission evidence snapshot is available.</p>
                    <p v-else-if="!evidenceSnapshot.length" class="mt-1 text-ori-blue-600">No evidence requirements were configured in this snapshot. Completeness is unknown.</p>
                    <ul v-else class="mt-1 space-y-1">
                        <li v-for="requirement in evidenceSnapshot" :key="requirement.id" class="flex justify-between gap-3">
                            <span>{{ requirement.name }} ({{ requirement.is_required ? 'required' : 'optional' }})</span>
                            <span class="text-right text-ori-blue-600">{{ requirement.attachments?.length ? 'File recorded' : 'No file recorded' }}</span>
                        </li>
                    </ul>
                </section>
            </section>

            <section class="ori-card p-6 lg:col-span-2">
                <h2 class="font-display text-xl font-semibold">Evaluation form</h2>
                <form v-if="canEvaluate" class="mt-4 space-y-4" @submit.prevent="submit">
                    <p class="text-sm text-ori-blue-600">Assess each rating yourself. The screening does not assign ratings or verify evidence.</p>
                    <div>
                        <label for="score" class="ori-label">Overall score</label>
                        <input id="score" v-model="form.score" type="number" min="0" max="100" step="0.01" class="ori-input" required @input="useSuggestedScore = false" />
                        <p class="mt-1 text-xs text-ori-blue-600">Suggested score = (quality + relevance + documentation) / 15 × 100. You may override it.</p>
                        <div v-if="suggestedScore !== null" class="mt-1 flex flex-wrap items-center gap-2 text-xs text-ori-blue-600">
                            <span>Suggested: {{ suggestedScore.toFixed(2) }} / 100</span>
                            <button type="button" class="underline" @click="resetSuggestedScore">Use suggested score</button>
                        </div>
                        <InputError :message="form.errors.score" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="quality_rating" class="ori-label">Quality</label>
                            <input id="quality_rating" v-model.number="form.quality_rating" type="number" min="1" max="5" step="1" class="ori-input" required />
                            <InputError :message="form.errors.quality_rating" class="mt-1" />
                        </div>
                        <div>
                            <label for="relevance_rating" class="ori-label">Relevance</label>
                            <input id="relevance_rating" v-model.number="form.relevance_rating" type="number" min="1" max="5" step="1" class="ori-input" required />
                            <InputError :message="form.errors.relevance_rating" class="mt-1" />
                        </div>
                        <div>
                            <label for="documentation_rating" class="ori-label">Docs</label>
                            <input id="documentation_rating" v-model.number="form.documentation_rating" type="number" min="1" max="5" step="1" class="ori-input" required />
                            <InputError :message="form.errors.documentation_rating" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="ori-label">Recommendation</label>
                        <select v-model="form.recommendation" class="ori-input" required>
                            <option v-for="item in recommendations" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.recommendation" class="mt-1" />
                    </div>
                    <div>
                        <label class="ori-label">Awarded points</label>
                        <input v-model="form.awarded_points" type="number" min="0" step="0.01" class="ori-input" />
                        <InputError :message="form.errors.awarded_points" class="mt-1" />
                    </div>
                    <div>
                        <label class="ori-label">Remarks</label>
                        <textarea v-model="form.remarks" rows="5" class="ori-input" required />
                        <InputError :message="form.errors.remarks" class="mt-1" />
                    </div>
                    <div>
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="form.review_confirmed" type="checkbox" class="mt-1" required />
                            <span>I have reviewed the submission and evidence, assessed the ratings and overall score, and confirm this recommendation.</span>
                        </label>
                        <InputError :message="form.errors.review_confirmed" class="mt-1" />
                    </div>
                    <button type="submit" class="ori-btn-accent w-full" :disabled="form.processing">
                        Submit evaluation
                    </button>
                </form>
                <div v-else class="mt-4 rounded-xl bg-ori-blue-50 p-4 text-sm text-ori-blue-700">
                    This submission is no longer open for evaluation.
                    <div v-if="submission.evaluation" class="mt-3">
                        <p class="font-semibold">Latest remarks</p>
                        <p class="mt-1">{{ submission.evaluation.remarks }}</p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
