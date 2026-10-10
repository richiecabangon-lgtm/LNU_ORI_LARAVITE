```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
}>();
</script>

<template>
    <Head title="Welcome | LNU ORI System" />

    <div class="min-h-screen overflow-hidden bg-white text-ori-blue-900">

        <!-- Header -->
        <header class="absolute inset-x-0 top-0 z-30">
            <!-- University gold accent line -->
            <div class="h-1 w-full bg-ori-yellow-300"></div>

            <div class="border-b border-white/15 bg-ori-blue-900/25 backdrop-blur-md">
                <div
                    class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-5 lg:px-10"
                >
                    <!-- Brand -->
                    <a href="#" class="group flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center">
                            <img
                                src="/images/ori.ico"
                                alt="LNU Office of Research and Innovation Logo"
                                class="h-12 w-12 rounded-xl object-contain"
                            />
                        </div>

                        <div>
                            <p class="font-display text-lg font-bold leading-tight tracking-wide text-white sm:text-xl">
                                LNU ORI System
                            </p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-ori-yellow-300 sm:text-xs sm:tracking-[0.2em]">
                                Office of Research and Innovation
                            </p>
                        </div>
                    </a>

                    <!-- Sign in -->
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="ori-btn-accent inline-flex shrink-0 items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-bold shadow-lg transition duration-300 hover:-translate-y-0.5 hover:shadow-xl"
                    >
                        Sign in
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 7l5 5m0 0l-5 5m5-5H4"
                            />
                        </svg>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Hero section -->
        <section class="relative isolate overflow-hidden bg-ori-hero px-6 pb-24 pt-36 text-white sm:pt-44 lg:pb-32 lg:pt-48">

            <!-- Decorative background -->
            <div
                class="pointer-events-none absolute inset-0 -z-10 opacity-40"
                style="background-image: radial-gradient(circle at 12% 80%, rgba(255,255,255,0.16), transparent 28%), radial-gradient(circle at 85% 25%, rgba(245,197,24,0.24), transparent 25%);"
            ></div>

            <div
                class="pointer-events-none absolute -right-24 top-28 -z-10 h-80 w-80 rounded-full border border-white/10 lg:right-10 lg:top-32 lg:h-[30rem] lg:w-[30rem]"
            ></div>

            <div
                class="pointer-events-none absolute -right-10 top-44 -z-10 h-64 w-64 rounded-full border border-ori-yellow-300/20 lg:right-24 lg:top-48 lg:h-96 lg:w-96"
            ></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[1.25fr_0.75fr] lg:gap-12">

                <!-- Hero copy -->
                <div class="max-w-3xl">
                    <div class="mb-7 inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/10 px-4 py-2 backdrop-blur-sm">
                        <span class="h-2 w-2 rounded-full bg-ori-yellow-300 shadow-[0_0_12px_rgba(245,197,24,0.8)]"></span>
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-ori-blue-100 sm:text-sm">
                            Leyte Normal University
                        </span>
                    </div>

                    <h1 class="font-display text-4xl font-bold leading-[1.13] tracking-tight text-white sm:text-5xl md:text-6xl lg:text-7xl">
                        Empowering Faculty.
                        <span class="mt-2 block text-ori-yellow-300">
                            Enriching Lives.
                        </span>
                    </h1>

                    <div class="my-7 flex items-center gap-3">
                        <span class="h-1 w-16 rounded-full bg-ori-yellow-300"></span>
                        <span class="h-1 w-7 rounded-full bg-white/40"></span>
                        <span class="h-1 w-3 rounded-full bg-white/20"></span>
                    </div>

                    <p class="max-w-2xl text-base leading-8 text-ori-blue-100 sm:text-lg">
                        Advancing research excellence at Leyte Normal University
                        through faculty engagement, accountable evaluation, and
                        meaningful knowledge creation. One platform for managing
                        research involvement and supporting innovation.
                    </p>

                    <!-- Actions -->
                    <div class="mt-9 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <Link
                            :href="route('login')"
                            class="ori-btn-accent inline-flex items-center justify-center gap-3 rounded-xl px-7 py-4 text-sm font-bold shadow-xl transition duration-300 hover:-translate-y-1 hover:shadow-2xl sm:text-base"
                        >
                            Enter ORI Portal
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13 7l5 5m0 0l-5 5m5-5H4"
                                />
                            </svg>
                        </Link>

                        <a
                            href="#workflow"
                            class="ori-btn-ghost inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/5 px-7 py-4 text-sm font-semibold text-white transition duration-300 hover:-translate-y-1 hover:bg-white/15 sm:text-base"
                        >
                            Explore the workflow
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </a>
                    </div>

                    <p class="mt-7 text-xs leading-6 text-ori-blue-100/80 sm:text-sm">
                        A unified space for research documentation, evaluation,
                        and institutional research governance.
                    </p>
                </div>

                <!-- Visual feature panel -->
                <div class="relative mx-auto w-full max-w-md lg:ml-auto">
                    <div class="absolute -inset-4 rounded-[2rem] bg-ori-yellow-300/10 blur-2xl"></div>

                    <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/10 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-ori-yellow-300">
                                    Research excellence
                                </p>
                                <h2 class="mt-3 font-display text-2xl font-bold text-white sm:text-3xl">
                                    From ideas to impact.
                                </h2>
                            </div>

                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-ori-yellow-300/15 text-ori-yellow-300">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-7 w-7"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3l2.4 5.1L20 9l-4 4 .9 5.7L12 16l-4.9 2.7L8 13l-4-4 5.6-.9L12 3z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19 16l1.2 2.4L23 19l-2 2 .4 2"
                                    />
                                </svg>
                            </div>
                        </div>

                        <div class="my-7 h-px bg-gradient-to-r from-ori-yellow-300/70 via-white/20 to-transparent"></div>

                        <div class="space-y-5">
                            <div class="flex items-center gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ori-yellow-300/15 text-ori-yellow-300">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 6.5v13m0-13C10.7 5.5 9 5 7 5c-1.4 0-2.7.2-4 .7v13c1.3-.5 2.6-.7 4-.7 2 0 3.7.5 5 1.5m0-13C13.3 5.5 15 5 17 5c1.4 0 2.7.2 4 .7v13c-1.3-.5-2.6-.7-4-.7-2 0-3.7.5-5 1.5"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-white">Faculty engagement</h3>
                                    <p class="mt-1 text-sm leading-6 text-ori-blue-100">
                                        Document research contributions and supporting evidence.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m5-3.5A12 12 0 0112 3a12 12 0 01-8 3.5V12c0 5 3.4 7.8 8 9 4.6-1.2 8-4 8-9V7.5z"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-white">Transparent evaluation</h3>
                                    <p class="mt-1 text-sm leading-6 text-ori-blue-100">
                                        Support consistent review and accountable decisions.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3 3v18h18M7 14l4-4 4 3 5-7"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-white">Institutional progress</h3>
                                    <p class="mt-1 text-sm leading-6 text-ori-blue-100">
                                        Organize research records and strengthen oversight.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 rounded-2xl border border-white/15 bg-ori-blue-900/30 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-ori-blue-100">
                                Our shared purpose
                            </p>
                            <p class="mt-2 text-sm font-medium leading-6 text-white">
                                Advancing knowledge. Inspiring innovation.
                                Creating meaningful impact.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gold divider at the base of the hero -->
            <div class="absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-transparent via-ori-yellow-300 to-transparent"></div>
        </section>

        <!-- Workflow section -->
        <section id="workflow" class="relative bg-slate-50 px-6 py-20 sm:py-24 lg:px-10">
            <div class="mx-auto max-w-7xl">

                <!-- Section heading -->
                <div class="mx-auto max-w-2xl text-center">
                    <span class="inline-flex items-center gap-2 rounded-full border border-ori-blue-100 bg-white px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-ori-blue-800 shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-ori-yellow-300"></span>
                        One connected system
                    </span>

                    <h2 class="mt-5 font-display text-3xl font-bold tracking-tight text-ori-blue-900 sm:text-4xl lg:text-5xl">
                        Research governance,
                        <span class="text-ori-blue-600">made clearer.</span>
                    </h2>

                    <p class="mx-auto mt-5 max-w-xl text-base leading-8 text-slate-600">
                        A structured workflow that connects faculty submissions,
                        coordinator evaluation, and administrative decisions
                        in one accountable research management process.
                    </p>

                    <div class="mx-auto mt-7 flex w-fit items-center gap-2">
                        <span class="h-1 w-12 rounded-full bg-ori-yellow-300"></span>
                        <span class="h-1 w-5 rounded-full bg-ori-blue-200"></span>
                    </div>
                </div>

                <!-- Role cards -->
                <div class="mt-14 grid gap-6 md:grid-cols-3">

                    <!-- Faculty -->
                    <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-ori-yellow-300 hover:shadow-xl">
                        <div class="absolute inset-x-0 top-0 h-1 bg-ori-yellow-300 transition-all duration-300 group-hover:h-1.5"></div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex rounded-full bg-ori-yellow-100 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-ori-blue-800">
                                Faculty
                            </span>

                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-ori-yellow-100 text-ori-blue-800 transition duration-300 group-hover:scale-110">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.2-3.4M5 11.1V16c4.7 3.8 9.3 3.8 14 0v-4.9M21 9v6"
                                    />
                                </svg>
                            </div>
                        </div>

                        <h3 class="mt-6 font-display text-xl font-bold text-ori-blue-900">
                            Submit involvement
                        </h3>

                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            Record research outputs, upload supporting documents,
                            and monitor submissions throughout the review process.
                        </p>

                        <div class="mt-6 flex items-center gap-2 border-t border-slate-100 pt-5 text-xs font-semibold text-ori-blue-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4 text-ori-yellow-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12l4 4L19 6"
                                />
                            </svg>
                            Research documentation
                        </div>
                    </article>

                    <!-- Coordinator -->
                    <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-ori-blue-300 hover:shadow-xl">
                        <div class="absolute inset-x-0 top-0 h-1 bg-ori-blue-700 transition-all duration-300 group-hover:h-1.5"></div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex rounded-full bg-ori-blue-100 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-ori-blue-800">
                                Coordinator
                            </span>

                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-ori-blue-100 text-ori-blue-800 transition duration-300 group-hover:scale-110">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m5-3.5A12 12 0 0112 3a12 12 0 01-8 3.5V12c0 5 3.4 7.8 8 9 4.6-1.2 8-4 8-9V7.5z"
                                    />
                                </svg>
                            </div>
                        </div>

                        <h3 class="mt-6 font-display text-xl font-bold text-ori-blue-900">
                            Evaluate quality
                        </h3>

                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            Review submitted evidence, assess research involvement,
                            and recommend approval or revision with clear accountability.
                        </p>

                        <div class="mt-6 flex items-center gap-2 border-t border-slate-100 pt-5 text-xs font-semibold text-ori-blue-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4 text-ori-blue-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12l4 4L19 6"
                                />
                            </svg>
                            Review and recommendation
                        </div>
                    </article>

                    <!-- Admin -->
                    <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-ori-blue-500 hover:shadow-xl">
                        <div class="absolute inset-x-0 top-0 h-1 bg-ori-blue-900 transition-all duration-300 group-hover:h-1.5"></div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex rounded-full bg-ori-blue-900 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-white">
                                Admin
                            </span>

                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-ori-blue-900 text-ori-yellow-300 transition duration-300 group-hover:scale-110">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3l2.4 5.1L20 9l-4 4 .9 5.7L12 16l-4.9 2.7L8 13l-4-4 5.6-.9L12 3z"
                                    />
                                </svg>
                            </div>
                        </div>

                        <h3 class="mt-6 font-display text-xl font-bold text-ori-blue-900">
                            Finalize decisions
                        </h3>

                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            Approve evaluated submissions, administer system users,
                            and maintain organized institutional research records.
                        </p>

                        <div class="mt-6 flex items-center gap-2 border-t border-slate-100 pt-5 text-xs font-semibold text-ori-blue-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4 text-ori-blue-700"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12l4 4L19 6"
                                />
                            </svg>
                            Approval and oversight
                        </div>
                    </article>
                </div>

                <!-- Closing banner -->
                <div class="relative mt-14 overflow-hidden rounded-2xl bg-ori-blue-900 px-7 py-9 shadow-xl sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-8">
                    <div class="pointer-events-none absolute right-0 top-0 h-full w-1/3 bg-gradient-to-l from-white/5 to-transparent"></div>

                    <div class="relative">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-ori-yellow-300">
                            Office of Research and Innovation
                        </p>
                        <h3 class="mt-3 font-display text-2xl font-bold text-white sm:text-3xl">
                            Your research journey starts here.
                        </h3>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-ori-blue-100">
                            Contribute to a culture of discovery, collaboration,
                            and meaningful academic impact.
                        </p>
                    </div>

                    <div class="relative mt-6 shrink-0 lg:mt-0">
                        <Link
                            :href="route('login')"
                            class="ori-btn-accent inline-flex w-full items-center justify-center gap-3 rounded-xl px-6 py-3.5 text-sm font-bold shadow-lg transition duration-300 hover:-translate-y-1 sm:w-auto"
                        >
                            Access the portal
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13 7l5 5m0 0l-5 5m5-5H4"
                                />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

<!-- Footer -->
<footer class="relative overflow-hidden border-t-4 border-ori-yellow-300 bg-ori-blue-900 px-2 py-8 text-white lg:px-2">
    <!-- Subtle gold accent line -->
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-ori-yellow-300 via-yellow-400 to-ori-yellow-300"></div>

    <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

        <!-- University Logo and Identity -->
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center border-white/20">
                <img
                    src="/images/ori.ico"
                    alt="LNU Office of Research and Innovation Logo"
                    class="h-full w-full object-contain"
                />
            </div>

            <div>
                <p class="text-sm font-bold tracking-wide text-white">
                    Leyte Normal University
                </p>
                <p class="mt-1 text-xs font-medium text-ori-yellow-300">
                    Office of Research and Innovation
                </p>
                <p class="mt-1 text-xs text-blue-100/80">
                    Advancing Research. Inspiring Innovation.
                </p>
            </div>
        </div>

        <!-- Copyright and Tagline -->
        <div class="border-t border-white/15 pt-4 text-left sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0 sm:text-right">
            <p class="text-xs leading-6 text-blue-100">
                © {{ new Date().getFullYear() }} Leyte Normal University.
            </p>
        </div>

    </div>
</footer>
    </div>
</template>