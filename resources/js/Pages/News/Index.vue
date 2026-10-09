<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

type Post = { id: number; title: string; category: string; summary: string | null; cover_url: string | null; cover_image_alt: string | null; published_at: string | null; attachments_count: number };
defineProps<{ posts: Post[] }>();

const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(value)) : '';
</script>

<template>
    <Head title="News" />
    <AuthenticatedLayout>
        <PageHeader title="News & announcements" description="Updates and documents from the Office of Research and Innovation, including incentives and guidelines." />
        <p v-if="!posts.length" class="border-y border-ori-blue-100 py-6 text-sm text-ori-blue-500">No announcements yet.</p>
        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <article v-for="post in posts" :key="post.id" class="group flex flex-col overflow-hidden border border-ori-blue-100 bg-white">
                <Link :href="route('news.show', post.id)" class="block aspect-video bg-ori-blue-50" tabindex="-1" aria-hidden="true">
                    <img v-if="post.cover_url" :src="post.cover_url" alt="" loading="lazy" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />
                </Link>
                <div class="flex flex-1 flex-col p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ post.category }} <span class="font-normal normal-case text-ori-blue-400">· {{ formatDate(post.published_at) }}</span></p>
                    <h2 class="mt-1 font-display text-lg font-semibold text-ori-blue-900"><Link :href="route('news.show', post.id)" class="hover:underline">{{ post.title }}</Link></h2>
                    <p v-if="post.summary" class="mt-1 line-clamp-3 text-sm text-ori-blue-600">{{ post.summary }}</p>
                    <p v-if="post.attachments_count" class="mt-auto pt-3 text-xs font-semibold text-emerald-800">{{ post.attachments_count }} document{{ post.attachments_count === 1 ? '' : 's' }} attached</p>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
