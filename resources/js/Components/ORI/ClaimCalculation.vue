<script setup lang="ts">
defineProps<{
    submission: {
        quantity?: string | number | null;
        unit_snapshot?: string | null;
        rate_snapshot?: string | number | null;
        claimed_points?: string | number | null;
        research_type?: { semester_ceiling?: string | number | null } | null;
    };
}>();
</script>

<template>
    <div v-if="submission.quantity != null || submission.research_type?.semester_ceiling != null" class="border-y border-ori-blue-100 py-3 text-sm">
        <p v-if="submission.quantity != null && submission.rate_snapshot != null">
            {{ Number(submission.quantity) }} {{ submission.unit_snapshot }}(s) × {{ Number(submission.rate_snapshot).toFixed(2) }} pts = {{ Number(submission.claimed_points).toFixed(2) }} pts
        </p>
        <p v-if="submission.research_type?.semester_ceiling != null" class="mt-1 text-amber-800">
            Brochure reference: {{ Number(submission.research_type.semester_ceiling).toFixed(2) }} points per semester. Coordinator must review the semester total; it is not checked automatically.
        </p>
    </div>
</template>