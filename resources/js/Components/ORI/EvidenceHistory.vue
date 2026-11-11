<script setup lang="ts">
import { computed } from 'vue';

type Evidence = { id: number; name: string; is_required: boolean; attachments: Array<{ id: number; name: string }> };
type History = { id: number; created_at: string; meta?: { evidence_requirements?: Evidence[] } };
const props = defineProps<{ histories: History[] }>();
const snapshots = computed(() => props.histories
    .filter((history) => Array.isArray(history.meta?.evidence_requirements))
    .sort((first, second) => second.id - first.id));
</script>

<template>
    <section v-if="snapshots.length" class="border-t border-ori-blue-100 pt-4">
        <h3 class="text-sm font-semibold text-slate-700">Evidence submission history</h3>
        <div v-for="(history, index) in snapshots" :key="history.id" class="mt-3 border-l-2 border-slate-300 pl-3 text-sm text-slate-700">
            <p class="text-xs font-semibold text-slate-600">{{ index === 0 ? 'Latest submission snapshot' : 'Earlier submission snapshot' }}</p>
            <p class="text-xs text-slate-600">{{ new Date(history.created_at).toLocaleString() }}</p>
            <p v-if="!history.meta?.evidence_requirements?.length" class="mt-1 text-slate-600">No requirements configured.</p>
            <ul v-else class="mt-2 space-y-2">
                <li v-for="requirement in history.meta?.evidence_requirements" :key="requirement.id">
                    <strong>{{ requirement.name }}</strong> <span class="text-xs text-slate-600">({{ requirement.is_required ? 'Required' : 'Optional' }})</span>
                    <p class="text-slate-600">{{ requirement.attachments.map((file) => file.name).join(', ') || 'No file provided' }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>