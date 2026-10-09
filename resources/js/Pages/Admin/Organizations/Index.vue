<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Department = { id: number; code: string; name: string; is_active: boolean };
type College = { id: number; code: string; name: string; is_active: boolean; departments: Department[] };
const props = defineProps<{ colleges: College[] }>();
const showCollegeForm = ref(false);
const addingDepartmentFor = ref<number | null>(null);
const collegeForm = useForm({ code: '', name: '' });
const departmentForm = useForm({ college_id: 0, code: '', name: '' });
const editingCollege = ref<number | null>(null);
const collegeEdit = useForm({ code: '', name: '', is_active: true });
const editingDepartment = ref<number | null>(null);
const departmentEdit = useForm({ college_id: 0, code: '', name: '', is_active: true });
function addCollege() { collegeForm.post(route('admin.colleges.store'), { onSuccess: () => { collegeForm.reset(); showCollegeForm.value = false; } }); }
function startAddDepartment(college: College) { addingDepartmentFor.value = college.id; departmentForm.reset(); departmentForm.college_id = college.id; }
function addDepartment(college: College) { departmentForm.college_id = college.id; departmentForm.post(route('admin.departments.store'), { onSuccess: () => { departmentForm.reset(); addingDepartmentFor.value = null; } }); }
function editCollege(college: College) { editingCollege.value = college.id; collegeEdit.code = college.code; collegeEdit.name = college.name; collegeEdit.is_active = college.is_active; }
function saveCollege(college: College) { collegeEdit.put(route('admin.colleges.update', college.id), { onSuccess: () => editingCollege.value = null }); }
function editDepartment(college: College, department: Department) { editingDepartment.value = department.id; departmentEdit.college_id = college.id; departmentEdit.code = department.code; departmentEdit.name = department.name; departmentEdit.is_active = department.is_active; }
function saveDepartment(department: Department) { departmentEdit.put(route('admin.departments.update', department.id), { onSuccess: () => editingDepartment.value = null }); }
function remove(url: string, label: string) { if (confirm(`Delete ${label}? Items with assigned users or submissions must be deactivated instead.`)) router.delete(url); }
</script>

<template>
    <Head title="Colleges and Departments" />
    <AuthenticatedLayout>
        <PageHeader title="Colleges and departments" description="Maintain colleges and their programs/departments. Departments are always assigned to a college.">
            <template #actions><button type="button" class="ori-btn-accent" @click="showCollegeForm = !showCollegeForm">{{ showCollegeForm ? 'Close' : 'Add college' }}</button></template>
        </PageHeader>
        <form v-if="showCollegeForm" class="mb-6 grid gap-3 border-y border-ori-blue-100 py-5 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-end" @submit.prevent="addCollege">
            <label><span class="ori-label">College code</span><input v-model="collegeForm.code" class="ori-input" placeholder="CAS" required /><InputError :message="collegeForm.errors.code" /></label>
            <label><span class="ori-label">College name</span><input v-model="collegeForm.name" class="ori-input" required /><InputError :message="collegeForm.errors.name" /></label>
            <button class="ori-btn-primary">Add college</button>
        </form>
        <div class="divide-y divide-ori-blue-100 border-y border-ori-blue-100">
            <section v-for="college in colleges" :key="college.id" class="py-5">
                <form v-if="editingCollege === college.id" class="grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)_auto_auto] sm:items-end" @submit.prevent="saveCollege(college)">
                    <label><span class="ori-label">Code</span><input v-model="collegeEdit.code" class="ori-input" required /></label><label><span class="ori-label">Name</span><input v-model="collegeEdit.name" class="ori-input" required /></label><label class="flex items-center gap-2 pb-2"><input v-model="collegeEdit.is_active" type="checkbox" /> Active</label><button class="ori-btn-primary">Save</button>
                </form>
                <div v-else class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase text-ori-blue-500">{{ college.code }}</p><h2 class="font-display text-lg font-semibold text-ori-blue-900">{{ college.name }}</h2></div><div class="flex gap-3"><span class="text-xs text-ori-blue-500">{{ college.is_active ? 'Active' : 'Inactive' }}</span><button class="text-sm font-semibold text-ori-blue-700" @click="editCollege(college)">Edit</button><button class="text-sm font-semibold text-rose-700" @click="remove(route('admin.colleges.destroy', college.id), college.name)">Delete</button></div></div>
                <form v-if="addingDepartmentFor === college.id" class="mt-4 grid gap-3 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:items-end" @submit.prevent="addDepartment(college)">
                    <label><span class="ori-label">Department code</span><input v-model="departmentForm.code" class="ori-input" :placeholder="college.code + '-BSIT'" required /><InputError :message="departmentForm.errors.code" /></label>
                    <label><span class="ori-label">Department / program name</span><input v-model="departmentForm.name" class="ori-input" placeholder="Bachelor of Science in Information Technology" required /><InputError :message="departmentForm.errors.name" /></label>
                    <button class="ori-btn-ghost">Add department</button>
                </form>
                <button v-else type="button" class="mt-4 text-sm font-semibold text-ori-blue-700 underline" @click="startAddDepartment(college)">Add department or program</button>
                <ul class="mt-4 divide-y divide-ori-blue-50 border-t border-ori-blue-100">
                    <li v-for="department in college.departments" :key="department.id" class="py-3">
                        <form v-if="editingDepartment === department.id" class="grid gap-3 sm:grid-cols-[9rem_minmax(0,1fr)_auto_auto] sm:items-end" @submit.prevent="saveDepartment(department)"><label><span class="ori-label">Code</span><input v-model="departmentEdit.code" class="ori-input" required /></label><label><span class="ori-label">Name</span><input v-model="departmentEdit.name" class="ori-input" required /></label><label class="flex items-center gap-2 pb-2"><input v-model="departmentEdit.is_active" type="checkbox" /> Active</label><button class="ori-btn-primary">Save</button></form>
                        <div v-else class="flex items-center justify-between gap-3"><span><strong class="text-sm text-ori-blue-900">{{ department.code }}</strong><span class="ms-3 text-sm text-ori-blue-700">{{ department.name }}</span></span><span class="flex gap-3"><button class="text-sm text-ori-blue-700" @click="editDepartment(college, department)">Edit</button><button class="text-sm text-rose-700" @click="remove(route('admin.departments.destroy', department.id), department.name)">Delete</button></span></div>
                    </li>
                    <li v-if="college.departments.length === 0" class="py-4 text-sm text-ori-blue-500">No departments configured.</li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
