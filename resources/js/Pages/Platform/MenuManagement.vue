<script setup>
import { Head } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import MenuManagementTableCard from './Components/MenuManagementTableCard.vue';
import MenuManagementFormCard from './Components/MenuManagementFormCard.vue';
import { useMenuManagementPage } from './Composables/useMenuManagementPage';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    menus: { type: Array, default: () => [] },
    tenantsCount: { type: Number, default: 0 },
});

const {
    dashboardPath,
    flashStatus,
    pageErrors,
    menuItems,
    iconOptions,
    createMenuForm,
    deleteMenuForm,
    reorderMenuForm,
    toggleMenuStatusForm,
    isEditMode,
    flatMenus,
    draggedMenuId,
    dragOverMenuId,
    togglingMenuId,
    depthPaddingClass,
    handleRowDragStart,
    handleRowDragOver,
    handleRowDrop,
    handleRowDragEnd,
    startEditMenu,
    deleteMenu,
    toggleMenuStatus,
    parentOptions,
    resetCreateMenuForm,
    submitMenuForm,
    logout,
} = useMenuManagementPage(props);
</script>

<template>
    <Head title="Management Menu Superadmin" />

    <AppDashboardLayout
        title="Management Menu"
        subtitle="Kelola template menu platform"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div class="space-y-5">
            <section class="grid items-start gap-4 xl:grid-cols-4">
                <MenuManagementTableCard
                    :flat-menus="flatMenus"
                    :flash-status="flashStatus"
                    :drag-over-menu-id="dragOverMenuId"
                    :dragged-menu-id="draggedMenuId"
                    :reorder-processing="reorderMenuForm.processing"
                    :delete-processing="deleteMenuForm.processing"
                    :toggle-processing="toggleMenuStatusForm.processing"
                    :toggling-menu-id="togglingMenuId"
                    :errors="{
                        ...pageErrors,
                        status_menu: toggleMenuStatusForm.errors.status_menu || pageErrors.status_menu,
                        is_active: toggleMenuStatusForm.errors.is_active || pageErrors.is_active,
                    }"
                    :depth-padding-class="depthPaddingClass"
                    @row-drag-start="handleRowDragStart"
                    @row-drag-over="handleRowDragOver"
                    @row-drop="handleRowDrop"
                    @row-drag-end="handleRowDragEnd"
                    @edit="startEditMenu"
                    @delete="deleteMenu"
                    @toggle-status="toggleMenuStatus"
                />

                <MenuManagementFormCard
                    :is-edit-mode="isEditMode"
                    :form="createMenuForm"
                    :parent-options="parentOptions"
                    :icon-options="iconOptions"
                    :errors="pageErrors"
                    @cancel-edit="resetCreateMenuForm"
                    @submit="submitMenuForm"
                />
            </section>
        </div>
    </AppDashboardLayout>
</template>
