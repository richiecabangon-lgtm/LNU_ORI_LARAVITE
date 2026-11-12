<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ResearchInterestsInput from '@/Components/ORI/ResearchInterestsInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
    researchInterests: string[] | null;
    assignment: { college: string | null; department: string | null };
    colleges: { id: number; name: string; is_active: boolean }[];
    departments: { id: number; name: string; college_id: number; is_active: boolean }[];
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    designation: user.designation ?? '',
    college_id: user.college_id ?? null as number | null,
    department_id: user.department_id ?? null as number | null,
    academic_rank: user.academic_rank ?? '',
    orcid_url: user.orcid_url ?? '',
    google_scholar_url: user.google_scholar_url ?? '',
    scopus_url: user.scopus_url ?? '',
    institutional_profile_url: user.institutional_profile_url ?? '',
    research_interests: [...(props.researchInterests ?? [])],
});

const filteredDepartments = computed(() => props.departments.filter((department) => department.college_id === form.college_id));

function saveProfile() {
    form.transform(({ research_interests, college_id, department_id, ...data }) => ({
        ...data,
        ...(user.role === 'faculty' ? {
            college_id,
            department_id,
            research_interests: research_interests.map((interest) => interest.trim()).filter(Boolean),
        } : {}),
    })).patch(route('profile.update'));
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="saveProfile"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="designation" value="Designation" />
                <TextInput id="designation" type="text" class="mt-1 block w-full" v-model="form.designation" maxlength="255" />
                <InputError class="mt-2" :message="form.errors.designation" />
            </div>

            <template v-if="user.role === 'faculty'">
                <div>
                    <InputLabel for="college_id" value="College" />
                    <select id="college_id" v-model="form.college_id" @change="form.department_id = null" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option :value="null">No college</option>
                        <option v-for="college in colleges" :key="college.id" :value="college.id">{{ college.name }}{{ college.is_active ? '' : ' (inactive)' }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.college_id" />
                </div>
                <div>
                    <InputLabel for="department_id" value="Department" />
                    <select id="department_id" v-model="form.department_id" :disabled="form.college_id === null" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                        <option :value="null">No department</option>
                        <option v-for="department in filteredDepartments" :key="department.id" :value="department.id">{{ department.name }}{{ department.is_active ? '' : ' (inactive)' }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.department_id" />
                </div>
            </template>
            <div v-else>
                <h3 class="text-sm font-medium text-gray-700">College and department</h3>
                <p class="mt-1 text-sm text-gray-900">{{ assignment.college || 'No college assigned' }} / {{ assignment.department || 'No department assigned' }}</p>
                <p class="mt-1 text-sm text-gray-600">An administrator manages your college and department assignment. Contact an administrator to request a change.</p>
            </div>

            <div>
                <InputLabel for="academic_rank" value="Academic rank" />
                <TextInput
                    id="academic_rank"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.academic_rank"
                    placeholder="e.g. Assistant Professor"
                    autocomplete="organization-title"
                />
                <InputError class="mt-2" :message="form.errors.academic_rank" />
            </div>

            <div>
                <InputLabel for="orcid_url" value="ORCID profile" />
                <TextInput id="orcid_url" type="url" class="mt-1 block w-full" v-model="form.orcid_url" placeholder="https://orcid.org/0000-0000-0000-0000" />
                <InputError class="mt-2" :message="form.errors.orcid_url" />
            </div>

            <div>
                <InputLabel for="google_scholar_url" value="Google Scholar profile" />
                <TextInput id="google_scholar_url" type="url" class="mt-1 block w-full" v-model="form.google_scholar_url" placeholder="https://scholar.google.com/citations?user=..." />
                <InputError class="mt-2" :message="form.errors.google_scholar_url" />
            </div>

            <div>
                <InputLabel for="scopus_url" value="Scopus author profile" />
                <TextInput id="scopus_url" type="url" class="mt-1 block w-full" v-model="form.scopus_url" placeholder="https://www.scopus.com/authid/detail.uri?authorId=..." />
                <InputError class="mt-2" :message="form.errors.scopus_url" />
            </div>

            <div>
                <InputLabel for="institutional_profile_url" value="Institutional profile" />
                <TextInput id="institutional_profile_url" type="url" class="mt-1 block w-full" v-model="form.institutional_profile_url" placeholder="https://www.lnu.edu.ph/..." />
                <InputError class="mt-2" :message="form.errors.institutional_profile_url" />
            </div>

            <ResearchInterestsInput v-if="user.role === 'faculty'" v-model="form.research_interests" :errors="form.errors" id-prefix="profile-interest" />

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
