<script setup lang="ts">
import DocumentPreviewModal from '@/Components/ORI/DocumentPreviewModal.vue';
import { ref } from 'vue';

type ViewerAttachment = { id: number; original_name: string; size: number; kind: 'image' | 'pdf' | 'file'; view_url: string; download_url: string };
defineProps<{ attachments: ViewerAttachment[]; title?: string }>();

const previewing = ref<ViewerAttachment | null>(null);
const sizeLabel = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <section v-if="attachments.length" aria-labelledby="attachment-viewer-heading">
        <h3 id="attachment-viewer-heading" class="text-sm font-semibold text-ori-blue-800">{{ title ?? 'Attachments' }} ({{ attachments.length }})</h3>
        <ul class="mt-2 divide-y divide-ori-blue-100 border-y border-ori-blue-100">
            <li v-for="file in attachments" :key="file.id" class="flex flex-wrap items-center justify-between gap-3 py-2 text-sm">
                <span class="min-w-0 break-words text-ori-blue-900">{{ file.original_name }} <span class="text-xs text-ori-blue-400">{{ sizeLabel(file.size) }}</span></span>
                <span class="flex items-center gap-4 font-semibold text-ori-blue-700">
                    <button v-if="file.kind !== 'file'" type="button" class="hover:underline" aria-haspopup="dialog" @click="previewing = file">Preview</button>
                    <span v-else class="text-xs font-normal text-ori-blue-400">No preview</span>
                    <a :href="file.download_url" class="hover:underline">Download</a>
                </span>
            </li>
        </ul>
        <DocumentPreviewModal :file="previewing" @close="previewing = null" />
    </section>
</template>
