<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    researchTypes: Array<{ value: string; label: string; points: number }>;
}>();

const form = useForm({
    points: Object.fromEntries(props.researchTypes.map((type) => [type.value, type.points])) as Record<string, number>,
});
</script>

<template>
    <Head title="Research Type Point Schedule" />
    <AuthenticatedLayout>
        <PageHeader title="Research type point schedule" description="Set the maximum point value assigned to each research output. These values determine the default author allocations." />
        <form class="max-w-4xl space-y-5" @submit.prevent="form.put(route('admin.research-type-points.update'))">
            <div class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                <label v-for="(type, index) in researchTypes" :key="type.value" class="grid grid-cols-[minmax(0,1fr)_9rem] items-center gap-5 py-4">
                    <span>
                        <span class="block font-semibold text-ori-blue-900">{{ type.label }}</span>
                        <span class="text-xs text-ori-blue-500">{{ type.value }}</span>
                    </span>
                    <span class="relative">
                        <input v-model.number="form.points[type.value]" type="number" min="0" step="0.01" required class="ori-input pe-12 text-right tabular-nums" :aria-label="`${type.label} point value`" />
                        <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-ori-blue-400">pts</span>
                        <span v-if="form.errors[`points.${type.value}`]" class="mt-1 block text-xs text-rose-700">{{ form.errors[`points.${type.value}`] }}</span>
                    </span>
                </label>
            </div>
            <div class="flex items-center gap-4">
                <button type="submit" :disabled="form.processing" class="ori-btn-accent">{{ form.processing ? 'Saving...' : 'Save point schedule' }}</button>
                <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700">Point values saved.</span>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
