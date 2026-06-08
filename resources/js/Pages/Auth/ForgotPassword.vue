<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">
            <div>
                <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal">Reset password</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Enter your email address and we will send a password reset link.
                </p>
            </div>

            <p v-if="status" class="mt-6 rounded border border-emerald-800 bg-emerald-950 px-3 py-2 text-sm text-emerald-200">
                {{ status }}
            </p>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-200">Email address</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        class="mt-2 w-full rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-emerald-400"
                    >
                    <p v-if="form.errors.email" class="mt-2 text-sm text-red-300">{{ form.errors.email }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Send reset link
                </button>
            </form>

            <p class="mt-6 text-sm text-slate-300">
                <Link href="/login" class="font-medium text-emerald-300 hover:text-emerald-200">Back to log in</Link>
            </p>
        </section>
    </main>
</template>
