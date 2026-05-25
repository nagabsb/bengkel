<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { resolveMenuIcon } from '../../Utils/menuIcons';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    isSidebarCollapsed: {
        type: Boolean,
        default: false,
    },
    isSubmenuOpen: {
        type: Boolean,
        default: false,
    },
    isParentActive: {
        type: Boolean,
        default: false,
    },
    showCollapsedSubmenu: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['parent-click', 'close-collapsed-submenu']);

const hasChildren = computed(() => Array.isArray(props.item?.children) && props.item.children.length > 0);
</script>

<template>
    <div class="relative space-y-1" :data-collapsed-submenu-root="hasChildren ? '' : null">
        <component
            v-if="!hasChildren"
            :is="item.href ? Link : 'button'"
            :href="item.href || undefined"
            :type="item.href ? undefined : 'button'"
            class="group flex cursor-pointer items-center rounded-xl py-2.5 text-left text-sm font-medium transition motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 active:scale-95"
            :class="[
                isSidebarCollapsed ? 'mx-auto h-12 w-12 justify-center px-0' : 'w-full justify-between px-3',
                item.active
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100',
            ]"
            :title="isSidebarCollapsed ? item.label : undefined"
        >
            <span class="flex items-center" :class="isSidebarCollapsed ? '' : 'gap-3'">
                <span
                    class="grid h-8 w-8 place-items-center rounded-lg border transition"
                    :class="item.active
                        ? 'border-emerald-200 bg-white text-emerald-600 dark:border-emerald-400/30 dark:bg-slate-900 dark:text-emerald-300'
                        : 'border-slate-200 bg-white text-slate-500 group-hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:group-hover:border-emerald-400/40'"
                >
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path
                            v-for="(pathValue, pathIndex) in resolveMenuIcon(item.icon)"
                            :key="`${item.key}-${pathIndex}`"
                            :d="pathValue"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </span>
                <span
                    class="whitespace-nowrap overflow-hidden transition-all duration-150 ease-out"
                    :class="isSidebarCollapsed ? 'hidden' : 'w-28 translate-x-0 opacity-100'"
                >
                    {{ item.label }}
                </span>
            </span>

            <span
                v-if="item.badge && !isSidebarCollapsed"
                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="item.active
                    ? 'bg-white text-emerald-700 dark:bg-slate-900 dark:text-emerald-300'
                    : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
            >
                {{ item.badge }}
            </span>
        </component>

        <component
            v-else
            :is="'button'"
            type="button"
            class="group flex cursor-pointer items-center rounded-xl py-2.5 text-left text-sm font-medium transition motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 active:scale-95"
            :class="[
                isSidebarCollapsed ? 'mx-auto h-12 w-12 justify-center px-0' : 'w-full justify-between px-3',
                isParentActive
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100',
            ]"
            :title="isSidebarCollapsed ? item.label : undefined"
            @click="emit('parent-click', item)"
        >
            <span class="flex items-center" :class="isSidebarCollapsed ? '' : 'gap-3'">
                <span
                    class="grid h-8 w-8 place-items-center rounded-lg border transition"
                    :class="isParentActive
                        ? 'border-emerald-200 bg-white text-emerald-600 dark:border-emerald-400/30 dark:bg-slate-900 dark:text-emerald-300'
                        : 'border-slate-200 bg-white text-slate-500 group-hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:group-hover:border-emerald-400/40'"
                >
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path
                            v-for="(pathValue, pathIndex) in resolveMenuIcon(item.icon)"
                            :key="`${item.key}-${pathIndex}`"
                            :d="pathValue"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </span>
                <span
                    class="whitespace-nowrap overflow-hidden transition-all duration-150 ease-out"
                    :class="isSidebarCollapsed ? 'hidden' : 'w-28 translate-x-0 opacity-100'"
                >
                    {{ item.label }}
                </span>
            </span>

            <svg
                v-if="!isSidebarCollapsed"
                viewBox="0 0 24 24"
                fill="none"
                class="h-4 w-4 text-slate-400 transition-transform duration-200 ease-out dark:text-slate-500"
                :class="isSubmenuOpen ? 'rotate-90' : ''"
                aria-hidden="true"
            >
                <path d="M10 7L15 12L10 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </component>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-x-1 scale-95 opacity-0"
            enter-to-class="translate-x-0 scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-x-0 scale-100 opacity-100"
            leave-to-class="translate-x-1 scale-95 opacity-0"
        >
            <div
                v-if="hasChildren && isSidebarCollapsed && showCollapsedSubmenu"
                class="absolute left-full top-0 z-30 ml-3 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-200/80 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/40"
            >
                <p class="px-2 pb-1 pt-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    {{ item.label }}
                </p>

                <component
                    v-for="child in item.children"
                    :key="`collapsed-${item.key}-${child.key}`"
                    :is="child.href ? Link : 'button'"
                    :href="child.href || undefined"
                    :type="child.href ? undefined : 'button'"
                    class="flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium transition active:scale-95"
                    :class="child.active
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
                    @click="emit('close-collapsed-submenu')"
                >
                    <span class="truncate">{{ child.label }}</span>
                    <span
                        v-if="child.badge"
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="child.active
                            ? 'bg-white text-emerald-700 dark:bg-slate-900 dark:text-emerald-300'
                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ child.badge }}
                    </span>
                </component>
            </div>
        </Transition>

        <div
            v-if="hasChildren && !isSidebarCollapsed"
            class="grid overflow-hidden transition-all duration-200 ease-out"
            :class="isSubmenuOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
        >
            <div class="min-h-0 space-y-1 overflow-hidden pl-11 pr-2">
                <component
                    v-for="child in item.children"
                    :key="`${item.key}-${child.key}`"
                    :is="child.href ? Link : 'button'"
                    :href="child.href || undefined"
                    :type="child.href ? undefined : 'button'"
                    class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium transition active:scale-95"
                    :class="child.active
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
                >
                    <span class="truncate">{{ child.label }}</span>
                    <span
                        v-if="child.badge"
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="child.active
                            ? 'bg-white text-emerald-700 dark:bg-slate-900 dark:text-emerald-300'
                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ child.badge }}
                    </span>
                </component>
            </div>
        </div>
    </div>
</template>

