<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';

type Attachment = { id: number; original_name: string; size: number };
const props = defineProps<{
    post: { id: number; title: string; category: string; cover_url: string | null; cover_image_alt: string | null; summary: string | null; body: string; is_published: boolean; attachments: Attachment[] } | null;
    categories: string[];
}>();

const form = useForm({
    _method: props.post ? 'put' : 'post',
    title: props.post?.title ?? '',
    category: props.post?.category ?? 'Announcement',
    summary: props.post?.summary ?? '',
    body: props.post?.body ?? '',
    cover_image: null as File | null,
    cover_image_alt: props.post?.cover_image_alt ?? '',
    remove_cover_image: false,
    is_published: props.post?.is_published ?? true,
    attachments: [] as File[],
});

const newCoverUrl = ref<string | null>(null);
const coverPreview = computed(() => newCoverUrl.value ?? (form.remove_cover_image ? null : props.post?.cover_url ?? null));
function onCover(event: Event) {
    if (newCoverUrl.value) URL.revokeObjectURL(newCoverUrl.value);
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.cover_image = file;
    newCoverUrl.value = file ? URL.createObjectURL(file) : null;
}
onUnmounted(() => { if (newCoverUrl.value) URL.revokeObjectURL(newCoverUrl.value); });

const sizeLabel = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const attachmentError = () => Object.entries(form.errors).find(([key]) => key === 'attachments' || key.startsWith('attachments.'))?.[1];

function onFiles(event: Event) {
    form.attachments = Array.from((event.target as HTMLInputElement).files ?? []);
}
function removeAttachment(attachment: Attachment) {
    if (props.post && confirm(`Remove ${attachment.original_name}?`)) {
        router.delete(route('admin.news.attachments.destroy', [props.post.id, attachment.id]), { preserveScroll: true });
    }
}
function save() {
    form.post(props.post ? route('admin.news.update', props.post.id) : route('admin.news.store'), { forceFormData: true });
}
</script>

<template>
    <Head :title="post ? 'Edit news' : 'New news post'" />
    <AuthenticatedLayout>
        <PageHeader :title="post ? 'Edit news post' : 'New news post'" description="Published posts and their documents are visible to all signed-in users." />
        <form class="ori-card max-w-3xl space-y-5 p-6" @submit.prevent="save">
            <label class="block"><span class="ori-label">Title</span><input v-model="form.title" class="ori-input" maxlength="255" required /><InputError :message="form.errors.title" /></label>
            <label class="block"><span class="ori-label">Category</span><select v-model="form.category" class="ori-input" required><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select><InputError :message="form.errors.category" /></label>
            <label class="block"><span class="ori-label">Summary (optional)</span><input v-model="form.summary" class="ori-input" maxlength="500" /><InputError :message="form.errors.summary" /></label>
            <label class="block"><span class="ori-label">Content</span><textarea v-model="form.body" rows="10" class="ori-input" required /><InputError :message="form.errors.body" /></label>
            <fieldset class="space-y-3 border-y border-ori-blue-100 py-4">
                <legend class="ori-label">Cover image (optional)</legend>
                <img v-if="coverPreview" :src="coverPreview" :alt="form.cover_image_alt || 'Cover image preview'" class="aspect-video w-full max-w-xl border border-ori-blue-100 object-cover" />
                <input type="file" accept=".jpg,.jpeg,.png,.webp" class="ori-input" @change="onCover" />
                <p class="text-xs text-ori-blue-500">JPG, PNG or WebP, at least 600 × 300 px, up to 5 MB. A wide 16:9 photo works best; it is cropped to fit on cards.</p>
                <InputError :message="form.errors.cover_image" />
                <label v-if="coverPreview" class="block"><span class="ori-label">Image description (for screen readers) <span aria-hidden="true" class="text-rose-600">*</span></span><input v-model="form.cover_image_alt" class="ori-input" maxlength="255" placeholder="e.g. LNU education students receiving the national research award" required /><InputError :message="form.errors.cover_image_alt" /></label>
                <label v-if="post?.cover_url && !newCoverUrl" class="flex items-center gap-2 text-sm"><input v-model="form.remove_cover_image" type="checkbox" /> Remove current cover image</label>
            </fieldset>
            <div>
                <span class="ori-label">Documents</span>
                <ul v-if="post?.attachments.length" class="mb-3 divide-y divide-ori-blue-100 border-y border-ori-blue-100 text-sm">
                    <li v-for="attachment in post.attachments" :key="attachment.id" class="flex items-center justify-between gap-3 py-2">
                        <span>{{ attachment.original_name }} <span class="text-xs text-ori-blue-400">{{ sizeLabel(attachment.size) }}</span></span>
                        <button type="button" class="text-sm font-semibold text-rose-700" @click="removeAttachment(attachment)">Remove</button>
                    </li>
                </ul>
                <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg" class="ori-input" @change="onFiles" />
                <p class="mt-1 text-xs text-ori-blue-500">Up to 10 files, 20 MB each.</p>
                <InputError :message="attachmentError()" />
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_published" type="checkbox" /> Publish to faculty</label>
            <div class="flex gap-3">
                <button class="ori-btn-primary" :disabled="form.processing">{{ post ? 'Save changes' : 'Create post' }}</button>
                <Link :href="route('admin.news.index')" class="ori-btn-ghost">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
