<script setup>
import { useAsyncSelect } from '../../Composables/useAsyncSelect';

const props = defineProps({
    id: {
        type: String,
        default: '',
    },
    name: {
        type: String,
        default: '',
    },
    modelValue: {
        type: [String, Number, null],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Pilih data',
    },
    searchPlaceholder: {
        type: String,
        default: 'Cari data...',
    },
    emptyText: {
        type: String,
        default: 'Data tidak ditemukan.',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    clearText: {
        type: String,
        default: '',
    },
    triggerClass: {
        type: String,
        default: '',
    },
    menuMaxHeightClass: {
        type: String,
        default: 'max-h-56',
    },
    optionButtonClass: {
        type: String,
        default: '',
    },
    clearButtonClass: {
        type: String,
        default: '',
    },
    menuWidthClass: {
        type: String,
        default: 'w-full',
    },
    optionsListClass: {
        type: String,
        default: '',
    },
    fixedMenu: {
        type: Boolean,
        default: false,
    },
    menuWidthMultiplier: {
        type: Number,
        default: 1,
    },
    menuOffset: {
        type: Number,
        default: 8,
    },
});

const emit = defineEmits(['update:modelValue']);

const {
    rootRef,
    triggerButtonRef,
    menuRef,
    searchInputRef,
    isOpen,
    query,
    fixedMenuStyles,
    selectedOption,
    filteredOptions,
    highlightedIndex,
    setOptionRef,
    isSameValue,
    toggleMenu,
    chooseOption,
    clearValue,
    handleTriggerKeydown,
    handleSearchKeydown,
} = useAsyncSelect(props, emit);
</script>

<template>
    <div ref="rootRef" class="relative min-w-0">
        <input
            v-if="id || name"
            :id="id || undefined"
            :name="name || id || undefined"
            :value="modelValue ?? ''"
            type="hidden"
        >

        <button
            ref="triggerButtonRef"
            type="button"
            class="flex h-10 min-w-0 w-full cursor-pointer items-center justify-between rounded-xl border border-slate-200 bg-white px-3 text-left text-sm text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-500/10 dark:focus-visible:ring-emerald-400/30"
            :class="triggerClass"
            :disabled="disabled"
            @click="toggleMenu"
            @keydown="handleTriggerKeydown"
        >
            <div class="min-w-0 flex-1">
                <slot name="selected" :option="selectedOption ? selectedOption.raw : null" :placeholder="placeholder">
                    <span
                        class="block truncate"
                        :class="selectedOption ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'"
                        :title="selectedOption ? selectedOption.label : ''"
                    >
                        {{ selectedOption ? selectedOption.label : placeholder }}
                    </span>
                </slot>
            </div>

            <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500" aria-hidden="true">
                <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <div
            v-if="isOpen"
            ref="menuRef"
            class="rounded-xl border border-slate-200 bg-white shadow-lg shadow-slate-200/60 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/30"
            :class="[props.fixedMenu ? 'z-[70]' : 'z-40 absolute mt-2', !props.fixedMenu ? menuWidthClass : '']"
            :style="props.fixedMenu ? fixedMenuStyles : undefined"
        >
            <div class="border-b border-slate-200 p-2 dark:border-slate-700">
                <input
                    ref="searchInputRef"
                    v-model="query"
                    type="text"
                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                    :placeholder="searchPlaceholder"
                    @click.stop
                    @keydown="handleSearchKeydown"
                >
            </div>

            <div class="overflow-y-auto py-1" :class="[menuMaxHeightClass, optionsListClass]">
                <button
                    v-if="clearable"
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between px-3 py-2 text-left text-sm text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-400 dark:hover:bg-emerald-500/15 dark:hover:text-emerald-300"
                    :class="clearButtonClass"
                    @click="clearValue"
                >
                    <span>{{ clearText || placeholder }}</span>
                </button>

                <button
                    v-for="(option, optionIndex) in filteredOptions"
                    :key="`option-${String(option.value)}`"
                    :ref="(element) => setOptionRef(element, optionIndex)"
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between px-3 py-2 text-left text-sm transition"
                    :class="[
                        optionButtonClass,
                        optionIndex === highlightedIndex
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : isSameValue(option.value, modelValue)
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-300 dark:hover:bg-emerald-500/15 dark:hover:text-emerald-300',
                    ]"
                    :title="option.label"
                    :aria-selected="isSameValue(option.value, modelValue)"
                    @click="chooseOption(option)"
                >
                    <slot name="option" :option="option.raw" :selected="isSameValue(option.value, modelValue)">
                        <span class="truncate">{{ option.label }}</span>
                    </slot>
                </button>

                <p v-if="filteredOptions.length === 0" class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">
                    {{ emptyText }}
                </p>
            </div>
        </div>
    </div>
</template>
