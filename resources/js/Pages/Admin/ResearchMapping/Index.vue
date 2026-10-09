<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type Agenda = { id: number; name: string; description: string | null; image_url: string | null; is_active: boolean };
type Sdg = { id: number; number: number; name: string; description: string | null; color: string | null; image_url: string | null; is_active: boolean };
const props = defineProps<{ agendas: Agenda[]; sdgs: Sdg[] }>();
const showAgendaForm = ref(false);
const form = useForm({ name: '', description: '', image: null as File | null });
const agendaEdits = reactive<Record<number, { name: string; description: string; is_active: boolean; image: File | null; imagePreview: string | null }>>({});
const sdgEdits = reactive<Record<number, { number: number; name: string; description: string; color: string; is_active: boolean; image: File | null; imagePreview: string | null }>>(Object.fromEntries(props.sdgs.map((item) => [item.id, { number: item.number, name: item.name, description: item.description ?? '', color: item.color ?? '#1e4d8c', is_active: item.is_active, image: null, imagePreview: item.image_url }])));
function setAgendaImage(event: Event, agendaId?: number) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (agendaId === undefined) {
        form.image = file;
        return;
    }
    const edit = agendaEdits[agendaId];
    edit.image = file;
    edit.imagePreview = file ? URL.createObjectURL(file) : props.agendas.find((agenda) => agenda.id === agendaId)?.image_url ?? null;
}
function setSdgImage(event: Event, sdg: Sdg) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    sdgEdits[sdg.id].image = file;
    sdgEdits[sdg.id].imagePreview = file ? URL.createObjectURL(file) : sdg.image_url;
}
function saveAgenda() { form.post(route('admin.research-agendas.store'), { forceFormData: true, onSuccess: () => { form.reset(); showAgendaForm.value = false; } }); }
function editAgenda(item: Agenda) { agendaEdits[item.id] = { name: item.name, description: item.description ?? '', is_active: item.is_active, image: null, imagePreview: item.image_url }; }
function saveAgendaEdit(item: Agenda) { router.post(route('admin.research-agendas.update', item.id), { ...agendaEdits[item.id], _method: 'put' }, { forceFormData: true }); }
function saveSdg(item: Sdg) { router.post(route('admin.sdgs.update', item.id), { ...sdgEdits[item.id], _method: 'put' }, { forceFormData: true }); }
function removeAgenda(item: Agenda) { if (confirm(`Delete ${item.name}?`)) router.delete(route('admin.research-agendas.destroy', item.id)); }
</script>

