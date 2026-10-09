<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import AttachmentViewer from '@/Components/ORI/AttachmentViewer.vue';
import AuthorizationProofLink from '@/Components/ORI/AuthorizationProofLink.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

const form = useForm({
    score: '',
    quality_rating: 3,
    relevance_rating: 3,
    documentation_rating: 3,
    recommendation: props.recommendations[0]?.value || 'recommend_approval',
    awarded_points: props.submission.claimed_points || '',
    remarks: '',
});

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
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
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
                        <dd class="mt-1 whitespace-pre-wrap text-ori-blue-700">{{ submission.abstract || '—' }}</dd>
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
            </section>

            <section class="ori-card p-6 lg:col-span-2">
                <h2 class="font-display text-xl font-semibold">Evaluation form</h2>
                <form v-if="canEvaluate" class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="ori-label">Overall score</label>
                        <input v-model="form.score" type="number" min="0" max="100" step="0.01" class="ori-input" />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="ori-label">Quality</label>
                            <input v-model="form.quality_rating" type="number" min="1" max="5" class="ori-input" />
                        </div>
                        <div>
                            <label class="ori-label">Relevance</label>
                            <input v-model="form.relevance_rating" type="number" min="1" max="5" class="ori-input" />
                        </div>
                        <div>
                            <label class="ori-label">Docs</label>
                            <input v-model="form.documentation_rating" type="number" min="1" max="5" class="ori-input" />
                        </div>
                    </div>
                    <div>
                        <label class="ori-label">Recommendation</label>
                        <select v-model="form.recommendation" class="ori-input" required>
                            <option v-for="item in recommendations" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="ori-label">Awarded points</label>
                        <input v-model="form.awarded_points" type="number" min="0" step="0.01" class="ori-input" />
                    </div>
                    <div>
                        <label class="ori-label">Remarks</label>
                        <textarea v-model="form.remarks" rows="5" class="ori-input" required />
                        <InputError :message="form.errors.remarks" class="mt-1" />
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
