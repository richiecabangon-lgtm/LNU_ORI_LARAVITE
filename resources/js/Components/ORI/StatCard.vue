<script setup lang="ts">
defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    accent?: boolean;
}>();
</script>

<template>
    <div
        class="ori-card relative overflow-hidden p-5"
        :class="accent ? 'bg-ori-blue-700 text-white' : ''"
    >
        <div
            v-if="accent"
            class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-ori-yellow-400/30"
        />
        <p
            class="text-sm font-medium"
            :class="accent ? 'text-ori-blue-100' : 'text-ori-blue-500'"
        >
            {{ label }}
        </p>
        <p
            class="mt-2 font-display text-3xl font-semibold tracking-tight"
            :class="accent ? 'text-white' : 'text-ori-blue-900'"
        >
            {{ value }}
        </p>
        <p
            v-if="hint"
            class="mt-2 text-xs"
            :class="accent ? 'text-ori-yellow-200' : 'text-ori-blue-400'"
        >
            {{ hint }}
        </p>
    </div>
</template>