<template>
    <Head title="SDG and Research Agendas" />
    <AuthenticatedLayout>
        <PageHeader title="SDGs and research agendas" description="Maintain the research mapping choices available when faculty submit an output." />
        <section class="border-y border-ori-blue-100 py-5">
            <div class="mb-4 flex items-center justify-between gap-4"><div><h2 class="font-display text-lg font-semibold text-ori-blue-900">Research agendas</h2><p class="text-sm text-ori-blue-500">Create the institution's current research priorities.</p></div><button class="ori-btn-accent" @click="showAgendaForm = !showAgendaForm">{{ showAgendaForm ? 'Close' : 'Add agenda' }}</button></div>
            <form v-if="showAgendaForm" class="mb-4 grid gap-3 border-b border-ori-blue-100 pb-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(12rem,1fr)_auto] sm:items-end" @submit.prevent="saveAgenda"><label><span class="ori-label">Name</span><input v-model="form.name" class="ori-input" required /></label><label><span class="ori-label">Description</span><input v-model="form.description" class="ori-input" /></label><label><span class="ori-label">Image (JPG, PNG, WebP; max 4 MB)</span><input type="file" accept="image/jpeg,image/png,image/webp" class="ori-input" @change="setAgendaImage($event)" /></label><button class="ori-btn-primary" :disabled="form.processing">Save</button></form>
            <div class="divide-y divide-ori-blue-100">
                <div v-for="agenda in agendas" :key="agenda.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <form v-if="agendaEdits[agenda.id]" class="grid flex-1 gap-3 sm:grid-cols-[auto_minmax(8rem,1fr)_minmax(10rem,2fr)_minmax(12rem,1fr)_auto_auto] sm:items-center" @submit.prevent="saveAgendaEdit(agenda)"><img v-if="agendaEdits[agenda.id].imagePreview" :src="agendaEdits[agenda.id].imagePreview ?? undefined" :alt="`${agenda.name} image preview`" class="h-12 w-12 rounded object-cover" /><span v-else class="h-12 w-12 rounded bg-ori-blue-50" aria-hidden="true"></span><input v-model="agendaEdits[agenda.id].name" class="ori-input" required /><input v-model="agendaEdits[agenda.id].description" class="ori-input" /><label class="text-xs"><span class="ori-label">Replace image</span><input type="file" accept="image/jpeg,image/png,image/webp" class="block max-w-full text-xs" @change="setAgendaImage($event, agenda.id)" /></label><label class="flex items-center gap-2"><input v-model="agendaEdits[agenda.id].is_active" type="checkbox" /> Active</label><button class="ori-btn-primary">Save</button></form>
                    <template v-else><span class="flex min-w-0 items-center gap-3"><img v-if="agenda.image_url" :src="agenda.image_url" :alt="`${agenda.name} image`" class="h-12 w-12 shrink-0 rounded object-cover" /><span v-else class="h-12 w-12 shrink-0 rounded bg-ori-blue-50" aria-hidden="true"></span><span><strong class="text-sm text-ori-blue-900">{{ agenda.name }}</strong><span class="ms-2 text-xs text-ori-blue-500">{{ agenda.description }}</span></span></span><span class="flex gap-3"><button class="text-sm text-ori-blue-700" @click="editAgenda(agenda)">Edit</button><button class="text-sm text-rose-700" @click="removeAgenda(agenda)">Delete</button></span></template>
                </div>
            </div>
        </section>
        <section class="mt-7 border-y border-ori-blue-100 py-5">
            <div class="mb-4"><h2 class="font-display text-lg font-semibold text-ori-blue-900">United Nations Sustainable Development Goals</h2><p class="text-sm text-ori-blue-500">Edit the labels and control which goals appear to faculty. The 17 goal numbers remain fixed.</p></div>
            <div class="grid gap-3 md:grid-cols-2">
                <form v-for="sdg in sdgs" :key="sdg.id" class="grid grid-cols-[3rem_minmax(0,1fr)_6rem_auto] items-end gap-2 border-b border-ori-blue-100 py-3" @submit.prevent="saveSdg(sdg)">
                    <span class="pb-3 text-sm font-bold text-ori-blue-700">{{ sdg.number }}</span>
                    <label><span class="ori-label">Goal name</span><input v-model="sdgEdits[sdg.id].name" class="ori-input" required /></label>
                    <label><span class="ori-label">Color</span><input v-model="sdgEdits[sdg.id].color" type="color" class="ori-input h-10 p-1" /></label>
                    <button class="ori-btn-ghost">Save</button>
                    <div class="col-span-4 flex flex-wrap items-center gap-3">
                        <img v-if="sdgEdits[sdg.id].imagePreview" :src="sdgEdits[sdg.id].imagePreview ?? undefined" :alt="`SDG ${sdg.number} image preview`" class="h-14 w-14 rounded object-cover" />
                        <span v-else class="h-14 w-14 rounded bg-ori-blue-50" aria-hidden="true"></span>
                        <label class="text-xs"><span class="ori-label">Set or replace image (JPG, PNG, WebP; max 4 MB)</span><input type="file" accept="image/jpeg,image/png,image/webp" class="block max-w-full text-xs" @change="setSdgImage($event, sdg)" /></label>
                    </div>
                    <label class="col-span-4 flex items-center gap-2 text-xs text-ori-blue-600"><input v-model="sdgEdits[sdg.id].is_active" type="checkbox" /> Available to faculty</label>
                </form>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
