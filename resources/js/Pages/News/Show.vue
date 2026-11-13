<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import DocumentPreviewModal from '@/Components/ORI/DocumentPreviewModal.vue';
import Pagination from '@/Components/ORI/Pagination.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, type ComponentPublicInstance } from 'vue';

type Attachment = { id: number; original_name: string; size: number; kind: 'image' | 'pdf' | 'file' };
type GalleryImage = { id: number; alt_text: string; url: string };
type ReactionKey = 'like' | 'love' | 'haha' | 'wow' | 'sad' | 'angry';
type Comment = { id: number; user_name: string; body: string; created_at: string; is_edited: boolean; can_manage: boolean };
type CommentLink = { url: string | null; label: string; active: boolean };
type PaginatedComments = { data: Comment[]; links: CommentLink[] };
type NewsImage = { id: number | 'cover'; alt_text: string; url: string };
const props = defineProps<{ post: { id: number; title: string; category: string; cover_url: string | null; cover_image_alt: string | null; summary: string | null; body: string; published_at: string | null; gallery_images: GalleryImage[]; attachments: Attachment[]; view_count: number; reaction_counts: Record<ReactionKey, number>; user_reaction: ReactionKey | null; comment_count: number; comments: PaginatedComments } }>();

const publishedLabel = props.post.published_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(props.post.published_at)) : '';
const commentDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const mediaImages = computed<NewsImage[]>(() => [
    ...(props.post.cover_url ? [{ id: 'cover' as const, url: props.post.cover_url, alt_text: props.post.cover_image_alt ?? '' }] : []),
    ...props.post.gallery_images,
]);
const mediaGridClass = computed(() => {
    if (mediaImages.value.length === 1) return 'grid-cols-1';
    if (mediaImages.value.length === 3) return 'grid-cols-2 grid-rows-2';
    if (mediaImages.value.length === 5) return 'grid-cols-2 sm:grid-cols-6';
    if (mediaImages.value.length === 6) return 'grid-cols-2 sm:grid-cols-3';

    return 'grid-cols-2 lg:grid-cols-3';
});
function mediaTileClass(index: number): string {
    if (mediaImages.value.length === 1) return 'aspect-video max-h-[34rem]';
    if (mediaImages.value.length === 2) return 'aspect-[4/3]';
    if (mediaImages.value.length === 3 && index === 0) return 'row-span-2 aspect-square';
    if (mediaImages.value.length === 5) {
        if (index < 2) return 'aspect-square sm:col-span-3';
        if (index === 4) return 'col-span-2 aspect-[2/1] sm:col-span-2 sm:aspect-square';

        return 'aspect-square sm:col-span-2';
    }

    return 'aspect-square';
}
const sizeLabel = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const reactionOptions: Array<{ value: ReactionKey; emoji: string; label: string }> = [
    { value: 'like', emoji: '👍', label: 'Like' },
    { value: 'love', emoji: '❤️', label: 'Love' },
    { value: 'haha', emoji: '😂', label: 'Haha' },
    { value: 'wow', emoji: '😮', label: 'Wow' },
    { value: 'sad', emoji: '😢', label: 'Sad' },
    { value: 'angry', emoji: '😡', label: 'Angry' },
];
const activeReactionOptions = computed(() => reactionOptions.filter((reaction) => props.post.reaction_counts[reaction.value] > 0));
const totalReactionCount = computed(() => activeReactionOptions.value.reduce((total, reaction) => total + props.post.reaction_counts[reaction.value], 0));
const selectedReaction = computed(() => reactionOptions.find((reaction) => reaction.value === props.post.user_reaction));
const reactionPickerOpen = ref(false);
const reactionPickerRoot = ref<HTMLElement | null>(null);
const reactionTrigger = ref<HTMLButtonElement | null>(null);
const reactionOptionButtons = ref<HTMLButtonElement[]>([]);
const commentInput = ref<HTMLTextAreaElement | null>(null);
const editTextarea = ref<HTMLTextAreaElement | null>(null);
let reactionLongPressTimer: ReturnType<typeof setTimeout> | null = null;
let longPressOpenedPicker = false;
let suppressReactionClick = false;
const previewing = ref<Attachment | null>(null);
const previewFile = computed(() => previewing.value && { original_name: previewing.value.original_name, kind: previewing.value.kind, view_url: viewUrl(previewing.value), download_url: downloadUrl(previewing.value) });
const viewUrl = (attachment: Attachment) => route('news.attachments.view', [props.post.id, attachment.id]);
const downloadUrl = (attachment: Attachment) => route('news.attachments.download', [props.post.id, attachment.id]);
const commentForm = useForm({ body: '' });
const editForm = useForm({ body: '' });
const editingCommentId = ref<number | null>(null);

function closeReactionPickerOnOutsidePointer(event: PointerEvent) {
    if (reactionPickerOpen.value && event.target instanceof Node && !reactionPickerRoot.value?.contains(event.target)) {
        reactionPickerOpen.value = false;
    }
}

