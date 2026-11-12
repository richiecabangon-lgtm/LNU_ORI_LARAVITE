<script setup lang="ts">
import type { ActivityDates } from './activityDates';

defineProps<{ dates?: ActivityDates | null; legacy?: string | null; hasAuthors?: boolean }>();
</script>

<template>
    <div v-if="dates || legacy" class="space-y-2 text-sm">
        <div v-if="dates">
            <h3 class="font-semibold text-ori-blue-800">Activity dates</h3>
            <p v-if="dates.mode === 'single'">{{ dates.date }}</p>
            <p v-else-if="dates.mode === 'range'">{{ dates.start }} to {{ dates.end }}</p>
            <p v-else>{{ dates.dates.join(', ') }}</p>
        </div>
        <div v-if="legacy">
            <h3 class="font-semibold text-ori-blue-800">{{ hasAuthors === false ? 'Legacy single activity date' : 'Publication date' }}</h3>
            <p>{{ legacy.substring(0, 10) }}</p>
        </div>
    </div>
</template>