<script setup>
import { Head } from '@inertiajs/vue3';
import { useThemeMode } from '@/Composables/useThemeMode';

const props = defineProps({
    statusCode: {
        type: Number,
        default: 403,
    },
    title: {
        type: String,
        default: 'Akses Ditolak',
    },
    message: {
        type: String,
        default: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
    },
});

useThemeMode();

const goBack = () => {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
        return;
    }

    if (typeof window !== 'undefined') {
        window.location.assign(route('dashboard'));
    }
};
</script>

<template>
    <Head :title="`${statusCode} ${title}`" />

    <main class="relative min-h-dvh overflow-hidden bg-gradient-to-br from-emerald-50 via-white to-slate-100 px-4 py-10 text-slate-900 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-emerald-950/20 dark:text-slate-100 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(16,185,129,0.12),transparent_40%),radial-gradient(circle_at_80%_85%,rgba(16,185,129,0.08),transparent_42%)] dark:bg-[radial-gradient(circle_at_20%_20%,rgba(16,185,129,0.18),transparent_40%),radial-gradient(circle_at_80%_85%,rgba(16,185,129,0.1),transparent_42%)]" aria-hidden="true" />

        <section class="relative mx-auto flex min-h-[calc(100dvh-5rem)] w-full max-w-3xl items-center justify-center">
            <article class="relative w-full overflow-hidden rounded-3xl border border-emerald-200/80 bg-white/90 p-6 shadow-[0_24px_70px_-32px_rgba(16,185,129,0.45)] backdrop-blur-xl dark:border-emerald-400/35 dark:bg-slate-900/85 dark:shadow-[0_30px_80px_-36px_rgba(16,185,129,0.55)] sm:p-8 lg:p-10">
                <div class="pointer-events-none absolute right-5 top-2 text-7xl font-black leading-none text-emerald-100 dark:text-emerald-400/10 sm:text-8xl">
                    403
                </div>

                <div class="relative z-10 inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-bold tracking-wide text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-300" />
                    ERROR {{ statusCode }}
                </div>

                <div class="relative z-10 mt-5 flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl border border-emerald-300 bg-emerald-100/70 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300">
                        <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                            <path d="M12 3.5 5 6.5v5.8c0 4.4 2.8 8.5 7 9.7 4.2-1.2 7-5.3 7-9.7V6.5l-7-3Z" stroke="currentColor" stroke-width="1.8" />
                            <path d="M9.5 12.2a2.5 2.5 0 1 1 5 0v2.3h-5v-2.3Z" stroke="currentColor" stroke-width="1.8" />
                            <path d="M12 10.3v1.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <h1 class="text-3xl font-extrabold leading-tight text-slate-900 dark:text-white sm:text-4xl">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-7 text-slate-600 dark:text-slate-200 sm:text-base">{{ message }}</p>
                    </div>
                </div>

                <div class="relative z-10 mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">
                    Silakan kembali ke halaman sebelumnya untuk melanjutkan aktivitas Anda.
                </div>

                <div class="relative z-10 mt-8 flex">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600 active:scale-95 dark:border-emerald-400 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400"
                        @click="goBack"
                    >
                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                            <path d="m8.5 5.5-4 4m0 0 4 4m-4-4h11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Kembali
                    </button>
                </div>
            </article>
        </section>
    </main>
</template>

