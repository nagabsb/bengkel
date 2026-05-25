<script setup>
import { computed } from 'vue';

const props = defineProps({
    permissions: {
        type: Object,
        default: () => ({ data: [] }),
    },
});

const humanizeModule = (moduleName) => String(moduleName || 'general')
    .replaceAll('_', ' ')
    .replaceAll('.', ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());

const groups = computed(() => {
    const rows = Array.isArray(props.permissions?.data) ? props.permissions.data : [];
    const grouped = {};

    rows.forEach((permission) => {
        const menuLabel = String(permission?.menu_label || '').trim();
        const permissionName = String(permission?.name || '');
        const groupKey = menuLabel !== ''
            ? `menu:${menuLabel.toLowerCase()}`
            : (permissionName.includes('.') ? permissionName.split('.')[0] : 'general');
        const groupLabel = menuLabel !== ''
            ? menuLabel
            : humanizeModule(groupKey);

        grouped[groupKey] ??= {
            moduleName: groupKey,
            label: groupLabel,
            items: [],
        };
        grouped[groupKey].items.push(permission);
    });

    return Object.values(grouped)
        .map((group) => ({
            ...group,
            total: group.items.length,
        }))
        .sort((left, right) => left.label.localeCompare(right.label));
});
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Permission Catalog</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ringkasan modul dari halaman aktif.</p>

        <div class="mt-4 space-y-3">
            <article
                v-for="group in groups"
                :key="group.moduleName"
                class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ group.label }}</p>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        {{ group.total }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="permission in group.items.slice(0, 4)"
                        :key="permission.id"
                        class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        {{ permission.action || 'access' }}
                    </span>
                    <span
                        v-if="group.items.length > 4"
                        class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        +{{ group.items.length - 4 }} lainnya
                    </span>
                </div>
            </article>

            <p v-if="groups.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                Belum ada permission tersedia.
            </p>
        </div>
    </article>
</template>
