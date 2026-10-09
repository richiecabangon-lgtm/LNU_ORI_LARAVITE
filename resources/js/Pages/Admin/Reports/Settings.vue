<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    settings: Record<string, string | number | null> & { logo_url?: string | null; university_logo_url?: string | null };
    signatoryUsers: Array<{ id: number; name: string; role: string }>;
}>();
const form = useForm({
    logo: null as File | null,
    remove_logo: false,
    university_logo: null as File | null,
    remove_university_logo: false,
    institution_name: props.settings.institution_name ?? 'Leyte Normal University',
    office_name: props.settings.office_name ?? 'Office of Research and Innovation',
    report_heading: props.settings.report_heading ?? 'Faculty Performance Evaluation Sheet',
    report_subheading: props.settings.report_subheading ?? '',
    prepared_by_title: props.settings.prepared_by_title ?? 'Prepared by',
    prepared_by_user_id: props.settings.prepared_by_user_id ?? '',
    reviewed_by_title: props.settings.reviewed_by_title ?? 'Reviewed by',
    reviewed_by_user_id: props.settings.reviewed_by_user_id ?? '',
    approved_by_title: props.settings.approved_by_title ?? 'Approved by',
    approved_by_user_id: props.settings.approved_by_user_id ?? '',
    footer_text: props.settings.footer_text ?? '',
});
const preparedUsers = computed(() => props.signatoryUsers.filter((person) => ['admin', 'faculty'].includes(person.role)));
const coordinatorUsers = computed(() => props.signatoryUsers.filter((person) => person.role === 'coordinator'));
const adminUsers = computed(() => props.signatoryUsers.filter((person) => person.role === 'admin'));

const fields = [
    { key: 'institution_name', label: 'Institution name' },
    { key: 'office_name', label: 'Office name' },
    { key: 'report_heading', label: 'Report heading' },
    { key: 'report_subheading', label: 'Subheading' },
    { key: 'prepared_by_title', label: 'Prepared by title' },
    { key: 'reviewed_by_title', label: 'Reviewed by title' },
    { key: 'approved_by_title', label: 'Approved by title' },
] as const;

function saveSettings() {
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.reports.settings.update'), { forceFormData: true });
}

function chooseLogo(event: Event) {
    const input = event.target as HTMLInputElement;
    form.logo = input.files?.[0] ?? null;
    if (form.logo) {
        form.remove_logo = false;
    }
}

function chooseUniversityLogo(event: Event) {
    const input = event.target as HTMLInputElement;
    form.university_logo = input.files?.[0] ?? null;
    if (form.university_logo) {
        form.remove_university_logo = false;
    }
}
</script>

