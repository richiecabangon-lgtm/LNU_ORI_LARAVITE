<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type ResearchItem = { id: number; uuid: string; reference_code: string; title: string; research_type: string; abstract: string | null; venue: string | null; doi: string | null; publication_date: string | null; status: string; faculty_points: number; is_submitter: boolean; college: string | null; department: string | null; sdgs: Array<{ number: number; name: string; image_url: string | null }>; research_agendas: Array<{ name: string; image_url: string | null }> };
const props = defineProps<{
    faculty: { id: number; name: string; email: string; employee_id: string | null; designation: string | null; academic_rank: string | null; profile_photo_url: string | null; orcid_url: string | null; google_scholar_url: string | null; institutional_profile_url: string | null };
    college: string | null;
    department: string | null;
    submissions: ResearchItem[];
    canSeeContact: boolean;
}>();
const totalPoints = computed(() => props.submissions.reduce((total, item) => total + item.faculty_points, 0));
const externalProfiles = computed(() => [
    { label: 'ORCID', url: props.faculty.orcid_url, detail: 'Persistent researcher identifier' },
    { label: 'Google Scholar', url: props.faculty.google_scholar_url, detail: 'Publications and citation profile' },
    { label: 'Institutional profile', url: props.faculty.institutional_profile_url, detail: 'Leyte Normal University profile' },
].filter((profile): profile is { label: string; url: string; detail: string } => Boolean(profile.url)));

