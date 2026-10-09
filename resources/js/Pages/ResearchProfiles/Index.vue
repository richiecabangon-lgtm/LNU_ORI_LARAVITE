<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Faculty = { id: number; name: string; employee_id: string | null; designation: string | null; email: string; college: { name: string } | null; department: { name: string } | null; submissions_count: number; coauthored_submissions_count: number };
const props = defineProps<{ faculty: { data: Faculty[]; links: Array<{ url: string | null; label: string; active: boolean }> }; filters: { search: string } }>();
const search = ref(props.filters.search);
function applySearch() { router.get(route('research-profiles.index'), { search: search.value }, { preserveState: true, replace: true }); }
</script>

<template>
    <Head title="Faculty Research Profiles" />
    <AuthenticatedLayout>
        <PageHeader title="Faculty research profiles" description="Browse faculty research interests and submitted research outputs.">
            <template #actions><form @submit.prevent="applySearch"><input v-model="search" type="search" class="ori-input min-w-64" placeholder="Search faculty" aria-label="Search faculty" /></form></template>
        </PageHeader>
        <div class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
            <Link v-for="person in faculty.data" :key="person.id" :href="route('research-profiles.show', person.id)" class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_12rem_10rem] sm:items-center hover:bg-white/60">
                <span><span class="block font-semibold text-ori-blue-900">{{ person.name }}</span><span class="text-sm text-ori-blue-500">{{ person.designation || 'Faculty' }}<span v-if="person.employee_id"> · {{ person.employee_id }}</span></span></span>
                <span class="text-sm text-ori-blue-700">{{ person.college?.name || 'College not assigned' }}<br />{{ person.department?.name || 'Department not assigned' }}</span>
                <span class="text-xs text-ori-blue-500">{{ person.submissions_count + person.coauthored_submissions_count }} research records</span>
            </Link>
            <p v-if="faculty.data.length === 0" class="py-12 text-center text-sm text-ori-blue-500">No faculty profiles match this search.</p>
        </div>
        <nav v-if="faculty.links.length > 3" class="mt-5 flex flex-wrap gap-2" aria-label="Profile pages">
            <Link v-for="link in faculty.links.filter((item) => item.url)" :key="link.label" :href="link.url!" :class="['border px-3 py-2 text-sm', link.active ? 'border-ori-blue-700 bg-ori-blue-700 text-white' : 'border-ori-blue-200 text-ori-blue-700']">{{ link.label }}</Link>
        </nav>
    </AuthenticatedLayout>
</template>
