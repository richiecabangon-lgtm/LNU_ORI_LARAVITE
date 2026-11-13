<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';

type Attachment = { id: number; original_name: string; size: number };
type GalleryImage = { id: number; original_name: string; alt_text: string; url: string };
const props = defineProps<{
    post: { id: number; title: string; category: string; cover_url: string | null; cover_image_alt: string | null; summary: string | null; body: string; is_published: boolean; gallery_images: GalleryImage[]; attachments: Attachment[] } | null;
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
    gallery_images: [] as File[],
    gallery_image_alts: [] as string[],
    is_published: props.post?.is_published ?? true,
    attachments: [] as File[],
});

const newCoverUrl = ref<string | null>(null);
const coverPreview = computed(() => newCoverUrl.value ?? (form.remove_cover_image ? null : props.post?.cover_url ?? null));
const selectedGalleryImages = ref<Array<{ file: File; previewUrl: string; alt: string }>>([]);
function onCover(event: Event) {
    if (newCoverUrl.value) URL.revokeObjectURL(newCoverUrl.value);
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.cover_image = file;
    newCoverUrl.value = file ? URL.createObjectURL(file) : null;
}
onUnmounted(() => {
    if (newCoverUrl.value) URL.revokeObjectURL(newCoverUrl.value);
    selectedGalleryImages.value.forEach((image) => URL.revokeObjectURL(image.previewUrl));
});

const sizeLabel = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const attachmentError = () => Object.entries(form.errors).find(([key]) => key === 'attachments' || key.startsWith('attachments.'))?.[1];

function onFiles(event: Event) {
    form.attachments = Array.from((event.target as HTMLInputElement).files ?? []);
}
function onGalleryImages(event: Event) {
    const input = event.currentTarget as HTMLInputElement;
    const availableSlots = 10 - (props.post?.gallery_images.length ?? 0) - selectedGalleryImages.value.length;
    const newImages = Array.from(input.files ?? []).slice(0, Math.max(0, availableSlots));
    selectedGalleryImages.value.push(...newImages.map((file) => ({ file, previewUrl: URL.createObjectURL(file), alt: '' })));
    form.gallery_images = selectedGalleryImages.value.map((image) => image.file);
    input.value = '';
}
function removeSelectedGalleryImage(index: number) {
    URL.revokeObjectURL(selectedGalleryImages.value[index].previewUrl);
    selectedGalleryImages.value.splice(index, 1);
    form.gallery_images = selectedGalleryImages.value.map((image) => image.file);
}
function removeGalleryImage(image: GalleryImage) {
    if (props.post && confirm(`Remove ${image.original_name} from the photo gallery?`)) {
        router.delete(route('admin.news.attachments.destroy', [props.post.id, image.id]), { preserveScroll: true });
    }
}
function removeAttachment(attachment: Attachment) {
    if (props.post && confirm(`Remove ${attachment.original_name}?`)) {
        router.delete(route('admin.news.attachments.destroy', [props.post.id, attachment.id]), { preserveScroll: true });
    }
}
function save() {
    form.gallery_image_alts = selectedGalleryImages.value.map((image) => image.alt);
    form.post(props.post ? route('admin.news.update', props.post.id) : route('admin.news.store'), { forceFormData: true });
}
</script>

