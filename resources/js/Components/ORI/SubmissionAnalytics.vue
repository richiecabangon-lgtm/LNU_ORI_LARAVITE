<script setup lang="ts">
import { computed } from 'vue';

type TrendPoint = { period: string; label: string; total: number };
type Breakdown = { key: string; label: string; total: number };

const props = defineProps<{
    trend: TrendPoint[];
    statuses: Breakdown[];
    researchTypes: Breakdown[];
}>();

const trendMax = computed(() => Math.max(1, ...props.trend.map((point) => point.total)));
const statusMax = computed(() => Math.max(1, ...props.statuses.map((item) => item.total)));
const researchTypeMax = computed(() => Math.max(1, ...props.researchTypes.map((item) => item.total)));
const trendBars = computed(() => props.trend.map((point, index) => {
    const x = 52 + index * 55;
    const height = point.total === 0 ? 0 : (point.total / trendMax.value) * 140;

    return {
        ...point,
        x,
        center: x + 14,
        y: 185 - height,
        height,
    };
}));
const trendLine = computed(() => trendBars.value.map((point) => `${point.center},${point.total ? point.y : 185}`).join(' '));

const statusColors: Record<string, string> = {
    draft: '#94a3b8',
    submitted: '#3b82f6',
    under_evaluation: '#7c3aed',
    evaluated: '#eab308',
    approved: '#16a34a',
    rejected: '#ef4444',
    revision_requested: '#f97316',
};
</script>

<template>
    <section class="mt-8 border-y border-ori-blue-100 py-6" aria-labelledby="analytics-title">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-ori-blue-500">Research activity</p>
                <h2 id="analytics-title" class="mt-1 font-display text-xl font-semibold text-ori-blue-900">Submission analytics</h2>
            </div>
            <p class="text-sm text-ori-blue-600">Submitted records over the last 12 months</p>
        </div>

        <div class="grid gap-7 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,1fr)]">
            <figure class="min-w-0" aria-labelledby="trend-title">
                <figcaption id="trend-title" class="mb-3 text-sm font-semibold text-ori-blue-800">Monthly trend</figcaption>
                <div class="overflow-x-auto">
                    <svg class="h-64 min-w-[680px] w-full" viewBox="0 0 720 240" role="img" aria-label="Submission counts for each of the last 12 months">
                        <line x1="42" y1="45" x2="700" y2="45" stroke="#dbe4ef" stroke-width="1" />
                        <line x1="42" y1="115" x2="700" y2="115" stroke="#dbe4ef" stroke-width="1" />
                        <line x1="42" y1="185" x2="700" y2="185" stroke="#aabbd0" stroke-width="1" />
                        <text x="8" y="49" fill="#64748b" font-size="11">{{ trendMax }}</text>
                        <text x="8" y="119" fill="#64748b" font-size="11">{{ Math.ceil(trendMax / 2) }}</text>
                        <text x="8" y="189" fill="#64748b" font-size="11">0</text>
                        <polyline :points="trendLine" fill="none" stroke="#1e4d8c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <g v-for="point in trendBars" :key="point.period">
                            <rect :x="point.x" :y="point.y" width="28" :height="point.height" rx="3" fill="#f5c518" />
                            <circle :cx="point.center" :cy="point.total ? point.y : 185" r="3.5" fill="#1e4d8c" />
                            <text v-if="point.total > 0" :x="point.center" :y="point.y - 8" text-anchor="middle" fill="#183b69" font-size="10" font-weight="700">{{ point.total }}</text>
                            <text :x="point.center" y="210" text-anchor="middle" fill="#52647b" font-size="10">{{ point.label }}</text>
                        </g>
                    </svg>
                </div>
            </figure>

            <figure aria-labelledby="status-title">
                <figcaption id="status-title" class="mb-3 text-sm font-semibold text-ori-blue-800">Current workflow status</figcaption>
                <div class="grid gap-3">
                    <div v-for="item in statuses" :key="item.key" class="grid grid-cols-[minmax(7rem,1fr)_minmax(5rem,1.2fr)_2rem] items-center gap-3 text-xs">
                        <span class="truncate text-ori-blue-700">{{ item.label }}</span>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="img" :aria-label="`${item.label}: ${item.total}`">
                            <div class="h-full rounded-full transition-[width]" :style="{ width: `${(item.total / statusMax) * 100}%`, backgroundColor: statusColors[item.key] ?? '#64748b' }" />
                        </div>
                        <span class="text-right font-semibold tabular-nums text-ori-blue-900">{{ item.total }}</span>
                    </div>
                </div>
            </figure>
        </div>

        <figure class="mt-7 max-w-4xl" aria-labelledby="type-title">
            <figcaption id="type-title" class="mb-3 text-sm font-semibold text-ori-blue-800">Research outputs by type</figcaption>
            <div class="grid gap-2 sm:grid-cols-2">
                <div v-for="item in researchTypes" :key="item.key" class="grid grid-cols-[minmax(8rem,1fr)_minmax(3rem,1.5fr)_2rem] items-center gap-3 text-xs">
                    <span class="truncate text-ori-blue-700">{{ item.label }}</span>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="img" :aria-label="`${item.label}: ${item.total}`">
                        <div class="h-full rounded-full bg-emerald-600" :style="{ width: `${(item.total / researchTypeMax) * 100}%` }" />
                    </div>
                    <span class="text-right font-semibold tabular-nums text-ori-blue-900">{{ item.total }}</span>
                </div>
            </div>
        </figure>
    </section>
</template>