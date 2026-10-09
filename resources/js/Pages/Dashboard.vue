<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import StatCard from '@/Components/ORI/StatCard.vue';
import StatusBadge from '@/Components/ORI/StatusBadge.vue';
import SubmissionAnalytics from '@/Components/ORI/SubmissionAnalytics.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type AchievementTier = {
    title: string;
    minimum: number;
    next: number | null;
};

type AchievementProgress = {
    title: string;
    minimum_points: number;
    next_title: string | null;
    next_threshold: number | null;
    points_to_next_tier: number;
    progress_percent: number;
};

const props = defineProps<{
    stats: Record<string, number>;
    recent: any[];
    role: string;
    leaderboard?: {
        scope: 'college' | 'institution' | 'self';
        college_id: number | null;
        colleges: Array<{ id: number; name: string }>;
        tiers: AchievementTier[];
        items: Array<{ id: number; name: string; academic_rank: string | null; designation: string | null; college: string | null; department: string | null; points: number; rank: number; award: string | null; achievement_tier: AchievementProgress }>;
        myStanding?: {
            college: { rank: number | null; participants: number; points: number; award: string | null; points_to_next_rank: number; achievement_tier: AchievementProgress } | null;
            institution: { rank: number | null; participants: number; points: number; award: string | null; points_to_next_rank: number; achievement_tier: AchievementProgress } | null;
        };
    } | null;
    analytics: {
        trend: Array<{ period: string; label: string; total: number }>;
        statuses: Array<{ key: string; label: string; total: number }>;
        researchTypes: Array<{ key: string; label: string; total: number }>;
    };
}>();

const leaderboardScope = ref(props.leaderboard?.scope ?? (props.role === 'coordinator' ? 'college' : 'institution'));
const leaderboardCollegeId = ref<number | null>(props.leaderboard?.college_id ?? null);

function loadLeaderboard() {
    router.get(route('dashboard'), {
        leaderboard_scope: leaderboardScope.value,
        leaderboard_college_id: leaderboardScope.value === 'college' ? leaderboardCollegeId.value ?? undefined : undefined,
    }, { preserveScroll: true, preserveState: true });
}

