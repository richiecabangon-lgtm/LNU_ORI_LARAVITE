<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Tier = { id: number | null; title: string; minimum_points: number | string };
const props = defineProps<{ tiers: Array<{ id: number; title: string; minimum_points: number }> }>();
const form = useForm<{ tiers: Tier[] }>({ tiers: props.tiers.map((tier) => ({ ...tier })) });

const preview = computed(() => {
    const sorted = form.tiers
        .map((tier, index) => ({ index, title: tier.title, minimum: Number(tier.minimum_points) }))
        .filter((tier) => Number.isFinite(tier.minimum))
        .sort((left, right) => left.minimum - right.minimum);

    return sorted.map((tier, position) => {
        const next = sorted[position + 1];
        return { ...tier, range: next ? `${tier.minimum.toLocaleString()} – ${(next.minimum - 0.01).toLocaleString()}` : `${tier.minimum.toLocaleString()}+` };
    });
});

function addTier() {
    const highest = Math.max(0, ...form.tiers.map((tier) => Number(tier.minimum_points) || 0));
    form.tiers.push({ id: null, title: '', minimum_points: highest + 100 });
}
function fieldError(index: number, field: 'title' | 'minimum_points'): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`tiers.${index}.${field}`];
}
function removeTier(index: number) {
    form.tiers.splice(index, 1);
}
function save() {
    form.put(route('admin.achievement-tiers.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Achievement Legend" />
    <AuthenticatedLayout>
        <PageHeader title="Achievement legend" description="Set the academic achievement tiers and the approved-point threshold where each tier begins. Each range ends just before the next tier starts." />
        <form class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]" @submit.prevent="save">
            <section aria-labelledby="legend-editor">
                <h2 id="legend-editor" class="sr-only">Legend editor</h2>
                <div class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                    <div v-for="(tier, index) in form.tiers" :key="tier.id ?? `new-${index}`" class="grid gap-3 py-4 md:grid-cols-[minmax(0,1fr)_11rem_auto] md:items-end">
                        <label>
                            <span class="ori-label">Legend title</span>
                            <input v-model="tier.title" class="ori-input" maxlength="80" required />
                            <InputError :message="fieldError(index, 'title')" />
                        </label>
                        <label>
                            <span class="ori-label">Starts at (points)</span>
                            <input v-model.number="tier.minimum_points" type="number" min="0" step="0.01" class="ori-input" required />
                            <InputError :message="fieldError(index, 'minimum_points')" />
                        </label>
                        <button type="button" class="pb-2 text-sm font-semibold text-rose-700 disabled:opacity-40" :disabled="form.tiers.length <= 1" @click="removeTier(index)">Remove</button>
                    </div>
                </div>
                <InputError :message="form.errors.tiers" class="mt-3" />
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="ori-btn-ghost" :disabled="form.tiers.length >= 12" @click="addTier">Add legend</button>
                    <button class="ori-btn-primary" :disabled="form.processing">Save legend</button>
                </div>
            </section>
            <aside aria-labelledby="legend-preview">
                <h2 id="legend-preview" class="font-display text-lg font-semibold text-ori-blue-900">Point ranges</h2>
                <ol class="mt-3 space-y-3">
                    <li v-for="tier in preview" :key="tier.index" class="border-l-2 border-emerald-500 pl-3">
                        <p class="text-sm font-semibold text-ori-blue-900">{{ tier.title || 'Untitled legend' }}</p>
                        <p class="mt-0.5 text-xs text-ori-blue-500">{{ tier.range }} points</p>
                    </li>
                </ol>
                <p class="mt-4 text-xs text-ori-blue-500">The lowest legend must start at 0 so every faculty member, including those with no approved points, has a tier.</p>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