<template>
    <Head title="FPES Report Setup" />
    <AuthenticatedLayout>
        <PageHeader title="FPES report setup" description="Maintain the shared report heading, signatories, and footer used on every printed sheet." />
        <form class="max-w-4xl space-y-6" @submit.prevent="saveSettings">
            <section class="ori-card p-6">
                <div class="mb-6 grid gap-6 border-b border-ori-blue-100 pb-6 sm:grid-cols-2">
                    <div class="flex min-w-0 items-center gap-4">
                        <img v-if="settings.university_logo_url && !form.remove_university_logo" :src="settings.university_logo_url" alt="Current university logo" class="h-20 w-20 shrink-0 object-contain" />
                        <div v-else class="flex h-20 w-20 shrink-0 items-center justify-center border border-ori-blue-200 bg-ori-blue-50 text-xs font-bold text-ori-blue-800">LNU</div>
                        <div class="min-w-0">
                            <label for="fpes-university-logo" class="block text-sm font-semibold text-ori-blue-800">University crest / logo</label>
                            <input id="fpes-university-logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm text-ori-blue-700 file:mr-3 file:rounded-md file:border-0 file:bg-ori-blue-100 file:px-3 file:py-2 file:font-semibold file:text-ori-blue-800" @change="chooseUniversityLogo" />
                            <p class="mt-1 text-xs text-ori-blue-500">PNG, JPG, or WebP up to 2 MB.</p>
                            <span v-if="form.errors.university_logo" class="mt-1 block text-sm text-rose-700">{{ form.errors.university_logo }}</span>
                            <label v-if="settings.university_logo_url" class="mt-2 inline-flex items-center gap-2 text-sm text-ori-blue-700"><input v-model="form.remove_university_logo" type="checkbox" class="rounded border-ori-blue-300 text-ori-blue-700" @change="form.university_logo = null" />Remove university logo</label>
                        </div>
                    </div>
                    <div class="flex min-w-0 items-center gap-4">
                        <img v-if="settings.logo_url && !form.remove_logo" :src="settings.logo_url" alt="Current ORI logo" class="h-20 w-20 shrink-0 object-contain" />
                        <div v-else class="flex h-20 w-20 shrink-0 items-center justify-center border border-ori-blue-200 bg-ori-blue-50 font-display text-xl font-bold text-ori-blue-800">ORI</div>
                        <div class="min-w-0">
                            <label for="fpes-logo" class="block text-sm font-semibold text-ori-blue-800">Office of Research and Innovation logo</label>
                            <input id="fpes-logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm text-ori-blue-700 file:mr-3 file:rounded-md file:border-0 file:bg-ori-blue-100 file:px-3 file:py-2 file:font-semibold file:text-ori-blue-800" @change="chooseLogo" />
                            <p class="mt-1 text-xs text-ori-blue-500">PNG, JPG, or WebP up to 2 MB. Used on the login page and FPES report.</p>
                            <span v-if="form.errors.logo" class="mt-1 block text-sm text-rose-700">{{ form.errors.logo }}</span>
                            <label v-if="settings.logo_url" class="mt-2 inline-flex items-center gap-2 text-sm text-ori-blue-700"><input v-model="form.remove_logo" type="checkbox" class="rounded border-ori-blue-300 text-ori-blue-700" @change="form.logo = null" />Remove ORI logo</label>
                        </div>
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label v-for="field in fields" :key="field.key" class="block">
                        <span class="text-sm font-semibold text-ori-blue-800">{{ field.label }}</span>
                        <input v-model="form[field.key]" :required="field.key !== 'report_subheading'" class="mt-2 w-full rounded-md border-ori-blue-200 text-sm focus:border-ori-blue-600 focus:ring-ori-blue-600" />
                        <span v-if="form.errors[field.key]" class="mt-1 block text-sm text-rose-700">{{ form.errors[field.key] }}</span>
                    </label>
                </div>
                <div class="mt-6 grid gap-5 border-t border-ori-blue-100 pt-5 sm:grid-cols-3">
                    <label><span class="text-sm font-semibold text-ori-blue-800">Prepared by fallback</span><select v-model="form.prepared_by_user_id" class="mt-2 w-full rounded-md border-ori-blue-200 text-sm"><option value="">Use report faculty / current user</option><option v-for="person in preparedUsers" :key="person.id" :value="person.id">{{ person.name }} · {{ person.role }}</option></select></label>
                    <label><span class="text-sm font-semibold text-ori-blue-800">Evaluated by fallback</span><select v-model="form.reviewed_by_user_id" class="mt-2 w-full rounded-md border-ori-blue-200 text-sm"><option value="">Auto-select coordinator from the report college</option><option v-for="person in coordinatorUsers" :key="person.id" :value="person.id">{{ person.name }} · {{ person.role }}</option></select><span class="mt-1 block text-xs text-ori-blue-500">Faculty reports automatically use the active coordinator assigned to the same college.</span></label>
                    <label><span class="text-sm font-semibold text-ori-blue-800">Approved by (one university approver)</span><select v-model="form.approved_by_user_id" required class="mt-2 w-full rounded-md border-ori-blue-200 text-sm"><option value="">Select the university approver</option><option v-for="person in adminUsers" :key="person.id" :value="person.id">{{ person.name }}</option></select><span class="mt-1 block text-xs text-ori-blue-500">This single admin name is used across colleges.</span><span v-if="form.errors.approved_by_user_id" class="mt-1 block text-sm text-rose-700">{{ form.errors.approved_by_user_id }}</span></label>
                </div>
                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-ori-blue-800">Footer text</span>
                    <textarea v-model="form.footer_text" rows="3" class="mt-2 w-full rounded-md border-ori-blue-200 text-sm focus:border-ori-blue-600 focus:ring-ori-blue-600" />
                    <span v-if="form.errors.footer_text" class="mt-1 block text-sm text-rose-700">{{ form.errors.footer_text }}</span>
                </label>
            </section>
            <div class="flex items-center gap-4">
                <button type="submit" :disabled="form.processing" class="ori-btn-accent">{{ form.processing ? 'Saving...' : 'Save report setup' }}</button>
                <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700">Saved.</span>
            </div>
        </form>
    </AuthenticatedLayout>
</template>