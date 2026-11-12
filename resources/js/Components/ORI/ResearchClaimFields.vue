<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import type { ResearchType } from './researchClaim';

defineProps<{
    type: ResearchType | undefined;
    modelValue: string | number;
    rate: number | null;
    points: number | null;
    error: string;
    serverError?: string;
    legacy?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div v-if="type" class="space-y-3 border-y border-ori-blue-100 py-4">
        <div v-if="type.unit !== 'output'">
            <label for="involvement-quantity" class="ori-label">{{ type.unit === 'hour' ? 'Hours' : 'Days' }} <span v-if="!legacy" aria-hidden="true" class="text-rose-600">*</span><span v-if="!legacy" class="sr-only"> required</span></label>
            <input id="involvement-quantity" :value="modelValue" type="number" :min="type.unit === 'hour' ? '0.01' : '1'" :max="type.unit === 'hour' ? '999999.99' : '999999'" :step="type.unit === 'hour' ? '0.01' : '1'" :required="!legacy" :aria-invalid="!!serverError || (!!error && String(modelValue) !== '')" aria-describedby="involvement-quantity-help involvement-quantity-error" class="ori-input max-w-xs" @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)" />
            <p id="involvement-quantity-help" class="mt-1 text-xs text-ori-blue-500">{{ legacy ? 'Leave blank to retain the legacy fixed-point calculation, or enter a duration.' : type.unit === 'hour' ? 'Enter hours to at most two decimal places.' : 'Enter whole days.' }}</p>
            <InputError id="involvement-quantity-error" :message="serverError" class="mt-1" />
        </div>
        <p class="text-sm text-ori-blue-800">Rate: <strong>{{ rate === null ? 'Not configured' : `${rate.toFixed(2)} points per ${type.unit}` }}</strong></p>
        <p class="text-sm text-ori-blue-800">Calculated total: <strong>{{ points === null ? 'Unavailable' : `${points.toFixed(2)} points` }}</strong></p>
        <p v-if="error" role="status" class="text-sm text-rose-700">{{ error }}</p>
        <p v-if="type.semester_ceiling != null" class="text-sm text-amber-800">Reference ceiling: {{ Number(type.semester_ceiling).toFixed(2) }} points per semester. The coordinator must review the semester total; it is not checked automatically.</p>
    </div>
</template>