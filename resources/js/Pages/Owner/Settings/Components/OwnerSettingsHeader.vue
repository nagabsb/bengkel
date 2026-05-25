<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    packageBadgeText: {
        type: String,
        default: '',
    },
    tabItems: {
        type: Array,
        default: () => [],
    },
    currentTab: {
        type: String,
        default: 'permissions',
    },
});
</script>

<template>
    <section class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-900 dark:text-slate-100">Pengaturan Tenant</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Konfigurasi permission tim dan pengaturan nota tenant dari satu halaman.
                </p>
            </div>

            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                {{ packageBadgeText }}
            </span>
        </div>

        <div class="mt-4 inline-flex w-full flex-wrap rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800">
            <Link
                v-for="tab in tabItems"
                :key="tab.key"
                :href="tab.href"
                class="flex min-w-[13rem] flex-1 items-center justify-between gap-2 rounded-lg px-3 py-2 transition"
                :class="currentTab === tab.key
                    ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-700 dark:text-emerald-300'
                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'"
            >
                <span class="text-sm font-semibold">{{ tab.label }}</span>
                <span class="text-[11px] font-medium opacity-80">{{ tab.description }}</span>
            </Link>
        </div>
    </section>
</template>
