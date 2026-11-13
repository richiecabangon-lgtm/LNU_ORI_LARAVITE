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
const selectedGroup = computed(() => groups.value.find((group) => group.types.some((type) => type.value === props.modelValue)));
const categoriesOpen = ref(false);
const openGroup = ref<string | null>(null);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);

function closeOnOutsidePointer(event: PointerEvent) {
    if (categoriesOpen.value && event.target instanceof Node && !root.value?.contains(event.target)) {
        categoriesOpen.value = false;
        openGroup.value = null;
    }
}

function closeOnEscape(event: KeyboardEvent) {
    if (categoriesOpen.value && event.key === 'Escape' && event.target instanceof Node && root.value?.contains(event.target)) {
        categoriesOpen.value = false;
        openGroup.value = null;
        trigger.value?.focus();
    }
}

function selectType(value: string) {
    emit('update:modelValue', value);
    categoriesOpen.value = false;
    openGroup.value = null;
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
        <div class="relative">
            <button
                ref="trigger"
                type="button"
                class="flex min-h-11 w-full items-center justify-between gap-3 border border-ori-blue-200 bg-white px-4 py-2 text-left text-sm text-ori-blue-800 shadow-sm hover:bg-ori-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ori-blue-700"
                :aria-expanded="categoriesOpen"
                aria-controls="research-type-categories"
                @click="categoriesOpen = !categoriesOpen; !categoriesOpen && (openGroup = null)"
            >
                <span aria-live="polite">{{ selected ? `${selectedGroup?.label}: ${selected.label}` : 'Select a research involvement category' }}</span>
                <span aria-hidden="true">{{ categoriesOpen ? '−' : '+' }}</span>
            </button>
            <div id="research-type-categories" v-show="categoriesOpen" class="absolute inset-x-0 top-full z-30 mt-1 max-h-80 divide-y divide-ori-blue-100 overflow-y-auto border border-ori-blue-100 bg-white shadow-lg">
                <div v-for="(group, index) in groups" :key="group.label">
                    <h3>
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-ori-blue-800 hover:bg-ori-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ori-blue-700" :aria-expanded="openGroup === group.label" :aria-controls="`research-type-group-${index}`" @click="openGroup = openGroup === group.label ? null : group.label">
                            <span>{{ group.label }}</span><span aria-hidden="true">{{ openGroup === group.label ? '−' : '+' }}</span>
                        </button>
                    </h3>
                    <div :id="`research-type-group-${index}`" v-show="openGroup === group.label" class="space-y-1 bg-ori-blue-50 px-4 py-3">
                        <label v-for="type in group.types" :key="type.value" class="flex cursor-pointer items-start gap-3 py-1 text-sm text-ori-blue-800">
                            <input type="radio" name="research_type" :value="type.value" :checked="modelValue === type.value" :aria-invalid="!!error" :aria-describedby="error ? 'research-type-error' : undefined" class="mt-0.5 border-ori-blue-300 text-ori-blue-700" @change="selectType(type.value)" />
                            <span>{{ type.label }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <InputError id="research-type-error" :message="error" class="mt-1" />
    </fieldset>
</template>