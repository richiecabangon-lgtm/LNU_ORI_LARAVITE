<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import AttachmentViewer from '@/Components/ORI/AttachmentViewer.vue';
import AuthorizationProofLink from '@/Components/ORI/AuthorizationProofLink.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    submission: any;
    can: { update: boolean; submit: boolean; delete: boolean; viewAuthorizations: boolean };
}>();

type SdgItem = { number: number; name: string };
type AgendaItem = { name: string };
const submission = props.submission as typeof props.submission & { sdgs?: SdgItem[]; research_agendas?: AgendaItem[]; research_type_label?: string };

function formatSdgs(items?: SdgItem[]): string {
    return items?.map((item) => `SDG ${item.number}: ${item.name}`).join(' · ') ?? '';
}

function formatAgendas(items?: AgendaItem[]): string {
    return items?.map((item) => item.name).join(' · ') ?? '';
}

const submitNow = () => {
    router.post(route('faculty.submissions.submit', props.submission.uuid));
};

const destroy = () => {
    if (confirm('Delete this draft submission?')) {
        router.delete(route('faculty.submissions.destroy', props.submission.uuid));
    }
};
</script>

<template>
    <Head :title="submission.reference_code" />

    <AuthenticatedLayout>
        <PageHeader :title="submission.title" :description="submission.reference_code">
            <template #actions>
                <StatusBadge :status="submission.status" />
                <Link
                    v-if="can.update"
                    :href="route('faculty.submissions.edit', submission.uuid)"
                    class="ori-btn-ghost"
                >
                    Edit
                </Link>
                <button v-if="can.submit" type="button" class="ori-btn-accent" @click="submitNow">
                    Submit for evaluation
                </button>
                <button v-if="can.delete" type="button" class="ori-btn-ghost text-rose-700" @click="destroy">
                    Delete draft
                </button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="ori-card space-y-4 p-6 lg:col-span-2">
                <div>
                    <h2 class="font-display text-xl font-semibold text-ori-blue-900">Submission details</h2>
                    <p class="mt-1 text-sm capitalize text-ori-blue-500">
                        {{ submission.research_type_label }} · AY {{ submission.academic_year?.name }}
                    </p>
                    <ul class="mt-2 divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                        <li v-for="author in submission.authorship_allocations" :key="author.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span>{{ author.name }}<span v-if="author.institution" class="text-ori-blue-500"> · {{ author.institution }}</span><span v-if="author.faculty" class="text-ori-blue-500"> · LNU faculty</span></span>
                            <span class="flex items-center gap-3"><strong class="tabular-nums">{{ Number(author.allocated_points).toFixed(2) }} pts</strong><AuthorizationProofLink v-if="can.viewAuthorizations && author.has_authorization" :author="author" /></span>
                        </li>
                    </ul>
                </div>
                <div v-if="submission.sdgs?.length || submission.research_agendas?.length" class="grid gap-4 border-t border-ori-blue-100 pt-4 sm:grid-cols-2">
                    <div v-if="submission.sdgs?.length"><h3 class="text-xs font-semibold uppercase text-ori-blue-400">SDGs</h3><div class="mt-2 flex flex-wrap gap-2"><span v-for="sdg in submission.sdgs" :key="sdg.id" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1 text-sm"><img v-if="sdg.image_url" :src="sdg.image_url" :alt="`SDG ${sdg.number}: ${sdg.name}`" class="h-8 w-8 rounded object-cover" /><span>SDG {{ sdg.number }} · {{ sdg.name }}</span></span></div></div>
                    <div v-if="submission.research_agendas?.length"><h3 class="text-xs font-semibold uppercase text-ori-blue-400">Research agendas</h3><div class="mt-2 flex flex-wrap gap-2"><span v-for="agenda in submission.research_agendas" :key="agenda.id" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1 text-sm"><img v-if="agenda.image_url" :src="agenda.image_url" :alt="`${agenda.name} image`" class="h-8 w-8 rounded object-cover" /><span>{{ agenda.name }}</span></span></div></div>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div v-if="submission.has_authors !== false">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-ori-blue-400">Authors</dt>
                        <dd class="mt-1 text-sm text-ori-blue-900">{{ submission.authors?.join(', ') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-ori-blue-400">Venue</dt>
                        <dd class="mt-1 text-sm text-ori-blue-900">{{ submission.venue || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-ori-blue-400">DOI</dt>
                        <dd class="mt-1 text-sm text-ori-blue-900">{{ submission.doi || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-ori-blue-400">Indexing</dt>
                        <dd class="mt-1 text-sm text-ori-blue-900">{{ submission.indexing || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-ori-blue-400">Claimed points</dt>
                        <dd class="mt-1 text-sm text-ori-blue-900">{{ submission.claimed_points ?? '—' }}</dd>
                    </div>
                </dl>

                <div v-if="submission.sdgs?.length || submission.research_agendas?.length" class="grid gap-4 border-t border-ori-blue-100 pt-4 sm:grid-cols-2">
                    <div v-if="submission.sdgs?.length"><h3 class="text-xs font-semibold uppercase text-ori-blue-400">SDGs</h3><p class="mt-1 text-sm text-ori-blue-800">{{ formatSdgs(submission.sdgs) }}</p></div>
                    <div v-if="submission.research_agendas?.length"><h3 class="text-xs font-semibold uppercase text-ori-blue-400">Research agendas</h3><p class="mt-1 text-sm text-ori-blue-800">{{ formatAgendas(submission.research_agendas) }}</p></div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-ori-blue-800">Abstract</h3>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-ori-blue-700">
                        {{ submission.abstract || 'No abstract provided.' }}
                    </p>
                </div>

                <AttachmentViewer v-if="submission.attachments?.length" :attachments="submission.attachments" />
            </section>

            <aside class="space-y-6">
                <section v-if="submission.evaluation" class="ori-card p-5">
                    <h3 class="font-display text-lg font-semibold text-ori-blue-900">Coordinator evaluation</h3>
                    <p class="mt-2 text-sm text-ori-blue-600">
                        {{ submission.evaluation.remarks }}
                    </p>
                    <p class="mt-3 text-xs uppercase tracking-wide text-ori-blue-400">
                        Recommendation: {{ submission.evaluation.recommendation?.replaceAll('_', ' ') }}
                    </p>
                </section>

                <section v-if="submission.approval" class="ori-card p-5">
                    <h3 class="font-display text-lg font-semibold text-ori-blue-900">Admin decision</h3>
                    <p class="mt-2 text-sm capitalize text-ori-blue-800">{{ submission.approval.decision }}</p>
                    <p class="mt-2 text-sm text-ori-blue-600">{{ submission.approval.remarks || 'No remarks.' }}</p>
                </section>

                <section class="ori-card p-5">
                    <h3 class="font-display text-lg font-semibold text-ori-blue-900">Status history</h3>
                    <ol class="mt-4 space-y-3">
                        <li
                            v-for="history in submission.status_histories"
                            :key="history.id"
                            class="border-l-2 border-ori-yellow-400 pl-3"
                        >
                            <p class="text-sm font-semibold capitalize text-ori-blue-900">
                                {{ history.action.replaceAll('_', ' ') }}
                            </p>
                            <p class="text-xs text-ori-blue-500">
                                {{ history.user?.name }} · {{ new Date(history.created_at).toLocaleString() }}
                            </p>
                        </li>
                    </ol>
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
