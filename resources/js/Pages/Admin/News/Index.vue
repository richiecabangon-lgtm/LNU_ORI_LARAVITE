<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';

type Post = { id: number; title: string; category: string; cover_url: string | null; summary: string | null; is_published: boolean; published_at: string | null; attachments_count: number };
defineProps<{ posts: Post[] }>();

const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : 'Not published';
function deletePost(post: Post) {
    if (confirm(`Delete "${post.title}" and its documents?`)) router.delete(route('admin.news.destroy', post.id));
}
</script>

<template>
    <Head title="News & Documents" />
    <AuthenticatedLayout>
        <PageHeader title="News & documents" description="Post announcements and documents, such as incentive guidelines, for faculty to read.">
            <template #actions><Link :href="route('admin.news.create')" class="ori-btn-accent">New post</Link></template>
        </PageHeader>
        <p v-if="!posts.length" class="border-y border-ori-blue-100 py-6 text-sm text-ori-blue-500">No news posted yet.</p>
        <div v-else class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
            <article v-for="post in posts" :key="post.id" class="flex flex-wrap items-center justify-between gap-4 py-4">
                <div class="flex min-w-0 items-center gap-4">
                    <img v-if="post.cover_url" :src="post.cover_url" alt="" class="hidden h-16 w-28 shrink-0 object-cover sm:block" />
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ post.category }}</p>
                    <h2 class="font-semibold text-ori-blue-900">{{ post.title }}</h2>
                    <p class="text-sm text-ori-blue-500">{{ post.summary || 'No summary' }}</p>
                    <p class="mt-1 text-xs text-ori-blue-400">{{ post.attachments_count }} document{{ post.attachments_count === 1 ? '' : 's' }} · {{ formatDate(post.published_at) }}</p>
                </div>
                </div>
                <div class="flex items-center gap-4">
                    <span :class="post.is_published ? 'text-emerald-700' : 'text-slate-500'" class="text-xs font-semibold">{{ post.is_published ? 'Published' : 'Draft' }}</span>
                    <Link :href="route('admin.news.edit', post.id)" class="text-sm font-semibold text-ori-blue-700">Edit</Link>
                    <button type="button" class="text-sm font-semibold text-rose-700" @click="deletePost(post)">Delete</button>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
