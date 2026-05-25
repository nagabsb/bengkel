<script setup>
defineProps({
    totalRoles: {
        type: Number,
        default: 0,
    },
    totalPermissions: {
        type: Number,
        default: 0,
    },
    totalModules: {
        type: Number,
        default: 0,
    },
    totalRolePermissionLinks: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Total Role</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ totalRoles }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Template role lintas tenant</p>
        </article>

        <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Total Permission</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ totalPermissions }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hak akses terhubung menu</p>
        </article>

        <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Module</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ totalModules }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kelompok permission</p>
        </article>

        <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Role x Permission</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-slate-100">{{ totalRolePermissionLinks }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Relasi akses aktif</p>
        </article>
    </section>
</template>