function closeReactionPickerOnEscape(event: KeyboardEvent) {
    if (reactionPickerOpen.value && event.key === 'Escape') {
        reactionPickerOpen.value = false;
        reactionTrigger.value?.focus();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeReactionPickerOnOutsidePointer);
    document.addEventListener('keydown', closeReactionPickerOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeReactionPickerOnOutsidePointer);
    document.removeEventListener('keydown', closeReactionPickerOnEscape);
    if (reactionLongPressTimer) window.clearTimeout(reactionLongPressTimer);
});

function react(reaction: ReactionKey) {
    if (props.post.user_reaction === reaction) {
        router.delete(route('news.reactions.destroy', props.post.id), { preserveScroll: true });
        return;
    }

    router.post(route('news.reactions.store', props.post.id), { reaction }, { preserveScroll: true });
}

function startReactionLongPress() {
    longPressOpenedPicker = false;
    reactionLongPressTimer = window.setTimeout(() => {
        reactionPickerOpen.value = true;
        longPressOpenedPicker = true;
    }, 500);
}

function finishReactionLongPress() {
    if (reactionLongPressTimer) window.clearTimeout(reactionLongPressTimer);
    reactionLongPressTimer = null;

    if (longPressOpenedPicker) {
        suppressReactionClick = true;
        window.setTimeout(() => { suppressReactionClick = false; }, 350);
    }
}

function quickReact() {
    if (suppressReactionClick) {
        suppressReactionClick = false;
        return;
    }

    react('like');
}

function openReactionPicker() {
    reactionPickerOpen.value = true;
    nextTick(() => reactionOptionButtons.value[0]?.focus());
}

function setReactionOptionButton(element: Element | ComponentPublicInstance | null, index: number) {
    if (element instanceof HTMLButtonElement) reactionOptionButtons.value[index] = element;
}

function chooseReaction(reaction: ReactionKey) {
    react(reaction);
    reactionPickerOpen.value = false;
    reactionTrigger.value?.focus();
}

