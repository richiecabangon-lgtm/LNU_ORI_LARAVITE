<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/ORI/PageHeader.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ settings: Record<string, string | boolean | null> & { allow_non_scopus: boolean } }>();
const form = useForm({
    institution_name: String(props.settings.institution_name ?? 'Leyte Normal University'),
    office_name: String(props.settings.office_name ?? 'Office of Research and Innovation'),
    heading: String(props.settings.heading ?? 'Certificate of Research Involvement'),
    additional_text: String(props.settings.additional_text ?? ''),
    first_signatory_name: String(props.settings.first_signatory_name ?? ''),
    first_signatory_title: String(props.settings.first_signatory_title ?? ''),
    second_signatory_name: String(props.settings.second_signatory_name ?? ''),
    second_signatory_title: String(props.settings.second_signatory_title ?? ''),
    allow_non_scopus: props.settings.allow_non_scopus ?? false,
});
const fields = [
    { key: 'institution_name', label: 'Institution name', required: true },
    { key: 'office_name', label: 'Office name', required: true },
    { key: 'heading', label: 'Certificate heading', required: true },
    { key: 'first_signatory_name', label: 'First signatory name', required: false },
    { key: 'first_signatory_title', label: 'First signatory title', required: false },
    { key: 'second_signatory_name', label: 'Second signatory name', required: false },
    { key: 'second_signatory_title', label: 'Second signatory title', required: false },
] as const;
</script>

<template>
    <Head title="Certificate Setup" />
    <AuthenticatedLayout>
        <PageHeader title="Certificate setup" />
        <form class="max-w-4xl space-y-6" @submit.prevent="form.put(route('admin.certification.settings.update'))">
            <section class="ori-card space-y-5 p-6">
                <div>
                    <label class="flex items-start gap-3">
                        <input v-model="form.allow_non_scopus" type="checkbox" class="mt-1 rounded border-ori-blue-200 text-ori-blue-600 focus:ring-ori-blue-600" />
                        <span class="text-sm font-semibold text-ori-blue-800">Allow certificates for other approved research types</span>
                    </label>
                    <p class="mt-2 text-sm text-gray-600">By default, certificates are available only for approved published individual research in the Scopus-indexed journal category. Indexing is self-reported, not independently verified. Legacy journal articles and other research types require this option.</p>
                    <span v-if="form.errors.allow_non_scopus" class="mt-1 block text-sm text-rose-700">{{ form.errors.allow_non_scopus }}</span>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label v-for="field in fields" :key="field.key" class="block">
                        <span class="text-sm font-semibold text-ori-blue-800">{{ field.label }}</span>
                        <input v-model="form[field.key]" :required="field.required" maxlength="255" class="mt-2 w-full rounded-md border-ori-blue-200 text-sm focus:border-ori-blue-600 focus:ring-ori-blue-600" />
                        <span v-if="form.errors[field.key]" class="mt-1 block text-sm text-rose-700">{{ form.errors[field.key] }}</span>
                    </label>
                </div>
                <label class="block">
                    <span class="text-sm font-semibold text-ori-blue-800">Additional text</span>
                    <textarea v-model="form.additional_text" rows="4" maxlength="2000" class="mt-2 w-full rounded-md border-ori-blue-200 text-sm focus:border-ori-blue-600 focus:ring-ori-blue-600" />
                    <span v-if="form.errors.additional_text" class="mt-1 block text-sm text-rose-700">{{ form.errors.additional_text }}</span>
                </label>
            </section>
            <div class="flex items-center gap-4">
                <button type="submit" :disabled="form.processing" class="ori-btn-accent">{{ form.processing ? 'Saving...' : 'Save certificate setup' }}</button>
                <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700">Saved.</span>
            </div>
        </form>
    </AuthenticatedLayout>
</template>