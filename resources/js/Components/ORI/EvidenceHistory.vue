<script setup lang="ts">
type Evidence = { id: number; name: string; is_required: boolean; attachments: Array<{ id: number; name: string }> };
type History = { id: number; created_at: string; meta?: { evidence_requirements?: Evidence[] } };
defineProps<{ histories: History[] }>();
</script>

<template>
    <section v-if="histories.some((history) => history.meta?.evidence_requirements)" class="border-t border-ori-blue-100 pt-4">
        <h3 class="text-sm font-semibold text-ori-blue-900">Evidence at submission</h3>
        <div v-for="history in histories.filter((item) => item.meta?.evidence_requirements)" :key="history.id" class="mt-3 text-sm">
            <p class="text-xs text-ori-blue-500">{{ new Date(history.created_at).toLocaleString() }}</p>
            <p v-if="!history.meta?.evidence_requirements?.length" class="mt-1 text-ori-blue-600">No requirements configured.</p>
            <ul v-else class="mt-2 space-y-2">
                <li v-for="requirement in history.meta?.evidence_requirements" :key="requirement.id">
                    <strong>{{ requirement.name }}</strong> <span class="text-xs text-ori-blue-500">({{ requirement.is_required ? 'Required' : 'Optional' }})</span>
                    <p class="text-ori-blue-600">{{ requirement.attachments.map((file) => file.name).join(', ') || 'No file provided' }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>