function displayProfileUrl(value: string): string {
    return value.replace(/^https?:\/\//, '').replace(/\/$/, '');
}
</script>

<template>
    <Head :title="`${faculty.name} Research Profile`" />
    <AuthenticatedLayout>
        <PageHeader :title="faculty.name" :description="faculty.designation || 'Faculty research profile'">
            <template #actions><Link v-if="faculty.id !== $page.props.auth.user.id" :href="route('research-profiles.index')" class="ori-btn-ghost">Faculty directory</Link></template>
        </PageHeader>
        <section class="grid gap-6 border-t border-ori-blue-100 py-6 md:grid-cols-[5rem_minmax(0,1fr)]">
            <img v-if="faculty.profile_photo_url" :src="faculty.profile_photo_url" alt="Faculty profile photo" class="h-20 w-20 rounded-full border border-ori-blue-200 object-cover" />
            <div v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-ori-blue-100 text-2xl font-semibold text-ori-blue-800">{{ faculty.name.slice(0, 1) }}</div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-ori-blue-400">College</dt><dd class="mt-1 text-ori-blue-900">{{ college || 'Not assigned' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-ori-blue-400">Department</dt><dd class="mt-1 text-ori-blue-900">{{ department || 'Not assigned' }}</dd></div>
                <div v-if="faculty.academic_rank"><dt class="text-xs font-semibold uppercase text-ori-blue-400">Academic rank</dt><dd class="mt-1 text-ori-blue-900">{{ faculty.academic_rank }}</dd></div>
                <div v-if="faculty.designation"><dt class="text-xs font-semibold uppercase text-ori-blue-400">Designation</dt><dd class="mt-1 text-ori-blue-900">{{ faculty.designation }}</dd></div>
                <div v-if="faculty.employee_id"><dt class="text-xs font-semibold uppercase text-ori-blue-400">Employee ID</dt><dd class="mt-1 text-ori-blue-900">{{ faculty.employee_id }}</dd></div>
                <div v-if="canSeeContact"><dt class="text-xs font-semibold uppercase text-ori-blue-400">Email</dt><dd class="mt-1 text-ori-blue-900">{{ faculty.email }}</dd></div>
            </dl>
        </section>
        <section v-if="externalProfiles.length" class="border-b border-ori-blue-100 py-5" aria-labelledby="researcher-profiles-heading">
            <div class="mb-3">
                <h2 id="researcher-profiles-heading" class="font-display text-lg font-semibold text-ori-blue-900">Researcher profiles</h2>
                <p class="mt-1 text-xs text-ori-blue-500">External scholarly and institutional records</p>
            </div>
            <ul class="grid gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="profile in externalProfiles" :key="profile.label" class="min-w-0">
                    <a :href="profile.url" target="_blank" rel="noopener noreferrer" class="group block min-w-0 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ori-blue-500">
                        <span class="flex items-center gap-1 text-sm font-semibold text-ori-blue-800 group-hover:text-ori-blue-600">
                            {{ profile.label }}
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M11 3a1 1 0 1 0 0 2h2.586l-7.293 7.293a1 1 0 0 0 1.414 1.414L15 6.414V9a1 1 0 1 0 2 0V4a1 1 0 0 0-1-1h-5Z"/><path d="M5 5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-3a1 1 0 1 0-2 0v3H5V7h3a1 1 0 1 0 0-2H5Z"/></svg>
                        </span>
                        <span class="mt-0.5 block truncate text-xs text-ori-blue-600 underline decoration-ori-blue-200 underline-offset-2">{{ displayProfileUrl(profile.url) }}</span>
                        <span class="mt-0.5 block text-xs text-ori-blue-400">{{ profile.detail }}</span>
                    </a>
                </li>
            </ul>
        </section>
        <p v-else class="border-b border-ori-blue-100 py-5 text-sm text-ori-blue-500">No external researcher profile links have been added to this profile.</p>
        <section class="mt-7">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="font-display text-xl font-semibold text-ori-blue-900">Research outputs</h2><p class="text-sm text-ori-blue-500">{{ submissions.length }} records · {{ totalPoints.toFixed(2) }} allocated points</p></div>
            </div>
            <div class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                <article v-for="item in submissions" :key="item.id" class="grid gap-3 py-5 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-ori-blue-900">{{ item.title }}</h3><StatusBadge :status="item.status" /></div>
                        <p class="mt-1 text-sm text-ori-blue-500">{{ item.research_type.replaceAll('_', ' ') }} · {{ item.publication_date || 'Publication date not recorded' }} · {{ item.reference_code }}</p>
                        <p class="mt-2 text-xs text-ori-blue-500">{{ item.college }}<span v-if="item.department"> · {{ item.department }}</span><span v-if="item.is_submitter"> · Submitted by this faculty member</span></p>
                        <p v-if="item.venue || item.doi" class="mt-2 text-xs text-ori-blue-600">{{ item.venue }}<span v-if="item.doi"> · DOI {{ item.doi }}</span></p>
                        <div v-if="item.sdgs?.length" class="mt-2 flex flex-wrap gap-2"><span v-for="sdg in item.sdgs" :key="sdg.number" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1 text-xs text-ori-blue-700"><img v-if="sdg.image_url" :src="sdg.image_url" :alt="`SDG ${sdg.number}: ${sdg.name}`" class="h-7 w-7 rounded object-cover" /><span>SDG {{ sdg.number }} · {{ sdg.name }}</span></span></div>
                        <div v-if="item.research_agendas?.length" class="mt-2 flex flex-wrap gap-2"><span v-for="agenda in item.research_agendas" :key="agenda.name" class="inline-flex items-center gap-2 border border-ori-blue-100 px-2 py-1 text-xs text-ori-blue-700"><img v-if="agenda.image_url" :src="agenda.image_url" :alt="`${agenda.name} image`" class="h-7 w-7 rounded object-cover" /><span>{{ agenda.name }}</span></span></div>
                    </div>
                    <div class="text-left sm:text-right"><span class="block text-xs uppercase text-ori-blue-400">Allocated points</span><strong class="text-lg tabular-nums text-ori-blue-900">{{ item.faculty_points.toFixed(2) }}</strong></div>
                    <p v-if="item.abstract" class="whitespace-pre-wrap text-sm leading-relaxed text-ori-blue-700 sm:col-span-2">{{ item.abstract }}</p>
                </article>
                <p v-if="submissions.length === 0" class="py-12 text-center text-sm text-ori-blue-500">No submitted research outputs are listed on this profile yet.</p>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
