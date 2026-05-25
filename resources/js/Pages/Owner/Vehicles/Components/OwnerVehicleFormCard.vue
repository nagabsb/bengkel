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
const firstInputId = 'owner-vehicle-brand';

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

    if (target.closest('[data-enter-ignore="true"]')) {
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
                {{ isEditMode ? 'Edit Master Kendaraan' : 'Tambah Master Kendaraan' }}
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
                <div class="grid gap-2" data-enter-ignore="true">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-vehicle-type">
                        Jenis Kendaraan
                    </label>
                    <div class="relative">
                        <select
                            id="owner-vehicle-type"
                            v-model="form.vehicle_type"
                            class="h-11 w-full cursor-pointer appearance-none rounded-xl border px-3 pr-10 text-sm tracking-wide outline-none transition focus:outline-none focus-visible:outline-none"
                            :class="form.errors.vehicle_type
                                ? 'border-rose-400/80 bg-rose-50/40 text-rose-700 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:focus:border-rose-300/70 dark:focus:ring-rose-300/30'
                                : 'border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20'"
                        >
                            <option value="motor">Motor</option>
                            <option value="mobil">Mobil</option>
                        </select>
                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500 dark:text-slate-400"
                            aria-hidden="true"
                        >
                            <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4">
                                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    <p v-if="form.errors.vehicle_type" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.vehicle_type }}</p>
                </div>

                <InputField
                    id="owner-vehicle-brand"
                    v-model="form.brand"
                    label="Merek Kendaraan"
                    placeholder="Contoh: Honda"
                    :error="form.errors.brand"
                />

                <InputField
                    id="owner-vehicle-model"
                    v-model="form.model"
                    label="Model Kendaraan"
                    placeholder="Contoh: Beat"
                    :error="form.errors.model"
                />

                <label
                    class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600 dark:text-slate-300"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Data kendaraan aktif
                </label>

                <p
                    v-if="errors?.create_vehicle && !form.errors.create_vehicle"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_vehicle }}
                </p>
                <p
                    v-if="errors?.update_vehicle && !form.errors.update_vehicle"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.update_vehicle }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Kendaraan') }}
                </button>
            </form>
        </div>
    </article>
</template>

