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
    canApprove: boolean;
}>();

const form = useForm({
    decision: 'approved',
    final_points: props.submission.evaluation?.awarded_points || props.submission.claimed_points || '',
    remarks: '',
});

const submit = () => {
    form.post(route('admin.approvals.store', props.submission.uuid));
};
</script>

<template>
    <Head :title="`Approve ${submission.reference_code}`" />

    <AuthenticatedLayout>
        <PageHeader :title="submission.title" :description="submission.reference_code">
            <template #actions>
                <StatusBadge :status="submission.status" />
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-5">
            <section class="ori-card space-y-5 p-6 lg:col-span-3">
                <div>
                    <h2 class="font-display text-xl font-semibold">Research record</h2>
                    <p class="mt-1 text-sm text-ori-blue-600">
                        Submitted by {{ submission.faculty?.name }} · Evaluated by
                        {{ submission.evaluation?.coordinator?.name || '—' }}
                    </p>
                    <Link :href="route('research-profiles.show', submission.faculty.id)" class="mt-2 inline-flex text-sm font-semibold text-ori-blue-700 underline">Open faculty research profile</Link>
                </div>

                <div class="rounded-2xl bg-ori-yellow-50 p-4 ring-1 ring-ori-yellow-200">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ori-blue-500">
                        Coordinator recommendation
                    </p>
                    <p class="mt-1 font-semibold capitalize text-ori-blue-900">
                        {{ submission.evaluation?.recommendation?.replaceAll('_', ' ') || 'Unavailable' }}
                    </p>
                    <p class="mt-2 text-sm text-ori-blue-700">
                        {{ submission.evaluation?.remarks || 'No evaluation remarks.' }}
                    </p>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-ori-blue-400">Type</dt>
                        <dd class="font-medium">{{ submission.research_type_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-ori-blue-400">Awarded points (eval)</dt>
                        <dd class="font-medium">{{ submission.evaluation?.awarded_points ?? '—' }}</dd>
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

                <div v-if="submission.authorship_allocations?.length">
                    <h3 class="text-sm font-semibold">{{ submission.has_authors === false ? 'Credited faculty' : 'Author points and authorization evidence' }}</h3>
                    <ul class="mt-2 divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                        <li v-for="author in submission.authorship_allocations" :key="author.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span>{{ author.name }}<span v-if="author.institution" class="text-ori-blue-500"> · {{ author.institution }}</span></span>
                            <span class="flex items-center gap-3"><strong>{{ Number(author.allocated_points).toFixed(2) }} pts</strong><AuthorizationProofLink v-if="author.has_authorization" :author="author" /></span>
                        </li>
                    </ul>
                </div>
                <div v-if="submission.sdgs?.length || submission.research_agendas?.length" class="grid gap-4 border-t border-ori-blue-100 pt-4 sm:grid-cols-2 text-sm">
                    <div v-if="submission.sdgs?.length"><strong>SDGs</strong><div class="mt-2 flex flex-wrap gap-2"><span v-for="item in submission.sdgs" :key="item.id" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1"><img v-if="item.image_url" :src="item.image_url" :alt="`SDG ${item.number}: ${item.name}`" class="h-8 w-8 rounded object-cover" /><span>SDG {{ item.number }} · {{ item.name }}</span></span></div></div>
                    <div v-if="submission.research_agendas?.length"><strong>Research agendas</strong><div class="mt-2 flex flex-wrap gap-2"><span v-for="item in submission.research_agendas" :key="item.id" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1"><img v-if="item.image_url" :src="item.image_url" :alt="`${item.name} image`" class="h-8 w-8 rounded object-cover" /><span>{{ item.name }}</span></span></div></div>
                </div>
                <AttachmentViewer v-if="submission.attachments?.length" :attachments="submission.attachments" title="Evidence files" />
            </section>

            <section class="ori-card p-6 lg:col-span-2">
                <h2 class="font-display text-xl font-semibold">Admin decision</h2>
                <form v-if="canApprove" class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="ori-label">Decision</label>
                        <select v-model="form.decision" class="ori-input">
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>
                    <div>
                        <label class="ori-label">Final points</label>
                        <input v-model="form.final_points" type="number" min="0" step="0.01" class="ori-input" />
                    </div>
                    <div>
                        <label class="ori-label">Remarks</label>
                        <textarea v-model="form.remarks" rows="5" class="ori-input" />
                        <InputError :message="form.errors.remarks" class="mt-1" />
                    </div>
                    <button
                        type="submit"
                        class="w-full"
                        :class="form.decision === 'approved' ? 'ori-btn-accent' : 'ori-btn-primary'"
                        :disabled="form.processing"
                    >
                        {{ form.decision === 'approved' ? 'Approve submission' : 'Reject submission' }}
                    </button>
                </form>
                <div v-else class="mt-4 rounded-xl bg-ori-blue-50 p-4 text-sm text-ori-blue-700">
                    This submission is already decided.
                    <div v-if="submission.approval" class="mt-3">
                        <p class="font-semibold capitalize">{{ submission.approval.decision }}</p>
                        <p class="mt-1">{{ submission.approval.remarks || 'No remarks.' }}</p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
