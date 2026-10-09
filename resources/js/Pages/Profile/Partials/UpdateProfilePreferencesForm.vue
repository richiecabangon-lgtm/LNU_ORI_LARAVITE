<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';

const props = defineProps<{ photoUrl: string | null; theme: 'light' | 'dark' }>();
const photoForm = useForm({ profile_photo: null as File | null });
const theme = ref(props.theme);
const uploadError = ref('');
const previewUrl = ref<string | null>(null);
const maxUploadBytes = 1_700_000;

function makeJpegBlob(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
            } else {
                reject(new Error('Image compression failed.'));
            }
        }, 'image/jpeg', quality);
    });
}

async function preparePhoto(file: File): Promise<File> {
    if (file.size <= maxUploadBytes) {
        return file;
    }

    if (file.size > 30 * 1024 * 1024) {
        throw new Error('Choose a photo smaller than 30 MB.');
    }

    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();
        throw new Error('This browser could not prepare the selected photo.');
    }

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    let blob = await makeJpegBlob(canvas, 0.86);
    let quality = 0.78;
    let resizeCount = 0;

    while (blob.size > maxUploadBytes && quality >= 0.5) {
        blob = await makeJpegBlob(canvas, quality);
        quality -= 0.08;
    }

    while (blob.size > maxUploadBytes && resizeCount < 5) {
        canvas.width = Math.max(320, Math.round(canvas.width * 0.8));
        canvas.height = Math.max(320, Math.round(canvas.height * 0.8));
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        blob = await makeJpegBlob(canvas, 0.62);
        resizeCount += 1;
    }

    if (blob.size > maxUploadBytes) {
        bitmap.close();
        throw new Error('The photo could not be reduced enough. Choose a smaller image.');
    }

    const baseName = file.name.replace(/\.[^.]+$/, '') || 'profile-photo';
    bitmap.close();
    return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

async function choosePhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    photoForm.profile_photo = null;
    uploadError.value = '';
    photoForm.clearErrors('profile_photo');

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (!file) {
        return;
    }

    try {
        const preparedPhoto = await preparePhoto(file);
        photoForm.profile_photo = preparedPhoto;
        previewUrl.value = URL.createObjectURL(preparedPhoto);
    } catch (error) {
        uploadError.value = error instanceof Error ? error.message : 'The selected photo could not be prepared.';
        input.value = '';
    }
}

function uploadPhoto() {
    uploadError.value = '';
    photoForm.post(route('profile.photo.update'), {
        forceFormData: true,
        onSuccess: () => {
            photoForm.reset();
            if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        },
    });
}

onUnmounted(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

function removePhoto() { router.delete(route('profile.photo.delete')); }
function saveTheme(value: 'light' | 'dark') {
    theme.value = value;
    router.patch(route('profile.preferences.update'), { theme_preference: value }, { preserveScroll: true });
}
</script>

<template>
    <section class="grid gap-8 md:grid-cols-[minmax(0,1fr)_16rem]">
        <div>
            <h2 class="text-lg font-semibold text-ori-blue-900">Profile photo</h2>
            <p class="mt-1 text-sm text-ori-blue-500">JPG, PNG, or WebP. Large photos are resized before upload.</p>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <img v-if="previewUrl || photoUrl" :src="previewUrl || photoUrl || undefined" alt="Profile photo preview" class="h-20 w-20 rounded-full border border-ori-blue-200 object-cover" />
                <div v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-ori-blue-100 text-xl font-semibold text-ori-blue-800">{{ $page.props.auth.user.name.slice(0, 1) }}</div>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="uploadPhoto">
                    <label class="text-sm font-medium text-ori-blue-700">Choose photo<input type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block max-w-64 text-sm" @change="choosePhoto" /></label>
                    <button class="ori-btn-primary" :disabled="photoForm.processing || !photoForm.profile_photo">{{ photoForm.processing ? 'Uploading…' : 'Upload' }}</button>
                </form>
                <button v-if="photoUrl" type="button" class="text-sm font-semibold text-rose-700" @click="removePhoto">Remove</button>
            </div>
            <p v-if="photoForm.profile_photo" class="mt-2 text-xs text-ori-blue-500">Ready to upload · {{ (photoForm.profile_photo.size / 1024).toFixed(0) }} KB</p>
            <InputError :message="uploadError || photoForm.errors.profile_photo" class="mt-2" />
        </div>
        <fieldset>
            <legend class="text-lg font-semibold text-ori-blue-900">Appearance</legend>
            <p class="mt-1 text-sm text-ori-blue-500">Choose how the workspace looks for your account.</p>
            <div class="mt-4 flex gap-2" role="group" aria-label="Theme preference">
                <button type="button" :aria-pressed="theme === 'light'" :class="theme === 'light' ? 'bg-ori-blue-700 text-white' : 'border border-ori-blue-200 text-ori-blue-700'" class="rounded-md px-4 py-2 text-sm font-semibold" @click="saveTheme('light')">Light</button>
                <button type="button" :aria-pressed="theme === 'dark'" :class="theme === 'dark' ? 'bg-ori-blue-700 text-white' : 'border border-ori-blue-200 text-ori-blue-700'" class="rounded-md px-4 py-2 text-sm font-semibold" @click="saveTheme('dark')">Dark</button>
            </div>
        </fieldset>
    </section>
</template>
