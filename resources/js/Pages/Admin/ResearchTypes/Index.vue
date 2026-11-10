<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Requirement = { id?: number; name: string; is_required: boolean };
type ResearchType = { id: number; slug: string; name: string; description: string | null; category: string | null; has_authors: boolean; points: number; is_active: boolean; submission_count: number; requirements: Requirement[] };
const props = defineProps<{ researchTypes: ResearchType[] }>();
const showCreate = ref(false);
const createForm = useForm({ slug: '', name: '', category: '', description: '', points: 0, has_authors: true });
const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', category: '', description: '', points: 0, is_active: true, has_authors: true, requirements: [] as Requirement[] });
function createType() { createForm.post(route('admin.research-types.store'), { onSuccess: () => { createForm.reset(); showCreate.value = false; } }); }
function editType(type: ResearchType) { editingId.value = type.id; editForm.clearErrors(); editForm.name = type.name; editForm.category = type.category ?? ''; editForm.description = type.description ?? ''; editForm.points = type.points; editForm.is_active = type.is_active; editForm.has_authors = type.has_authors; editForm.requirements = type.requirements.map((requirement) => ({ ...requirement })); }
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
                    <fieldset class="space-y-3 border-t border-ori-blue-100 pt-4 md:col-span-4">
                        <legend class="ori-label">Evidence requirements</legend>
                        <p class="text-sm text-ori-blue-600">Confirm applicable conditions and alternatives before marking evidence required. Ethics clearance or REC exemption is specific to presentation/publication assistance for studies since December 19, 2023.</p>
                        <details class="text-sm text-ori-blue-700"><summary class="cursor-pointer font-semibold">Brochure evidence reference</summary><ul class="mt-2 list-inside list-disc space-y-1"><li>General involvement: supporting documents; completed research: research paper; ongoing work: work plan and accomplishment report.</li><li>Published research: article and journal cover; activities: role-specific certificate, special order, or invitation.</li><li>Refereeing: communications, article, reviewer certificate, and LNU special order; student thesis: approval sheet and special order; proposal hearing: special order.</li><li>Funded roles: role-specific certifications or special orders; citations: citing article.</li></ul></details>
                        <div v-for="(requirement, index) in editForm.requirements" :key="index" class="flex flex-wrap items-end gap-3">
                            <label class="min-w-0 flex-1"><span class="ori-label">Requirement name</span><input v-model="requirement.name" maxlength="255" required class="ori-input" /></label>
                            <label class="flex items-center gap-2 pb-2"><input v-model="requirement.is_required" type="checkbox" /> Required</label>
                            <button type="button" class="pb-2 text-sm font-semibold text-rose-700" @click="editForm.requirements.splice(index, 1)">Remove</button>
                        </div>
                        <button type="button" class="ori-btn-ghost" @click="editForm.requirements.push({ name: '', is_required: false })">Add requirement</button>
                        <InputError :message="editForm.errors.requirements" />
                        <p v-for="(message, field) in editForm.errors" :key="field" class="text-sm text-rose-700">{{ message }}</p>
                    </fieldset>
                </form>
                <div v-else class="flex flex-wrap items-center justify-between gap-4">
                    <div><h2 class="font-semibold text-ori-blue-900">{{ type.name }} <span class="text-xs font-normal text-ori-blue-400">{{ type.category }}</span></h2><p class="text-sm text-ori-blue-500">{{ type.description || 'No description' }} · {{ type.submission_count }} submissions</p></div>
                    <div class="flex items-center gap-4"><span class="font-semibold tabular-nums">{{ type.points.toFixed(2) }} pts</span><span :class="type.is_active ? 'text-emerald-700' : 'text-slate-500'" class="text-xs font-semibold">{{ type.is_active ? 'Active' : 'Inactive' }}</span><button class="text-sm font-semibold text-ori-blue-700" @click="editType(type)">Edit</button><button class="text-sm font-semibold text-rose-700" @click="deleteType(type)">Delete</button></div>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
