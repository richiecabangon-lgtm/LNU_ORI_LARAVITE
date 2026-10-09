<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
}>();
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-white text-ori-blue-900">
        <header class="absolute inset-x-0 top-0 z-20">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
                <div>
                    <p class="font-display text-xl font-semibold text-white">LNU ORI System</p>
                    <p class="text-xs uppercase tracking-[0.2em] text-ori-yellow-300">
                        Office of Research and Innovation
                    </p>
                </div>
                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="ori-btn-accent"
                >
                    Sign in
                </Link>
            </div>
        </header>

        <section class="relative overflow-hidden bg-ori-hero px-6 pb-24 pt-32 text-white">
            <div class="pointer-events-none absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.2), transparent 25%), radial-gradient(circle at 80% 20%, rgba(245,197,24,0.35), transparent 20%);" />
            <div class="relative mx-auto max-w-7xl">
                <p class="mb-4 text-sm font-semibold uppercase tracking-[0.24em] text-ori-yellow-300">
                    Leyte Normal University
                </p>
                <h1 class="max-w-3xl font-display text-5xl font-semibold leading-tight md:text-6xl">
                    Research involvement, evaluated with clarity.
                </h1>
                <p class="mt-6 max-w-2xl text-lg text-ori-blue-100">
                    A sustainable workflow for faculty submissions, coordinator evaluation,
                    and administrative approval across the Office of Research and Innovation.
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <Link :href="route('login')" class="ori-btn-accent">
                        Enter ORI Portal
                    </Link>
                    <a href="#workflow" class="ori-btn-ghost border-white/20 bg-white/10 text-white hover:bg-white/20">
                        View workflow
                    </a>
                </div>
            </div>
        </section>

        <section id="workflow" class="mx-auto max-w-7xl px-6 py-20">
            <div class="max-w-2xl">
                <h2 class="font-display text-3xl font-semibold text-ori-blue-900">
                    Role-based research governance
                </h2>
                <p class="mt-3 text-ori-blue-600">
                    Built for long-term institutional use with clear accountability at every stage.
                </p>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <article class="ori-card p-6">
                    <div class="mb-4 inline-flex rounded-full bg-ori-yellow-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-ori-blue-800">
                        Faculty
                    </div>
                    <h3 class="font-display text-xl font-semibold">Submit involvement</h3>
                    <p class="mt-2 text-sm text-ori-blue-600">
                        Encode research outputs, attach evidence, and track progress from draft to approval.
                    </p>
                </article>
                <article class="ori-card p-6">
                    <div class="mb-4 inline-flex rounded-full bg-ori-blue-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-ori-blue-800">
                        Coordinator
                    </div>
                    <h3 class="font-display text-xl font-semibold">Evaluate quality</h3>
                    <p class="mt-2 text-sm text-ori-blue-600">
                        Score documentation, recommend approval or revision, and keep an auditable trail.
                    </p>
                </article>
                <article class="ori-card p-6">
                    <div class="mb-4 inline-flex rounded-full bg-ori-blue-700 px-3 py-1 text-xs font-bold uppercase tracking-wide text-ori-yellow-300">
                        Admin
                    </div>
                    <h3 class="font-display text-xl font-semibold">Finalize decisions</h3>
                    <p class="mt-2 text-sm text-ori-blue-600">
                        Approve evaluated submissions, manage users, and sustain institutional records.
                    </p>
                </article>
            </div>
        </section>

        <footer class="border-t border-ori-blue-100 bg-white px-6 py-8 text-sm text-ori-blue-500">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span>© {{ new Date().getFullYear() }} Leyte Normal University — Office of Research and Innovation</span>
                <span></span>
            </div>
        </footer>
    </div>
</template>
