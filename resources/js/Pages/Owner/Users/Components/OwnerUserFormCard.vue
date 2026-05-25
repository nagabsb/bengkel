<script setup>
import { nextTick, onMounted } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import InputField from '../../../../Components/UI/InputField.vue';

const props = defineProps({
    isEditMode: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    workshopOptions: {
        type: Array,
        default: () => [],
    },
    isWorkshopSelectable: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit']);
const firstInputId = 'owner-user-name';

const focusFirstInput = () => {
    nextTick(() => {
        const firstInput = document.getElementById(firstInputId);
        if (!(firstInput instanceof HTMLInputElement)) {
            return;
        }

        firstInput.focus();
        firstInput.select();
    });
};

const handleEscKey = (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    event.preventDefault();
    emit('close');
};

const handleEnterKey = (event) => {
    if (event.key !== 'Enter' || event.isComposing) {
        return;
    }

    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    const tagName = target.tagName.toLowerCase();
    if (tagName === 'textarea' || tagName === 'button') {
        return;
    }

    event.preventDefault();
    emit('submit');
};

onMounted(() => {
    focusFirstInput();
});
</script>

<template>
    <article
        class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-emerald-500/20 dark:bg-slate-900"
    >
        <div
            class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900"
        >
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ isEditMode ? 'Edit User' : 'Tambah User' }}
            </h3>
            <button
                type="button"
                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                aria-label="Tutup modal"
                @click="emit('close')"
            >
                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="modal-scroll-green min-h-0 overflow-y-auto px-5 pb-5 pt-4">
            <form class="space-y-4" @submit.prevent="emit('submit')" @keydown.esc="handleEscKey" @keydown.enter="handleEnterKey">
                <div v-if="isWorkshopSelectable" class="grid min-w-0 gap-2" data-enter-ignore="true">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-user-workshop">
                        Bengkel Tujuan
                    </label>
                    <AsyncSelect
                        id="owner-user-workshop"
                        v-model="form.workshop_id"
                        :options="workshopOptions"
                        placeholder="Pilih bengkel tujuan"
                        search-placeholder="Cari bengkel..."
                        empty-text="Bengkel tidak ditemukan."
                        :clearable="false"
                        :trigger-class="form.errors.workshop_id
                            ? 'h-11 border-rose-400/80 bg-rose-50/40 text-rose-700 hover:border-rose-400 focus-visible:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/70 dark:focus-visible:ring-rose-300/30'
                            : 'h-11 border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus-visible:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus-visible:ring-emerald-400/20'"
                    >
                        <template #option="{ option }">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ option?.label || '-' }}
                                </p>
                                <p v-if="option?.subtitle" class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ option.subtitle }}
                                </p>
                            </div>
                        </template>
                    </AsyncSelect>
                    <p v-if="form.errors.workshop_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.workshop_id }}</p>
                </div>

                <InputField
                    id="owner-user-name"
                    v-model="form.name"
                    label="Nama User"
                    placeholder="Contoh: Andi Pratama"
                    :error="form.errors.name"
                />

                <InputField
                    id="owner-user-email"
                    v-model="form.email"
                    label="Email"
                    type="email"
                    placeholder="Contoh: andi@email.com"
                    autocomplete="email"
                    :error="form.errors.email"
                />

                <InputField
                    id="owner-user-password"
                    v-model="form.password"
                    :label="isEditMode ? 'Password (Opsional)' : 'Password'"
                    type="password"
                    placeholder="Minimal 8 karakter"
                    autocomplete="new-password"
                    :error="form.errors.password"
                />

                <p v-if="isEditMode" class="text-xs text-slate-500 dark:text-slate-400">
                    Kosongkan password jika tidak ingin diubah.
                </p>

                <div class="space-y-2">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tipe User</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            class="inline-flex w-full cursor-pointer flex-col items-start rounded-xl border px-3 py-2 text-left transition active:scale-[0.99]"
                            :class="String(form.role || '') === 'admin'
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/30 dark:hover:bg-emerald-500/10'"
                            @click="form.role = 'admin'"
                        >
                            <span class="text-sm font-semibold">Admin</span>
                            <span class="text-xs opacity-80">Akses pengelolaan operasional.</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex w-full cursor-pointer flex-col items-start rounded-xl border px-3 py-2 text-left transition active:scale-[0.99]"
                            :class="String(form.role || '') === 'mekanik'
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/30 dark:hover:bg-emerald-500/10'"
                            @click="form.role = 'mekanik'"
                        >
                            <span class="text-sm font-semibold">Mekanik</span>
                            <span class="text-xs opacity-80">Akses pekerjaan teknis servis.</span>
                        </button>
                    </div>
                    <p v-if="form.errors.role" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.role }}</p>
                </div>

                <p
                    v-if="errors?.create_user && !form.errors.create_user"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_user }}
                </p>
                <p
                    v-if="errors?.update_user && !form.errors.update_user"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.update_user }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah User') }}
                </button>
            </form>
        </div>
    </article>
</template>
