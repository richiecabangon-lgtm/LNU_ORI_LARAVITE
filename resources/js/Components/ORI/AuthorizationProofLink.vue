<script setup lang="ts">
import DocumentPreviewModal from '@/Components/ORI/DocumentPreviewModal.vue';
import { computed, ref } from 'vue';

type Proof = { id: number; has_authorization?: boolean; authorization_kind?: 'image' | 'pdf' | 'file'; authorization_original_name?: string | null };
const props = defineProps<{ author: Proof }>();

const open = ref(false);
const previewFile = computed(() => open.value ? {
    original_name: props.author.authorization_original_name ?? 'Authorization proof',
    kind: props.author.authorization_kind ?? 'file',
    view_url: route('research-authorizations.view', props.author.id),
    download_url: route('research-authorizations.download', props.author.id),
} : null);
</script>

<template>
    <span v-if="author.has_authorization !== false" class="inline-flex items-center gap-3">
        <button v-if="author.authorization_kind !== 'file'" type="button" class="font-semibold text-ori-blue-700 hover:underline" aria-haspopup="dialog" @click="open = true">Preview proof</button>
        <a :href="route('research-authorizations.download', author.id)" class="font-semibold text-ori-blue-700 hover:underline">Download</a>
        <DocumentPreviewModal :file="previewFile" @close="open = false" />
    </span>
</template>
