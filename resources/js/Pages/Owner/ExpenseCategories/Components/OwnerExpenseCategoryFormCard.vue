<script setup>
import { nextTick, onMounted } from 'vue';
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
});

const emit = defineEmits(['close', 'submit']);
const firstInputId = 'owner-expense-category-name';

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
    if (!props.isEditMode) {
        focusFirstInput();
    }
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
                {{ isEditMode ? 'Edit Kategori Pengeluaran' : 'Tambah Kategori Pengeluaran' }}
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
                <InputField
                    id="owner-expense-category-name"
                    v-model="form.name"
                    label="Nama Kategori (Wajib)"
                    placeholder="Contoh: Operasional"
                    :error="form.errors.name"
                />

                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-expense-category-description">
                        Deskripsi (Opsional)
                    </label>
                    <textarea
                        id="owner-expense-category-description"
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                        placeholder="Catatan tambahan kategori"
                    />
                    <p v-if="form.errors.description" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.description }}</p>
                </div>

                <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Kategori aktif
                </label>

                <p
                    v-if="errors?.create_expense_category && !form.errors.create_expense_category"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_expense_category }}
                </p>
                <p
                    v-if="errors?.update_expense_category && !form.errors.update_expense_category"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.update_expense_category }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Kategori') }}
                </button>
            </form>
        </div>
    </article>
</template>
