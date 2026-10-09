<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    href: string;
    active?: boolean;
}>();

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-ori-yellow-400 text-sm font-semibold leading-5 text-ori-blue-900 focus:outline-none focus:border-ori-yellow-500 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-ori-blue-500 hover:text-ori-blue-800 hover:border-ori-blue-200 focus:outline-none focus:text-ori-blue-800 focus:border-ori-blue-300 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