const titles = computed(() => {
    if (props.role === 'faculty') {
        return {
            title: 'Faculty workspace',
            description: 'Monitor your research involvement submissions and current workflow status.',
            cards: [
                { label: 'Total submissions', value: props.stats.total, hint: 'All records', accent: true },
                { label: 'Drafts', value: props.stats.draft, hint: 'Ready to complete' },
                { label: 'In progress', value: props.stats.in_progress, hint: 'Awaiting action' },
                { label: 'Approved', value: props.stats.approved, hint: 'Finalized outputs' },
            ],
            cta: { href: route('faculty.submissions.create'), label: 'New submission' },
        };
    }

    if (props.role === 'coordinator') {
        return {
            title: 'Coordinator evaluation desk',
            description: 'Review pending faculty research involvement and record evaluation outcomes.',
            cards: [
                { label: 'Pending review', value: props.stats.pending, hint: 'Needs evaluation', accent: true },
                { label: 'Evaluated', value: props.stats.evaluated, hint: 'Awaiting admin' },
                { label: 'Revision requested', value: props.stats.revision, hint: 'Returned to faculty' },
                { label: 'Approved', value: props.stats.approved, hint: 'Institutionally accepted' },
            ],
            cta: { href: route('coordinator.evaluations.index'), label: 'Open queue' },
        };
    }

    return {
        title: 'Admin command center',
        description: 'Approve evaluated submissions and keep ORI accounts and records sustainable.',
        cards: [
            { label: 'Awaiting approval', value: props.stats.awaiting_approval, hint: 'Ready for decision', accent: true },
            { label: 'Approved', value: props.stats.approved, hint: 'Closed positively' },
            { label: 'Rejected', value: props.stats.rejected, hint: 'Closed negatively' },
            { label: 'Faculty accounts', value: props.stats.faculty_users, hint: 'Active researchers' },
        ],
        cta: { href: route('admin.approvals.index'), label: 'Review approvals' },
    };
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <PageHeader :title="titles.title" :description="titles.description">
            <template #actions>
                <Link :href="titles.cta.href" class="ori-btn-accent">
                    {{ titles.cta.label }}
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="card in titles.cards"
                :key="card.label"
                :label="card.label"
                :value="card.value"
                :hint="card.hint"
                :accent="card.accent"
            />
        </div>

        <SubmissionAnalytics
            :trend="analytics.trend"
            :statuses="analytics.statuses"
            :research-types="analytics.researchTypes"
        />

        <section v-if="role === 'faculty' && leaderboard?.myStanding" class="ori-card mt-8 overflow-hidden">
            <div class="border-b border-ori-blue-100 px-6 py-4">
                <p class="text-xs font-semibold uppercase text-ori-blue-500">Private standing</p>
                <h2 class="mt-1 font-display text-xl font-semibold text-ori-blue-900">Your research ranking</h2>
                <p class="mt-1 text-xs text-ori-blue-500">Based on points from approved submissions only. Other faculty rankings are not shown here.</p>
            </div>
            <div class="grid gap-0 divide-y divide-ori-blue-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <div v-for="standing in [
                    { key: 'college', label: 'College ranking', value: leaderboard.myStanding.college },
                    { key: 'institution', label: 'LNU ranking', value: leaderboard.myStanding.institution },
                ]" :key="standing.key" class="flex items-center gap-4 px-6 py-5">
                    <div v-if="standing.value" class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-full border-2 text-center" :class="standing.value.award === 'Gold' ? 'border-yellow-500 bg-yellow-50 text-yellow-700' : standing.value.award === 'Silver' ? 'border-slate-400 bg-slate-50 text-slate-600' : standing.value.award === 'Platinum' ? 'border-cyan-500 bg-cyan-50 text-cyan-700' : 'border-ori-blue-200 bg-ori-blue-50 text-ori-blue-700'" :aria-label="standing.value.award ? `${standing.value.award} medal` : standing.value.rank ? `Rank ${standing.value.rank}` : 'Rank not established'">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 15 8l7 .9-5 4.8 1.2 6.8L12 17.2l-6.2 3.3L7 13.7 2 8.9 9 8l3-6Z" /></svg>
                        <span class="text-xs font-bold tabular-nums">{{ standing.value.rank ? `#${standing.value.rank}` : '—' }}</span>
                    </div>
                    <div v-if="standing.value" class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold text-ori-blue-900">{{ standing.label }}<span v-if="standing.value.award" class="ms-2 text-xs font-bold" :class="standing.value.award === 'Gold' ? 'text-yellow-700' : standing.value.award === 'Silver' ? 'text-slate-600' : 'text-cyan-700'">{{ standing.value.award }} medal</span></h3>
                        <p class="mt-1 text-sm font-semibold text-emerald-800">{{ standing.value.achievement_tier.title }}</p>
                        <p class="mt-1 text-xs text-ori-blue-600">{{ standing.value.points.toFixed(2) }} approved points<span v-if="standing.value.rank"> · rank {{ standing.value.rank }} of {{ standing.value.participants }}</span><span v-else> · rank not established</span></p>
                        <template v-if="standing.value.achievement_tier.next_title">
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-ori-blue-500">
                                <span>{{ standing.value.achievement_tier.points_to_next_tier.toFixed(2) }} points to {{ standing.value.achievement_tier.next_title }}</span>
                                <span>{{ standing.value.achievement_tier.progress_percent }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-ori-blue-100" role="progressbar" :aria-label="`${standing.value.achievement_tier.title} progression`" :aria-valuenow="standing.value.achievement_tier.progress_percent" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-emerald-600 transition-[width]" :style="{ width: `${standing.value.achievement_tier.progress_percent}%` }"></div>
                            </div>
                        </template>
                        <p v-else class="mt-2 text-xs font-medium text-emerald-700">Highest achievement tier reached.</p>
                        <p v-if="standing.value.rank && standing.value.points_to_next_rank > 0" class="mt-1 text-xs text-ori-blue-500">{{ standing.value.points_to_next_rank.toFixed(2) }} points to the next faculty rank</p>
                        <p v-else-if="standing.value.rank" class="mt-1 text-xs text-ori-blue-500">You are currently tied for the highest faculty rank.</p>
                        <p v-else class="mt-1 text-xs text-ori-blue-500">Approved research points will establish your faculty rank.</p>
                    </div>
                    <p v-else class="text-sm text-ori-blue-500">Standing is unavailable for this scope.</p>
                </div>
            </div>
        </section>

        <section v-if="leaderboard && (role === 'coordinator' || role === 'admin')" class="ori-card mt-8 overflow-hidden">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-ori-blue-100 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase text-ori-blue-500">Automatic ranking</p>
                    <h2 class="mt-1 font-display text-xl font-semibold text-ori-blue-900">Top 10 research contributors</h2>
                    <p class="mt-1 text-xs text-ori-blue-500">Ranked by cumulative points from approved submissions only.</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="inline-flex rounded-md border border-ori-blue-100 p-1" role="group" aria-label="Leaderboard scope">
                        <button type="button" class="rounded px-3 py-2 text-sm font-semibold" :class="leaderboardScope === 'college' ? 'bg-ori-blue-700 text-white' : 'text-ori-blue-700 hover:bg-ori-blue-50'" @click="leaderboardScope = 'college'; loadLeaderboard()">College</button>
                        <button type="button" class="rounded px-3 py-2 text-sm font-semibold" :class="leaderboardScope === 'institution' ? 'bg-ori-blue-700 text-white' : 'text-ori-blue-700 hover:bg-ori-blue-50'" @click="leaderboardScope = 'institution'; loadLeaderboard()">Institution</button>
                    </div>
                    <label v-if="role === 'admin' && leaderboardScope === 'college'" class="grid gap-1 text-xs font-semibold text-ori-blue-700">
                        College
                        <select v-model.number="leaderboardCollegeId" class="min-w-52 rounded-md border-ori-blue-200 text-sm" @change="loadLeaderboard">
                            <option :value="null">Select a college</option>
                            <option v-for="college in leaderboard.colleges" :key="college.id" :value="college.id">{{ college.name }}</option>
                        </select>
                    </label>
                </div>
            </div>
            <div v-if="leaderboard.items.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-ori-blue-100 text-left text-sm">
                    <thead class="bg-ori-blue-50/70 text-xs uppercase text-ori-blue-500">
                        <tr><th class="px-5 py-3">Rank</th><th class="px-5 py-3">Faculty / achievement tier</th><th class="px-5 py-3">College / department</th><th class="px-5 py-3 text-right">Approved points</th></tr>
                    </thead>
                    <tbody class="divide-y divide-ori-blue-50">
                        <tr v-for="item in leaderboard.items" :key="item.id">
                            <td class="px-5 py-3">
                                <span class="inline-flex min-w-12 flex-col items-center justify-center rounded-full border px-2 py-1 tabular-nums" :class="item.award === 'Gold' ? 'border-yellow-500 bg-yellow-50 text-yellow-700' : item.award === 'Silver' ? 'border-slate-400 bg-slate-50 text-slate-600' : item.award === 'Platinum' ? 'border-cyan-500 bg-cyan-50 text-cyan-700' : 'border-ori-blue-100 text-ori-blue-700'" :aria-label="item.award ? `${item.award} medal, rank ${item.rank}` : `Rank ${item.rank}`">
                                    <svg v-if="item.award" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 15 8l7 .9-5 4.8 1.2 6.8L12 17.2l-6.2 3.3L7 13.7 2 8.9 9 8l3-6Z" /></svg>
                                    <span class="text-xs font-bold">#{{ item.rank }}</span>
                                </span>
                            </td>
                            <td class="px-5 py-3"><Link :href="route('research-profiles.show', item.id)" class="font-semibold text-ori-blue-900 hover:underline">{{ item.name }}</Link><span class="mt-0.5 block text-xs font-semibold text-emerald-800">{{ item.achievement_tier.title }}<span v-if="item.achievement_tier.next_title" class="font-normal text-ori-blue-500"> · {{ item.achievement_tier.points_to_next_tier.toFixed(2) }} points to next tier</span></span><span v-if="item.academic_rank || item.designation" class="mt-0.5 block text-xs text-ori-blue-500">{{ [item.academic_rank, item.designation].filter(Boolean).join(' · ') }}</span></td>
                            <td class="px-5 py-3 text-ori-blue-700">{{ item.college || 'Not assigned' }}<span v-if="item.department" class="block text-xs text-ori-blue-500">{{ item.department }}</span></td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums text-ori-blue-900">{{ Number(item.points).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="px-6 py-8 text-sm text-ori-blue-500">No approved research points are available for this ranking scope yet.</p>
        </section>

        <section v-if="leaderboard?.tiers.length" class="mt-8 border-y border-ori-blue-100 py-5" aria-labelledby="achievement-tier-heading">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="achievement-tier-heading" class="font-display text-lg font-semibold text-ori-blue-900">Academic achievement tiers</h2>
                <p class="text-xs text-ori-blue-500">Cumulative approved points</p>
            </div>
            <ol class="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-5">
                <li v-for="tier in leaderboard.tiers" :key="tier.title" class="border-l-2 border-emerald-500 pl-3">
                    <p class="text-sm font-semibold text-ori-blue-900">{{ tier.title }}</p>
                    <p class="mt-0.5 text-xs text-ori-blue-500">{{ tier.minimum.toLocaleString() }}<span v-if="tier.next">–{{ (tier.next - 0.01).toLocaleString() }}</span><span v-else>+</span> points</p>
                </li>
            </ol>
        </section>

        <section class="ori-card mt-8 overflow-hidden">
            <div class="border-b border-ori-blue-100 px-6 py-4">
                <h2 class="font-display text-xl font-semibold text-ori-blue-900">
                    Recent activity
                </h2>
            </div>
            <div class="divide-y divide-ori-blue-50">
                <div
                    v-for="item in recent"
                    :key="item.id"
                    class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-semibold text-ori-blue-900">{{ item.title }}</p>
                        <p class="text-sm text-ori-blue-500">
                            {{ item.reference_code }}
                            <span v-if="item.faculty"> · {{ item.faculty.name }}</span>
                        </p>
                        <p v-if="role === 'admin'" class="mt-1 text-xs capitalize text-ori-blue-500">
                            {{ item.action_label }}<span v-if="item.actor"> by {{ item.actor }}</span>
                            <span v-if="item.activity_at"> · {{ new Date(item.activity_at).toLocaleString() }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <StatusBadge :status="item.status" />
                        <Link
                            v-if="role === 'faculty'"
                            :href="route('faculty.submissions.show', item.uuid)"
                            class="text-sm font-semibold text-ori-blue-700 hover:text-ori-blue-900"
                        >
                            View
                        </Link>
                        <Link
                            v-else-if="role === 'coordinator'"
                            :href="route('coordinator.evaluations.show', item.uuid)"
                            class="text-sm font-semibold text-ori-blue-700 hover:text-ori-blue-900"
                        >
                            Evaluate
                        </Link>
                        <Link
                            v-else
                            :href="route('admin.approvals.show', item.uuid)"
                            class="text-sm font-semibold text-ori-blue-700 hover:text-ori-blue-900"
                        >
                            {{ item.status === 'evaluated' ? 'Decide' : 'View' }}
                        </Link>
                    </div>
                </div>
                <div v-if="recent.length === 0" class="px-6 py-10 text-center text-sm text-ori-blue-500">
                    No recent records yet.
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
