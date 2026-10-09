<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    agendaId: number;
    description: string;
}>();

const isOpen = ref(false);
const panelId = `agenda-context-${props.agendaId}`;
</script>

<template>
    <div class="mt-2 ps-8 text-xs text-ori-blue-600">
        <button
            type="button"
            class="inline-flex items-center gap-1 font-semibold text-ori-blue-700 hover:text-ori-blue-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-ori-blue-500"
            :aria-expanded="isOpen"
            :aria-controls="panelId"
            @click="isOpen = !isOpen"
        >
            {{ isOpen ? 'Hide agenda context' : 'View agenda context' }}
            <svg
                class="h-3.5 w-3.5 transition-transform duration-200 motion-reduce:transition-none"
                :class="isOpen ? 'rotate-180' : ''"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>
        <Transition name="agenda-context">
            <div v-if="isOpen" :id="panelId" class="agenda-context-panel" role="region">
                <div class="agenda-context-panel-inner">
                    <p class="pt-2 leading-relaxed">{{ description }}</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.agenda-context-panel {
    display: grid;
    grid-template-rows: 1fr;
    opacity: 1;
    transition: grid-template-rows 220ms ease, opacity 180ms ease;
}

.agenda-context-panel-inner {
    min-height: 0;
    overflow: hidden;
}

.agenda-context-enter-from,
.agenda-context-leave-to {
    grid-template-rows: 0fr;
    opacity: 0;
}

.agenda-context-enter-to,
.agenda-context-leave-from {
    grid-template-rows: 1fr;
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .agenda-context-panel {
        transition: none;
    }
}
</style>