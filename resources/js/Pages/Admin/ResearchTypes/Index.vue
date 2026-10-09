<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type ResearchType = { id: number; slug: string; name: string; description: string | null; category: string | null; has_authors: boolean; points: number; is_active: boolean; submission_count: number };
const props = defineProps<{ researchTypes: ResearchType[] }>();
const showCreate = ref(false);
const createForm = useForm({ slug: '', name: '', category: '', description: '', points: 0, has_authors: true });
const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', category: '', description: '', points: 0, is_active: true, has_authors: true });
function createType() { createForm.post(route('admin.research-types.store'), { onSuccess: () => { createForm.reset(); showCreate.value = false; } }); }
function editType(type: ResearchType) { editingId.value = type.id; editForm.name = type.name; editForm.category = type.category ?? ''; editForm.description = type.description ?? ''; editForm.points = type.points; editForm.is_active = type.is_active; editForm.has_authors = type.has_authors; }
function saveType(type: ResearchType) { editForm.put(route('admin.research-types.update', type.id), { onSuccess: () => { editingId.value = null; } }); }
function deleteType(type: ResearchType) { if (confirm(`Delete ${type.name}? Types used by submissions can only be deactivated.`)) router.delete(route('admin.research-types.destroy', type.id)); }
</script>

<template>
    <Head title="Research Involvement Types" />
    <AuthenticatedLayout>
        <PageHeader title="Research involvement types" description="Manage the types faculty can select and the default point value for each type.">
            <template #actions><button class="ori-btn-accent" type="button" @click="showCreate = !showCreate">{{ showCreate ? 'Close' : 'Add type' }}</button></template>
        </PageHeader>
        <form v-if="showCreate" class="mb-6 grid gap-4 border-y border-ori-blue-100 py-5 md:grid-cols-2" @submit.prevent="createType">
            <label><span class="ori-label">Type name</span><input v-model="createForm.name" class="ori-input" required /><InputError :message="createForm.errors.name" /></label>
            <label><span class="ori-label">Key (optional)</span><input v-model="createForm.slug" class="ori-input" placeholder="generated-from-name" /><InputError :message="createForm.errors.slug" /></label>
            <label><span class="ori-label">Category</span><input v-model="createForm.category" class="ori-input" maxlength="100" placeholder="e.g. Published research" /><InputError :message="createForm.errors.category" /></label>
            <label><span class="ori-label">Points per output</span><input v-model.number="createForm.points" type="number" min="0" step="0.01" class="ori-input" required /><InputError :message="createForm.errors.points" /></label>
            <label class="flex items-center gap-2 md:col-span-2"><input v-model="createForm.has_authors" type="checkbox" /> Ask for paper authors (turn off for roles such as reviewer, adviser or panel member)</label>
            <label class="md:col-span-2"><span class="ori-label">Description</span><textarea v-model="createForm.description" rows="2" class="ori-input" /></label>
            <button class="ori-btn-primary md:col-span-2" :disabled="createForm.processing">Add research type</button>
        </form>
        <div class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
            <article v-for="type in researchTypes" :key="type.id" class="py-4">
                <form v-if="editingId === type.id" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_10rem_auto_auto] md:items-end" @submit.prevent="saveType(type)">
                    <label><span class="ori-label">Name</span><input v-model="editForm.name" class="ori-input" required /></label>
                    <label><span class="ori-label">Points</span><input v-model.number="editForm.points" type="number" min="0" step="0.01" class="ori-input" required /></label>
                    <label class="flex items-center gap-2 pb-2"><input v-model="editForm.is_active" type="checkbox" /> Active</label>
                    <div class="flex gap-2"><button class="ori-btn-primary">Save</button><button class="ori-btn-ghost" type="button" @click="editingId = null">Cancel</button></div>
                    <label class="flex items-center gap-2 md:col-span-4"><input v-model="editForm.has_authors" type="checkbox" /> Ask for paper authors</label>
                    <label class="md:col-span-4"><span class="ori-label">Category</span><input v-model="editForm.category" class="ori-input" maxlength="100" /></label>
                    <label class="md:col-span-4"><span class="ori-label">Description</span><textarea v-model="editForm.description" rows="2" class="ori-input" /></label>
                </form>
                <div v-else class="flex flex-wrap items-center justify-between gap-4">
                    <div><h2 class="font-semibold text-ori-blue-900">{{ type.name }} <span class="text-xs font-normal text-ori-blue-400">{{ type.category }}</span></h2><p class="text-sm text-ori-blue-500">{{ type.description || 'No description' }} · {{ type.submission_count }} submissions</p></div>
                    <div class="flex items-center gap-4"><span class="font-semibold tabular-nums">{{ type.points.toFixed(2) }} pts</span><span :class="type.is_active ? 'text-emerald-700' : 'text-slate-500'" class="text-xs font-semibold">{{ type.is_active ? 'Active' : 'Inactive' }}</span><button class="text-sm font-semibold text-ori-blue-700" @click="editType(type)">Edit</button><button class="text-sm font-semibold text-rose-700" @click="deleteType(type)">Delete</button></div>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
