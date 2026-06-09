<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto flex min-h-screen w-full max-w-4xl flex-col justify-center px-6 py-12">
            <div>
                <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal">学習ホーム</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                    {{ user?.name }}さん、ログイン中です。問題選択や録音機能は後続タスクで実装します。
                </p>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <Link
                    href="/questions"
                    class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                >
                    問題一覧へ
                </Link>
                <Link
                    href="/"
                    class="rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                >
                    トップへ
                </Link>
                <button
                    type="button"
                    class="rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                    @click="logout"
                >
                    ログアウト
                </button>
            </div>
        </section>
    </main>
</template>
