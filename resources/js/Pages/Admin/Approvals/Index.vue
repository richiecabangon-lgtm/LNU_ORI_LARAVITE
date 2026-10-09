<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import Pagination from '@/Components/ORI/Pagination.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    submissions: any;
    filters: { tab?: string; search?: string };
}>();

const search = ref(props.filters.search || '');
const tab = ref(props.filters.tab || 'pending');

watch([search, tab], () => {
    router.get(
        route('admin.approvals.index'),
        { search: search.value || undefined, tab: tab.value },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="Approvals" />

    <AuthenticatedLayout>
        <PageHeader
            title="Administrative approvals"
            description="Finalize evaluated research involvement after coordinator recommendation."
        />

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold"
                :class="tab === 'pending' ? 'bg-ori-blue-700 text-white' : 'bg-white text-ori-blue-700 ring-1 ring-ori-blue-100'"
                @click="tab = 'pending'"
            >
                Awaiting approval
            </button>
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold"
                :class="tab === 'history' ? 'bg-ori-blue-700 text-white' : 'bg-white text-ori-blue-700 ring-1 ring-ori-blue-100'"
                @click="tab = 'history'"
            >
                Decision history
            </button>
        </div>

        <div class="ori-card p-4">
            <input v-model="search" type="search" class="ori-input" placeholder="Search title or reference code" />
        </div>

        <div class="ori-card mt-6 overflow-hidden">
            <table class="min-w-full divide-y divide-ori-blue-100 text-left text-sm">
                <thead class="bg-ori-blue-50/80 text-ori-blue-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Reference</th>
                        <th class="px-4 py-3 font-semibold">Faculty</th>
                        <th class="px-4 py-3 font-semibold">Coordinator</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ori-blue-50">
                    <tr v-for="item in submissions.data" :key="item.id">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ item.reference_code }}</div>
                            <div class="text-xs text-ori-blue-500">{{ item.title }}</div>
                        </td>
                        <td class="px-4 py-3">{{ item.faculty?.name }}</td>
                        <td class="px-4 py-3">{{ item.evaluation?.coordinator?.name || '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="item.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('admin.approvals.show', item.uuid)" class="font-semibold text-ori-blue-700">
                                Open
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="submissions.data.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-ori-blue-500">No records found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="submissions.links" />
    </AuthenticatedLayout>
</template>
