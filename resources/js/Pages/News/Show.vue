<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import DocumentPreviewModal from '@/Components/ORI/DocumentPreviewModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Attachment = { id: number; original_name: string; size: number; kind: 'image' | 'pdf' | 'file' };
const props = defineProps<{ post: { id: number; title: string; category: string; cover_url: string | null; cover_image_alt: string | null; summary: string | null; body: string; published_at: string | null; attachments: Attachment[] } }>();

const publishedLabel = props.post.published_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(props.post.published_at)) : '';
const sizeLabel = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const previewing = ref<Attachment | null>(null);
const previewFile = computed(() => previewing.value && { original_name: previewing.value.original_name, kind: previewing.value.kind, view_url: viewUrl(previewing.value), download_url: downloadUrl(previewing.value) });
const viewUrl = (attachment: Attachment) => route('news.attachments.view', [props.post.id, attachment.id]);
const downloadUrl = (attachment: Attachment) => route('news.attachments.download', [props.post.id, attachment.id]);
</script>

<template>
    <Head :title="post.title" />
    <AuthenticatedLayout>
        <PageHeader :title="post.title" :description="publishedLabel">
            <template #actions><Link :href="route('news.index')" class="ori-btn-ghost">All news</Link></template>
        </PageHeader>
        <article class="max-w-3xl">
            <figure v-if="post.cover_url" class="mb-6">
                <img :src="post.cover_url" :alt="post.cover_image_alt ?? ''" class="max-h-[28rem] w-full object-cover" />
            </figure>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ post.category }}</p>
            <p v-if="post.summary" class="mb-4 text-base font-medium text-ori-blue-700">{{ post.summary }}</p>
            <div class="whitespace-pre-line text-sm leading-relaxed text-ori-blue-900">{{ post.body }}</div>
            <section v-if="post.attachments.length" class="mt-8" aria-labelledby="news-documents">
                <h2 id="news-documents" class="font-display text-lg font-semibold text-ori-blue-900">Documents</h2>
                <ul class="mt-2 divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                    <li v-for="attachment in post.attachments" :key="attachment.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <span>{{ attachment.original_name }} <span class="text-xs text-ori-blue-400">{{ sizeLabel(attachment.size) }}</span></span>
                        <span class="flex items-center gap-4 font-semibold text-ori-blue-700">
                            <button v-if="attachment.kind !== 'file'" type="button" class="hover:underline" aria-haspopup="dialog" @click="previewing = attachment">Preview</button>
                            <span v-else class="text-xs font-normal text-ori-blue-400">No preview</span>
                            <a :href="downloadUrl(attachment)" class="hover:underline">Download</a>
                        </span>
                    </li>
                </ul>
            </section>
        </article>
        <DocumentPreviewModal :file="previewFile" @close="previewing = null" />
    </AuthenticatedLayout>
</template>
