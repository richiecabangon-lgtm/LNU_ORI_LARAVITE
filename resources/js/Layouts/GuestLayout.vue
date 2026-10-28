<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const logoUrl = computed(() => (page.props.app as { logo_url?: string | null })?.logo_url ?? null);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-[radial-gradient(circle_at_top,_#fff9e6,_transparent_30%),linear-gradient(180deg,#ffffff_0%,#eaf1fb_100%)] px-4 py-10">
        <div class="mb-6 text-center">
            <Link href="/" class="inline-flex flex-col items-center gap-3">
                <div  class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-soft">
                    <img v-if="logoUrl" :src="logoUrl" alt="Office of Research and Innovation logo"  class="block size-full rounded-full bg-white object-contain p-1" />
                    <ApplicationLogo v-else class="size-9 fill-current" />
                </div>
                <div>
                    <p class="font-display text-2xl font-semibold text-ori-blue-900">LNU ORI System</p>
                    <p class="text-xs uppercase tracking-[0.2em] text-ori-blue-500">
                        Office of Research and Innovation
                    </p>
                </div>
            </Link>
        </div>

        <div class="w-full overflow-hidden rounded-2xl border border-ori-blue-100 bg-white px-6 py-6 shadow-soft sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
