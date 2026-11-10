<script setup lang="ts">
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    modelValue: string[];
    errors: Record<string, string>;
    idPrefix: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();

function updateInterest(index: number, event: Event) {
    const values = [...props.modelValue];
    values[index] = (event.target as HTMLInputElement).value;
    emit('update:modelValue', values);
}
</script>

<template>
    <fieldset class="space-y-2">
        <legend class="ori-label">Research interests</legend>
        <div v-for="(interest, index) in modelValue" :key="index" class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <label :for="`${idPrefix}-${index}`" class="sr-only">Research interest {{ index + 1 }}</label>
                <input :id="`${idPrefix}-${index}`" :value="interest" type="text" maxlength="255" class="ori-input" @input="updateInterest(index, $event)" />
                <InputError :message="errors[`research_interests.${index}`]" class="mt-1" />
            </div>
            <button type="button" class="ori-btn-ghost" :aria-label="`Remove research interest ${index + 1}`" @click="emit('update:modelValue', modelValue.filter((_, position) => position !== index))">Remove</button>
        </div>
        <button v-if="modelValue.length < 5" type="button" class="ori-btn-ghost" @click="emit('update:modelValue', [...modelValue, ''])">Add interest</button>
        <InputError :message="errors.research_interests" />
    </fieldset>
</template>