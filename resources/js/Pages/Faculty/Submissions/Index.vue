<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import Pagination from '@/Components/ORI/Pagination.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    submissions: any;
    filters: { status?: string; search?: string };
    statuses: Array<{ value: string; label: string }>;
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

watch([search, status], () => {
    router.get(
        route('faculty.submissions.index'),
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="My Submissions" />

    <AuthenticatedLayout>
        <PageHeader
            title="Research involvement"
            description="Create, revise, and track your research submissions through evaluation and approval."
        >
            <template #actions>
                <Link :href="route('faculty.submissions.create')" class="ori-btn-accent">
                    Submit research involvement
                </Link>
            </template>
        </PageHeader>

        <div class="ori-card p-4">
            <div class="grid gap-3 md:grid-cols-3">
                <input v-model="search" type="search" class="ori-input md:col-span-2" placeholder="Search title or reference code" />
                <select v-model="status" class="ori-input">
                    <option value="">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>
        </div>

        <div class="ori-card mt-6 overflow-hidden">
            <table class="min-w-full divide-y divide-ori-blue-100 text-left text-sm">
                <thead class="bg-ori-blue-50/80 text-ori-blue-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Reference</th>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">Academic Year</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ori-blue-50">
                    <tr v-for="item in submissions.data" :key="item.id" class="hover:bg-ori-blue-50/40">
                        <td class="px-4 py-3 font-medium text-ori-blue-800">{{ item.reference_code }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-ori-blue-900">{{ item.title }}</div>
                            <div class="text-xs text-ori-blue-500">{{ item.research_type_label }}</div>
                            <div v-if="item.faculty_id !== $page.props.auth.user.id" class="mt-1 text-xs font-semibold text-emerald-700">Shared with you as a co-author</div>
                        </td>
                        <td class="px-4 py-3 text-ori-blue-700">{{ item.academic_year?.name }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="item.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('faculty.submissions.show', item.uuid)" class="font-semibold text-ori-blue-700">
                                Open
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="submissions.data.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-ori-blue-500">
                            No submissions found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="submissions.links" />
    </AuthenticatedLayout>
</template>