function resizeTextarea(textarea: HTMLTextAreaElement | null) {
    if (!textarea) return;

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 192)}px`;
    textarea.style.overflowY = textarea.scrollHeight > 192 ? 'auto' : 'hidden';
}

function resizeTextareaFromEvent(event: Event) {
    resizeTextarea(event.currentTarget as HTMLTextAreaElement);
}

function setEditTextarea(element: Element | ComponentPublicInstance | null) {
    editTextarea.value = element instanceof HTMLTextAreaElement ? element : null;
}

function submitComment() {
    commentForm.post(route('news.comments.store', props.post.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            nextTick(() => resizeTextarea(commentInput.value));
        },
    });
}

function startEditing(comment: Comment) {
    editingCommentId.value = comment.id;
    editForm.body = comment.body;
    editForm.clearErrors();
    nextTick(() => resizeTextarea(editTextarea.value));
}

function saveComment(comment: Comment) {
    editForm.put(route('news.comments.update', [props.post.id, comment.id]), {
        preserveScroll: true,
        onSuccess: () => { editingCommentId.value = null; },
    });
}

function removeComment(comment: Comment) {
    if (window.confirm('Remove this comment?')) {
        router.delete(route('news.comments.destroy', [props.post.id, comment.id]), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="post.title" />
    <AuthenticatedLayout>
        <PageHeader :title="post.title" :description="`${post.category} · ${publishedLabel}`">
            <template #actions><Link :href="route('news.index')" class="ori-btn-ghost">All news</Link></template>
        </PageHeader>
        <article class="max-w-4xl">
            <p v-if="post.summary" class="mb-6 max-w-3xl border-s-2 border-ori-yellow-400 ps-4 text-lg font-medium leading-relaxed text-ori-blue-700">{{ post.summary }}</p>
            <section v-if="mediaImages.length" class="mb-8 grid gap-1 overflow-hidden bg-ori-blue-100" :class="mediaGridClass" aria-label="News photos">
                <figure v-for="(image, index) in mediaImages" :key="image.id" class="min-w-0 overflow-hidden bg-ori-blue-50" :class="mediaTileClass(index)">
                    <img :src="image.url" :alt="image.alt_text" :loading="index === 0 ? 'eager' : 'lazy'" class="h-full w-full object-contain" />
                </figure>
            </section>
            <div class="max-w-3xl whitespace-pre-line text-base leading-8 text-ori-blue-900">{{ post.body }}</div>
            <section class="mt-10 max-w-3xl border-y border-ori-blue-100 py-5" aria-label="News engagement">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-medium text-ori-blue-700"><span class="font-semibold text-ori-blue-900">{{ new Intl.NumberFormat().format(post.view_count) }}</span> {{ post.view_count === 1 ? 'view' : 'views' }}</p>
                    <div class="ms-auto flex min-w-0 flex-wrap items-center justify-end gap-3">
                        <div ref="reactionPickerRoot" class="relative">
                        <button
                            ref="reactionTrigger"
                            type="button"
                            class="inline-flex min-h-10 items-center gap-2 rounded-full px-3 text-sm font-semibold text-ori-blue-700 transition-colors hover:bg-ori-blue-50 hover:text-ori-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ori-blue-700 select-none"
                            :aria-label="selectedReaction ? `Your reaction is ${selectedReaction.label}. Hold or use the down arrow to change it.` : 'Like this announcement, or hold to choose a reaction.'"
                            aria-haspopup="true"
                            :aria-expanded="reactionPickerOpen"
                            title="Click to Like; press and hold or use the down arrow for more reactions"
                            @pointerdown="startReactionLongPress"
                            @pointerup="finishReactionLongPress"
                            @pointerleave="finishReactionLongPress"
                            @pointercancel="finishReactionLongPress"
                            @keydown.arrow-down.prevent="openReactionPicker"
                            @click="quickReact"
                        >
                            <span aria-hidden="true">{{ selectedReaction?.emoji ?? '👍' }}</span>
                            <span>{{ selectedReaction?.label ?? 'Like' }}</span>
                        </button>
                        <div v-if="reactionPickerOpen" class="absolute bottom-full right-0 z-30 mb-2 flex gap-1 border border-ori-blue-100 bg-white p-2 shadow-lg" role="group" aria-label="Choose a reaction">
                            <button
                                v-for="(reaction, index) in reactionOptions"
                                :key="reaction.value"
                                :ref="(element) => setReactionOptionButton(element, index)"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center text-xl transition hover:-translate-y-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ori-blue-700"
                                :aria-label="`${reaction.label}, ${post.reaction_counts[reaction.value]} reactions`"
                                :aria-pressed="post.user_reaction === reaction.value"
                                :title="reaction.label"
                                @click="chooseReaction(reaction.value)"
                            >
                                <span aria-hidden="true">{{ reaction.emoji }}</span>
                            </button>
                        </div>
                        </div>
                        <div v-if="totalReactionCount" class="flex flex-wrap items-center justify-end gap-2 text-sm text-ori-blue-600" aria-label="Reaction totals">
                            <span v-for="reaction in activeReactionOptions" :key="reaction.value" :aria-label="`${reaction.label}: ${post.reaction_counts[reaction.value]}`">
                                <span aria-hidden="true">{{ reaction.emoji }}</span> {{ post.reaction_counts[reaction.value] }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
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
            <section class="mt-10 max-w-3xl" aria-labelledby="news-comments-heading">
                <h2 id="news-comments-heading" class="font-display text-xl font-semibold text-ori-blue-900">Comments <span class="text-base font-medium text-ori-blue-500">({{ post.comment_count }})</span></h2>
                <form class="mt-4 space-y-2" @submit.prevent="submitComment">
                    <label for="news-comment" class="ori-label">Add a comment</label>
                    <textarea ref="commentInput" id="news-comment" v-model="commentForm.body" class="ori-input min-h-11 max-h-48 resize-none overflow-y-hidden" rows="1" maxlength="2000" required placeholder="Share a thoughtful comment" @input="resizeTextareaFromEvent" />
                    <InputError :message="commentForm.errors.body" />
                    <div class="flex justify-end"><button type="submit" class="ori-btn-primary" :disabled="commentForm.processing">{{ commentForm.processing ? 'Posting…' : 'Post comment' }}</button></div>
                </form>
                <p v-if="!post.comments.data.length" class="mt-5 border-y border-ori-blue-100 py-5 text-sm text-ori-blue-500">No comments yet.</p>
                <ul v-else class="mt-5 divide-y divide-ori-blue-100 border-y border-ori-blue-100">
                    <li v-for="comment in post.comments.data" :key="comment.id" class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-ori-blue-900">{{ comment.user_name }}</p>
                                <time class="text-xs text-ori-blue-500" :datetime="comment.created_at">{{ commentDate(comment.created_at) }}<span v-if="comment.is_edited"> · edited</span></time>
                            </div>
                            <div v-if="comment.can_manage" class="flex gap-3 text-sm font-semibold text-ori-blue-700">
                                <button v-if="editingCommentId !== comment.id" type="button" class="hover:underline" @click="startEditing(comment)">Edit</button>
                                <button type="button" class="text-rose-700 hover:underline" @click="removeComment(comment)">Remove</button>
                            </div>
                        </div>
                        <form v-if="editingCommentId === comment.id" class="mt-3 space-y-2" @submit.prevent="saveComment(comment)">
                            <textarea :ref="setEditTextarea" v-model="editForm.body" class="ori-input min-h-11 max-h-48 resize-none overflow-y-hidden" rows="1" maxlength="2000" required aria-label="Edit comment" @input="resizeTextareaFromEvent" />
                            <InputError :message="editForm.errors.body" />
                            <div class="flex gap-2">
                                <button type="submit" class="ori-btn-primary" :disabled="editForm.processing">Save</button>
                                <button type="button" class="ori-btn-ghost" @click="editingCommentId = null">Cancel</button>
                            </div>
                        </form>
                        <p v-else class="mt-2 whitespace-pre-line text-sm leading-6 text-ori-blue-800">{{ comment.body }}</p>
                    </li>
                </ul>
                <Pagination :links="post.comments.links" />
            </section>
        </article>
        <DocumentPreviewModal :file="previewFile" @close="previewing = null" />
    </AuthenticatedLayout>
</template>
