<script setup lang="ts">
import { ref, watch } from 'vue';

type PreviewFile = { original_name: string; kind: 'image' | 'pdf' | 'file'; view_url: string; download_url: string; local?: boolean };
const props = defineProps<{ file: PreviewFile | null }>();
const emit = defineEmits<{ close: [] }>();

const dialog = ref<HTMLDialogElement | null>(null);

watch(() => props.file, (file) => {
    if (file && !dialog.value?.open) dialog.value?.showModal();
    if (!file && dialog.value?.open) dialog.value.close();
});

function onBackdropClick(event: MouseEvent) {
    if (event.target === dialog.value) emit('close');
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" class="m-auto h-[90vh] w-[min(72rem,95vw)] max-w-none overflow-hidden border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-900/70" :aria-label="file ? `Preview of ${file.original_name}` : 'Document preview'" @click="onBackdropClick" @close="emit('close')">
            <div v-if="file" class="flex h-full flex-col">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ori-blue-100 px-4 py-3">
                    <h2 class="min-w-0 break-words text-sm font-semibold text-ori-blue-900">{{ file.original_name }}</h2>
                    <div class="flex items-center gap-4 text-sm font-semibold text-ori-blue-700">
                        <a :href="file.view_url" target="_blank" rel="noopener" class="hover:underline">Open in new tab</a>
                        <a :href="file.download_url" :download="file.local ? file.original_name : undefined" class="hover:underline">Download</a>
                        <button type="button" class="ori-btn-ghost" autofocus @click="emit('close')">Close</button>
                    </div>
                </header>
                <div class="min-h-0 flex-1 overflow-auto bg-ori-blue-50">
                    <img v-if="file.kind === 'image'" :src="file.view_url" :alt="`Preview of ${file.original_name}`" class="mx-auto max-h-full max-w-full object-contain" />
                    <object v-else-if="file.kind === 'pdf'" :data="`${file.view_url}#view=FitH`" type="application/pdf" :aria-label="`Preview of ${file.original_name}`" class="h-full w-full">
                        <p class="p-4 text-sm text-ori-blue-600">This browser cannot display the PDF inline. <a :href="file.download_url" class="font-semibold underline">Download it</a>.</p>
                    </object>
                </div>
            </div>
        </dialog>
    </Teleport>
</template>
