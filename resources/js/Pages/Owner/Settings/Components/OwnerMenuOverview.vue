<script setup>
defineProps({
    menus: {
        type: Array,
        default: () => [],
    },
    menuSummary: {
        type: Object,
        default: () => ({
            rootMenus: 0,
            childMenus: 0,
            totalMenus: 0,
            lockedByPlan: 0,
        }),
    },
});
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-3">
        <article class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900 xl:col-span-2">
            <header class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Management Menu</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Menu diambil dari database dan siap dipakai sebagai source sidebar dinamis.
                    </p>
                </div>
                <span class="rounded-full border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ menuSummary.totalMenus }} menu
                </span>
            </header>

            <div class="space-y-3 p-4">
                <article
                    v-for="menu in menus"
                    :key="menu.id"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ menu.label }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ menu.route || '-' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="menu.menu_type === 'system'
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'"
                            >
                                {{ menu.menu_type }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="menu.locked_by_plan
                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'
                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'"
                            >
                                {{ menu.locked_by_plan ? 'Terkunci Plan' : 'Aktif Plan' }}
                            </span>
                        </div>
                    </div>

                    <div v-if="menu.children && menu.children.length > 0" class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                        <div
                            v-for="child in menu.children"
                            :key="child.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-700 dark:text-slate-200">{{ child.label }}</p>
                                <p class="truncate text-slate-500 dark:text-slate-400">{{ child.route || '-' }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 font-semibold"
                                :class="child.locked_by_plan
                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'
                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'"
                            >
                                {{ child.locked_by_plan ? 'locked' : 'allowed' }}
                            </span>
                        </div>
                    </div>
                </article>

                <p v-if="menus.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    Belum ada menu di database. Jalankan migration + seeder menu terlebih dahulu.
                </p>
            </div>
        </article>

        <article class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Sinkronisasi Plan</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Plan menentukan menu system mana yang boleh tampil untuk tenant ini.
            </p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                    <dt class="text-slate-500 dark:text-slate-400">Root Menu</dt>
                    <dd class="font-semibold text-slate-700 dark:text-slate-200">{{ menuSummary.rootMenus }}</dd>
                </div>
                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                    <dt class="text-slate-500 dark:text-slate-400">Sub Menu</dt>
                    <dd class="font-semibold text-slate-700 dark:text-slate-200">{{ menuSummary.childMenus }}</dd>
                </div>
                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                    <dt class="text-slate-500 dark:text-slate-400">Locked By Plan</dt>
                    <dd class="font-semibold text-rose-600 dark:text-rose-300">{{ menuSummary.lockedByPlan }}</dd>
                </div>
            </dl>

            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                Mode ini siap dipakai untuk CRUD menu tenant dan override menu system.
            </div>
        </article>
    </section>
</template>
