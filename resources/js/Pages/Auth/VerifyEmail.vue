<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const resend = () => {
    router.post('/email/verification-notification');
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">
            <div>
                <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal">Verify your email</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Confirm your email address before continuing to the dashboard.
                </p>
            </div>

            <p v-if="status === 'verification-link-sent'" class="mt-6 rounded border border-emerald-800 bg-emerald-950 px-3 py-2 text-sm text-emerald-200">
                A new verification link has been sent.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                    @click="resend"
                >
                    Resend email
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                    @click="logout"
                >
                    Log out
                </button>
            </div>
        </section>
    </main>
</template>
