<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}>();

const visibleLinks = computed(() =>
    props.links.filter((link) => link.url !== null || link.label === '...'),
);
</script>

<template>
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap items-center gap-2">
        <template v-for="(link, index) in visibleLinks" :key="index">
            <component
                :is="link.url ? 'a' : 'span'"
                :href="link.url || undefined"
                class="rounded-lg px-3 py-1.5 text-sm"
                :class="
                    link.active
                        ? 'bg-ori-blue-600 text-white'
                        : link.url
                          ? 'bg-white text-ori-blue-700 ring-1 ring-ori-blue-100 hover:bg-ori-blue-50'
                          : 'text-ori-blue-300'
                "
                v-html="link.label"
            />
        </template>
    </nav>
</template>
