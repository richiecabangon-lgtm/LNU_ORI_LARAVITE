<script setup lang="ts">
defineProps<{
    status: string;
    label?: string;
}>();

const colorMap: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-ori-blue-100 text-ori-blue-800',
    under_evaluation: 'bg-indigo-100 text-indigo-800',
    evaluated: 'bg-ori-yellow-100 text-ori-blue-900',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    revision_requested: 'bg-orange-100 text-orange-800',
    gray: 'bg-slate-100 text-slate-700',
    blue: 'bg-ori-blue-100 text-ori-blue-800',
    indigo: 'bg-indigo-100 text-indigo-800',
    amber: 'bg-ori-yellow-100 text-ori-blue-900',
    green: 'bg-emerald-100 text-emerald-800',
    red: 'bg-rose-100 text-rose-800',
    orange: 'bg-orange-100 text-orange-800',
};

const labelMap: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    under_evaluation: 'Under Evaluation',
    evaluated: 'Evaluated',
    approved: 'Approved',
    rejected: 'Rejected',
    revision_requested: 'Revision Requested',
};
</script>

<template>
    <span class="ori-badge" :class="colorMap[status] || colorMap.gray">
        {{ label || labelMap[status] || status }}
    </span>
</template>
