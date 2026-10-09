<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import Pagination from '@/Components/ORI/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    users: any;
    filters: { role?: string; search?: string };
    roles: Array<{ value: string; label: string }>;
    colleges: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string; college_id: number }>;
}>();

const search = ref(props.filters.search || '');
const role = ref(props.filters.role || '');
const showForm = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'faculty',
    employee_id: '',
    college_id: '',
    department_id: '',
    designation: '',
    academic_rank: '',
    is_active: true,
});

const filteredDepartments = computed(() =>
    form.college_id
        ? props.departments.filter((dept) => dept.college_id === Number(form.college_id))
        : [],
);

watch(() => form.college_id, (collegeId) => {
    const department = props.departments.find((item) => item.id === Number(form.department_id));
    if (department && department.college_id !== Number(collegeId)) {
        form.department_id = '';
    }
});

watch([search, role], () => {
    router.get(
        route('admin.users.index'),
        { search: search.value || undefined, role: role.value || undefined },
        { preserveState: true, replace: true },
    );
});

const createUser = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            form.reset();
            form.role = 'faculty';
            form.is_active = true;
            showForm.value = false;
        },
    });
};

const toggleActive = (user: any) => {
    router.put(route('admin.users.update', user.id), {
        name: user.name,
        email: user.email,
        role: user.role,
        employee_id: user.employee_id,
        college_id: user.college_id,
        department_id: user.department_id,
        designation: user.designation,
        is_active: !user.is_active,
    });
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <PageHeader
            title="User management"
            description="Provision Faculty, Coordinator, and Admin accounts for sustainable ORI operations."
        >
            <template #actions>
                <button type="button" class="ori-btn-accent" @click="showForm = !showForm">
                    {{ showForm ? 'Close form' : 'Add user' }}
                </button>
            </template>
        </PageHeader>

        <div v-if="showForm" class="ori-card mb-6 p-6">
            <h2 class="font-display text-xl font-semibold text-ori-blue-900">Create account</h2>
            <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="createUser">
                <div v-if="Object.keys(form.errors).length" role="alert" class="md:col-span-2 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    Please correct the highlighted fields before creating the account.
                </div>
                <div>
                    <label class="ori-label">Full name</label>
                    <input v-model="form.name" type="text" class="ori-input" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Email</label>
                    <input v-model="form.email" type="email" class="ori-input" required />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Temporary password</label>
                    <input v-model="form.password" type="password" class="ori-input" required />
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Role</label>
                    <select v-model="form.role" class="ori-input">
                        <option v-for="item in roles" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <InputError :message="form.errors.role" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Employee ID</label>
                    <input v-model="form.employee_id" type="text" class="ori-input" />
                    <InputError :message="form.errors.employee_id" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Designation</label>
                    <input v-model="form.designation" type="text" class="ori-input" />
                    <InputError :message="form.errors.designation" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Academic rank</label>
                    <input v-model="form.academic_rank" type="text" class="ori-input" placeholder="e.g. Assistant Professor" />
                    <InputError :message="form.errors.academic_rank" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">College</label>
                    <select v-model="form.college_id" class="ori-input">
                        <option value="">Select college</option>
                        <option v-for="college in colleges" :key="college.id" :value="college.id">{{ college.name }}</option>
                    </select>
                    <InputError :message="form.errors.college_id" class="mt-1" />
                </div>
                <div>
                    <label class="ori-label">Department</label>
                    <select v-model="form.department_id" class="ori-input" :disabled="!form.college_id">
                        <option value="">{{ form.college_id ? 'Select department' : 'Select a college first' }}</option>
                        <option v-for="dept in filteredDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <InputError :message="form.errors.department_id" class="mt-1" />
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="ori-btn-primary" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create user' }}</button>
                </div>
            </form>
        </div>

        <div class="ori-card p-4">
            <div class="grid gap-3 md:grid-cols-3">
                <input v-model="search" type="search" class="ori-input md:col-span-2" placeholder="Search name, email, or employee ID" />
                <select v-model="role" class="ori-input">
                    <option value="">All roles</option>
                    <option v-for="item in roles" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
        </div>

        <div class="ori-card mt-6 overflow-hidden">
            <table class="min-w-full divide-y divide-ori-blue-100 text-left text-sm">
                <thead class="bg-ori-blue-50/80 text-ori-blue-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">User</th>
                        <th class="px-4 py-3 font-semibold">Role</th>
                        <th class="px-4 py-3 font-semibold">Unit</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ori-blue-50">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-ori-blue-900">{{ user.name }}</div>
                            <div class="text-xs text-ori-blue-500">{{ user.email }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ user.role }}</td>
                        <td class="px-4 py-3">
                            <div>{{ user.college?.name || '—' }}</div>
                            <div class="text-xs text-ori-blue-500">{{ user.department?.name }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="ori-badge"
                                :class="user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="font-semibold text-ori-blue-700" @click="toggleActive(user)">
                                {{ user.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="users.links" />
    </AuthenticatedLayout>
</template>
