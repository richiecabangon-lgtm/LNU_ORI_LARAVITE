<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    modelValue: string;
    types: Array<{ value: string; label: string; group?: string }>;
    error?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const groups = computed(() => {
    const grouped = new Map<string, typeof props.types>();
    props.types.forEach((type) => grouped.set(type.group || 'Other', [...(grouped.get(type.group || 'Other') ?? []), type]));
    return [...grouped].map(([label, types]) => ({ label, types }));
});
const selected = computed(() => props.types.find((type) => type.value === props.modelValue));
const openGroup = ref<string | null>(null);
const root = ref<HTMLElement | null>(null);

function closeOnOutsidePointer(event: PointerEvent) {
    if (openGroup.value && event.target instanceof Node && !root.value?.contains(event.target)) openGroup.value = null;
}

function closeOnEscape(event: KeyboardEvent) {
    if (openGroup.value && event.key === 'Escape' && event.target instanceof Node && root.value?.contains(event.target)) {
        const expanded = root.value.querySelector<HTMLButtonElement>('button[aria-expanded="true"]');
        openGroup.value = null;
        expanded?.focus();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutsidePointer);
    document.addEventListener('keydown', closeOnEscape);
});
onUnmounted(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <fieldset ref="root">
        <legend class="ori-label">Research involvement type <span aria-hidden="true" class="text-rose-600">*</span><span class="sr-only"> required</span></legend>
        <p class="mb-2 text-sm text-ori-blue-700" aria-live="polite">{{ selected ? `Selected: ${selected.label}` : 'No involvement type selected.' }}</p>
        <div class="divide-y divide-ori-blue-100 border border-ori-blue-100">
            <div v-for="(group, index) in groups" :key="group.label">
                <h3>
                    <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-ori-blue-800 hover:bg-ori-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ori-blue-700" :aria-expanded="openGroup === group.label" :aria-controls="`research-type-group-${index}`" @click="openGroup = openGroup === group.label ? null : group.label">
                        <span>{{ group.label }}</span><span aria-hidden="true">{{ openGroup === group.label ? '−' : '+' }}</span>
                    </button>
                </h3>
                <div :id="`research-type-group-${index}`" v-show="openGroup === group.label" class="space-y-1 bg-ori-blue-50 px-4 py-3">
                    <label v-for="type in group.types" :key="type.value" class="flex cursor-pointer items-start gap-3 py-1 text-sm text-ori-blue-800">
                        <input type="radio" name="research_type" :value="type.value" :checked="modelValue === type.value" :aria-invalid="!!error" :aria-describedby="error ? 'research-type-error' : undefined" class="mt-0.5 border-ori-blue-300 text-ori-blue-700" @change="emit('update:modelValue', type.value)" />
                        <span>{{ type.label }}</span>
                    </label>
                </div>
            </div>
        </div>
        <InputError id="research-type-error" :message="error" class="mt-1" />
    </fieldset>
</template>