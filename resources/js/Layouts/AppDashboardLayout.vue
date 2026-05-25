<script setup>
import DashboardHeader from './Components/DashboardHeader.vue';
import DashboardSidebar from './Components/DashboardSidebar.vue';

defineProps({
    title: {
        type: String,
        default: 'Dasbor',
    },
    subtitle: {
        type: String,
        default: 'Ringkasan dan statistik utama',
    },
    searchPlaceholder: {
        type: String,
        default: 'Cari sesuatu...',
    },
    homeHref: {
        type: String,
        default: '/dashboard',
    },
    roleLabel: {
        type: String,
        default: 'Admin',
    },
    user: {
        type: Object,
        default: () => ({}),
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['logout']);
</script>

<template>
    <div class="h-dvh overflow-hidden bg-emerald-50/40 text-slate-900 transition-colors dark:bg-slate-900 dark:text-slate-100">
        <div class="flex h-full">
            <DashboardSidebar
                :home-href="homeHref"
                :role-label="roleLabel"
                :menu-items="menuItems"
            />

            <div class="dashboard-scroll flex min-w-0 flex-1 flex-col overflow-x-hidden overflow-y-auto">
                <DashboardHeader
                    :title="title"
                    :subtitle="subtitle"
                    :search-placeholder="searchPlaceholder"
                    :role-label="roleLabel"
                    :user="user"
                    :menu-items="menuItems"
                    @logout="emit('logout')"
                />

                <main class="flex-1 p-4 lg:p-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
