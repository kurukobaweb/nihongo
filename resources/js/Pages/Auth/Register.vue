<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">
            <div>
                <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal">Create account</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Set up an email and password account for the MVP.
                </p>
            </div>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-200">Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        class="mt-2 w-full rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-emerald-400"
                    >
                    <p v-if="form.errors.name" class="mt-2 text-sm text-red-300">{{ form.errors.name }}</p>
                </div>

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

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-200">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="mt-2 w-full rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-emerald-400"
                    >
                    <p v-if="form.errors.password" class="mt-2 text-sm text-red-300">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-200">Confirm password</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="mt-2 w-full rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-emerald-400"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Create account
                </button>
            </form>

            <p class="mt-6 text-sm text-slate-300">
                Already have an account?
                <Link href="/login" class="font-medium text-emerald-300 hover:text-emerald-200">Log in</Link>
            </p>
        </section>
    </main>
</template>
