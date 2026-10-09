<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

type Notice = { id: string; read_at: string | null; data: { title: string; message: string; url: string; reference_code: string }; created_at: string | null };
const page = usePage();
const open = ref(false);
const notifications = computed(() => (page.props.notifications as { items: Notice[]; unreadCount: number } | undefined) ?? { items: [], unreadCount: 0 });
let refreshTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    refreshTimer = setInterval(() => {
        router.reload({ only: ['notifications'] });
    }, 30000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});

function openNotification(item: Notice) {
    router.post(route('notifications.read', item.id), {}, {
        preserveScroll: true,
        onSuccess: () => router.visit(item.data.url),
    });
}
function markAllRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}
</script>

<template>
    <div class="relative">
        <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-ori-blue-100 bg-white text-ori-blue-700 hover:bg-ori-blue-50" aria-label="Notifications" :aria-expanded="open" @click="open = !open">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            <span v-if="notifications.unreadCount" class="absolute -right-1 -top-1 min-w-5 rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-5 text-white">{{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}</span>
        </button>
        <div v-if="open" class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] border border-ori-blue-100 bg-white text-ori-blue-900 shadow-xl">
            <div class="flex items-center justify-between border-b border-ori-blue-100 px-4 py-3"><h2 class="text-sm font-semibold">Notifications</h2><button v-if="notifications.unreadCount" type="button" class="text-xs font-semibold text-ori-blue-700 underline" @click="markAllRead">Mark all read</button></div>
            <ul class="max-h-96 divide-y divide-ori-blue-50 overflow-y-auto">
                <li v-for="item in notifications.items" :key="item.id"><button type="button" class="block w-full px-4 py-3 text-left hover:bg-ori-blue-50" :class="item.read_at ? 'opacity-70' : ''" @click="openNotification(item)"><span class="flex items-start gap-2"><span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="item.read_at ? 'bg-transparent' : 'bg-ori-yellow-500'" /><span><strong class="block text-sm">{{ item.data.title }}</strong><span class="mt-1 block text-xs text-ori-blue-600">{{ item.data.message }}</span><span class="mt-1 block text-[11px] text-ori-blue-400">{{ item.data.reference_code }}<span v-if="item.created_at"> · {{ new Date(item.created_at).toLocaleString() }}</span></span></span></span></button></li>
                <li v-if="notifications.items.length === 0" class="px-4 py-8 text-center text-sm text-ori-blue-500">No notifications yet.</li>
            </ul>
        </div>
    </div>
</template>
