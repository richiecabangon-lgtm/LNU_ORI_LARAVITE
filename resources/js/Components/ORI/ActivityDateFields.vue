<script setup lang="ts">
import type { ActivityDateInput } from './activityDates';

defineProps<{ modelValue: ActivityDateInput; errors: Record<string, string> }>();
defineEmits<{ 'update:modelValue': [value: ActivityDateInput] }>();
</script>

<template>
    <fieldset class="space-y-3">
        <legend class="ori-label">Activity dates</legend>
        <label class="block max-w-sm">
            <span class="ori-label">Date format</span>
            <select v-model="modelValue.mode" class="ori-input">
                <option value="">Not specified</option>
                <option value="single">Single date</option>
                <option value="range">Date range</option>
                <option value="multiple">Multiple dates</option>
            </select>
        </label>
        <label v-if="modelValue.mode === 'single'" class="block max-w-sm"><span class="ori-label">Date</span><input v-model="modelValue.date" type="date" class="ori-input" /></label>
        <div v-if="modelValue.mode === 'range'" class="grid gap-3 sm:grid-cols-2">
            <label><span class="ori-label">Start date</span><input v-model="modelValue.start" type="date" class="ori-input" /></label>
            <label><span class="ori-label">End date</span><input v-model="modelValue.end" type="date" class="ori-input" /></label>
        </div>
        <div v-if="modelValue.mode === 'multiple'" class="space-y-2">
            <div v-for="(_, index) in modelValue.dates" :key="index" class="flex max-w-md items-end gap-3">
                <label class="min-w-0 flex-1"><span class="ori-label">Date {{ index + 1 }}</span><input v-model="modelValue.dates[index]" type="date" class="ori-input" /></label>
                <button v-if="modelValue.dates.length > 2" type="button" class="ori-btn-ghost" :aria-label="`Remove date ${index + 1}`" @click="modelValue.dates.splice(index, 1)">Remove</button>
            </div>
            <button v-if="modelValue.dates.length < 50" type="button" class="ori-btn-ghost" @click="modelValue.dates.push('')">Add date</button>
        </div>
        <p v-for="(message, field) in errors" v-show="String(field).startsWith('activity_dates')" :key="field" class="text-sm text-rose-700">{{ message }}</p>
    </fieldset>
</template>