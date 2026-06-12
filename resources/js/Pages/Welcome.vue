<script setup>
const stack = ['Laravel 11', 'Inertia.js', 'Vue 3', 'Tailwind CSS'];
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-white">
        <section class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-16">
            <p class="text-sm font-medium uppercase tracking-wide text-emerald-300">MVP foundation</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-normal sm:text-5xl">Nihongo web foundation is running.</h1>
            <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300">
                This minimal page confirms the Laravel, Inertia, Vue, and Tailwind setup. Product screens and workflows are implemented in later tasks.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <span
                    v-for="item in stack"
                    :key="item"
                    class="rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100"
                >
                    {{ item }}
                </span>
            </div>
        </section>
    </main>
</template>
