<script setup lang="ts">
import DocumentPreviewModal from '@/Components/ORI/DocumentPreviewModal.vue';
import { computed, onUnmounted, ref } from 'vue';

const props = defineProps<{ file: File | null }>();

const url = ref<string | null>(null);
const kind = computed<'image' | 'pdf' | 'file'>(() => {
    const extension = props.file?.name.split('.').pop()?.toLowerCase() ?? '';
    if (['png', 'jpg', 'jpeg', 'webp'].includes(extension)) return 'image';
    return extension === 'pdf' ? 'pdf' : 'file';
});
const previewFile = computed(() => props.file && url.value ? { original_name: props.file.name, kind: kind.value, view_url: url.value, download_url: url.value, local: true } : null);

function open() {
    if (props.file) url.value = URL.createObjectURL(props.file);
}
function close() {
    if (url.value) URL.revokeObjectURL(url.value);
    url.value = null;
}
onUnmounted(close);
</script>

<template>
    <span v-if="file">
        <button v-if="kind !== 'file'" type="button" class="text-xs font-semibold text-ori-blue-700 hover:underline" aria-haspopup="dialog" @click="open">Preview</button>
        <span v-else class="text-xs text-ori-blue-400">No preview</span>
        <DocumentPreviewModal :file="previewFile" @close="close" />
    </span>
</template>
