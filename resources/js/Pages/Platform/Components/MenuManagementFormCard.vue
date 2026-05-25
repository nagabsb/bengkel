<script setup>
import AsyncSelect from '../../../Components/UI/AsyncSelect.vue';
import { resolveMenuIcon } from '../../../Utils/menuIcons';

defineProps({
    isEditMode: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
    parentOptions: {
        type: Array,
        default: () => [],
    },
    iconOptions: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['cancel-edit', 'submit']);
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900 xl:order-1">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ isEditMode ? 'Edit Menu' : 'Tambah Menu' }}</h3>
            <button
                v-if="isEditMode"
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                @click="emit('cancel-edit')"
            >
                Batal Edit
            </button>
        </div>

        <form class="mt-4 space-y-3" @submit.prevent="emit('submit')">
            <div class="space-y-1.5">
                <label for="menu-parent-id" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Parent</label>
                <AsyncSelect
                    id="menu-parent-id"
                    v-model="form.parent_id"
                    :options="parentOptions"
                    placeholder="Root Menu"
                    search-placeholder="Cari parent menu..."
                    empty-text="Menu tidak ditemukan."
                />
                <p v-if="form.errors.parent_id" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.parent_id }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="menu-label" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Label</label>
                <input id="menu-label" v-model="form.label" type="text" placeholder="Contoh: Layanan"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40">
                <p v-if="form.errors.label" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.label }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="menu-route" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Route</label>
                <input id="menu-route" v-model="form.route" type="text" placeholder="owner.service.index"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40">
                <p v-if="form.errors.route" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.route }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="menu-icon" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Icon</label>
                    <AsyncSelect
                        id="menu-icon"
                        v-model="form.icon"
                        :options="iconOptions"
                        placeholder="Pilih ikon"
                        search-placeholder="Cari ikon..."
                        empty-text="Ikon tidak ditemukan."
                        :clearable="false"
                        menu-max-height-class="max-h-96"
                        :fixed-menu="true"
                        :menu-width-multiplier="2.2"
                        options-list-class="flex flex-wrap content-start gap-2 px-2 pb-2 pt-2"
                        option-button-class="!h-12 !w-12 !px-0 !py-0 justify-center rounded-lg border border-slate-200 bg-white hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-400/40"
                    >
                        <template #selected="{ option, placeholder }">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    :title="option ? option.label : placeholder"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path
                                            v-for="(pathValue, pathIndex) in resolveMenuIcon(option?.icon || 'dashboard')"
                                            :key="`selected-icon-${pathIndex}`"
                                            :d="pathValue"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <span class="truncate" :class="option ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'">
                                    {{ option ? option.label : placeholder }}
                                </span>
                            </div>
                        </template>

                        <template #option="{ option }">
                            <div class="flex w-full items-center justify-center">
                                <span
                                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    :title="option?.label || ''"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path
                                            v-for="(pathValue, pathIndex) in resolveMenuIcon(option?.icon || 'dashboard')"
                                            :key="`option-icon-${option?.value}-${pathIndex}`"
                                            :d="pathValue"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <span class="sr-only">{{ option?.label || '-' }}</span>
                            </div>
                        </template>
                    </AsyncSelect>
                    <p v-if="form.errors.icon" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.icon }}</p>
                </div>
                <div class="space-y-1.5">
                    <label for="menu-sort-order" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sort</label>
                    <input id="menu-sort-order" v-model.number="form.sort_order" type="number" min="0"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400/40">
                    <p v-if="form.errors.sort_order" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.sort_order }}</p>
                </div>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2.5 text-base font-medium text-slate-600 dark:text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 text-emerald-600 checked:border-emerald-600 checked:bg-emerald-600 focus:ring-emerald-500 dark:accent-emerald-400 dark:border-slate-600 dark:bg-slate-800 dark:checked:border-emerald-400 dark:checked:bg-emerald-400">
                Menu aktif
            </label>

            <p v-if="form.errors.create_menu" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.create_menu }}</p>
            <p v-if="form.errors.update_menu" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ form.errors.update_menu }}</p>
            <p v-if="errors?.create_menu && !form.errors.create_menu" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ errors.create_menu }}</p>
            <p v-if="errors?.update_menu && !form.errors.update_menu" class="text-sm font-medium text-rose-600 dark:text-rose-300">{{ errors.update_menu }}</p>

            <button type="submit" class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-base font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                :disabled="form.processing">
                {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Menu') }}
            </button>
        </form>
    </article>
</template>