<template>
    <Head :title="post ? 'Edit news' : 'New news post'" />
    <AuthenticatedLayout>
        <PageHeader :title="post ? 'Edit news post' : 'New news post'" description="Published posts, photo galleries, and documents are visible to all signed-in users." />
        <form class="ori-card max-w-3xl space-y-5 p-6" @submit.prevent="save">
            <label class="block"><span class="ori-label">Title</span><input v-model="form.title" class="ori-input" maxlength="255" required /><InputError :message="form.errors.title" /></label>
            <label class="block"><span class="ori-label">Category</span><select v-model="form.category" class="ori-input" required><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select><InputError :message="form.errors.category" /></label>
            <label class="block"><span class="ori-label">Summary (optional)</span><input v-model="form.summary" class="ori-input" maxlength="500" /><InputError :message="form.errors.summary" /></label>
            <label class="block"><span class="ori-label">Content</span><textarea v-model="form.body" rows="10" class="ori-input" required /><InputError :message="form.errors.body" /></label>
            <fieldset class="space-y-3 border-y border-ori-blue-100 py-4">
                <legend class="ori-label">Primary cover image (optional)</legend>
                <img v-if="coverPreview" :src="coverPreview" :alt="form.cover_image_alt || 'Cover image preview'" class="aspect-video w-full max-w-xl border border-ori-blue-100 object-cover" />
                <input type="file" accept=".jpg,.jpeg,.png,.webp" class="ori-input" @change="onCover" />
                <p class="text-xs text-ori-blue-500">JPG, PNG or WebP, at least 600 × 300 px, up to 5 MB. A wide 16:9 photo works best; it is cropped to fit on cards.</p>
                <InputError :message="form.errors.cover_image" />
                <label v-if="coverPreview" class="block"><span class="ori-label">Image description (for screen readers) <span aria-hidden="true" class="text-rose-600">*</span></span><input v-model="form.cover_image_alt" class="ori-input" maxlength="255" placeholder="e.g. LNU education students receiving the national research award" required /><InputError :message="form.errors.cover_image_alt" /></label>
                <label v-if="post?.cover_url && !newCoverUrl" class="flex items-center gap-2 text-sm"><input v-model="form.remove_cover_image" type="checkbox" /> Remove current cover image</label>
            </fieldset>
            <fieldset class="space-y-3 border-b border-ori-blue-100 pb-5">
                <legend class="ori-label">Additional cover images (optional)</legend>
                <p class="text-xs text-ori-blue-500">Add up to 10 photos to display in the article gallery. JPG, PNG or WebP, at least 600 × 300 px and up to 5 MB each. Add a description for every photo.</p>
                <ul v-if="post?.gallery_images.length" class="grid gap-4 sm:grid-cols-2">
                    <li v-for="image in post.gallery_images" :key="image.id" class="border border-ori-blue-100 p-3">
                        <img :src="image.url" :alt="image.alt_text" class="aspect-[4/3] w-full bg-ori-blue-50 object-contain" />
                        <p class="mt-2 text-xs text-ori-blue-600">{{ image.alt_text }}</p>
                        <button type="button" class="mt-2 text-sm font-semibold text-rose-700 hover:underline" @click="removeGalleryImage(image)">Remove photo</button>
                    </li>
                </ul>
                <ul v-if="selectedGalleryImages.length" class="grid gap-4 sm:grid-cols-2">
                    <li v-for="(image, index) in selectedGalleryImages" :key="image.previewUrl" class="border border-ori-blue-100 p-3">
                        <img :src="image.previewUrl" :alt="image.alt || 'New gallery image preview'" class="aspect-[4/3] w-full bg-ori-blue-50 object-contain" />
                        <label class="mt-3 block"><span class="ori-label">Image description <span aria-hidden="true" class="text-rose-600">*</span></span><input v-model="image.alt" class="ori-input" maxlength="255" required placeholder="Describe what this photo shows" /><InputError :message="form.errors[`gallery_image_alts.${index}`]" /></label>
                        <button type="button" class="mt-2 text-sm font-semibold text-rose-700 hover:underline" @click="removeSelectedGalleryImage(index)">Remove photo</button>
                    </li>
                </ul>
                <label class="block"><span class="sr-only">Choose additional cover images</span><input type="file" multiple accept=".jpg,.jpeg,.png,.webp" class="ori-input" :disabled="(post?.gallery_images.length ?? 0) + selectedGalleryImages.length >= 10" @change="onGalleryImages" /></label>
                <p class="text-xs text-ori-blue-500">{{ (post?.gallery_images.length ?? 0) + selectedGalleryImages.length }} of 10 photos selected.</p>
                <InputError :message="form.errors.gallery_images || form.errors.gallery_image_alts" />
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
