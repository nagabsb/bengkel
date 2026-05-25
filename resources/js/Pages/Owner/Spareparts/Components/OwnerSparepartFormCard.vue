<script setup>
import { computed, nextTick, onMounted } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import CurrencyInput from '../../../../Components/UI/CurrencyInput.vue';
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
    supplierOptions: {
        type: Array,
        default: () => [],
    },
    warehouseOptions: {
        type: Array,
        default: () => [],
    },
    sparePartCategoryOptions: {
        type: Array,
        default: () => [],
    },
    sparePartUnitOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'submit']);
const firstInputId = 'owner-sparepart-name';
const workshopAsyncOptions = computed(() => (
    Array.isArray(props.workshopOptions)
        ? props.workshopOptions.map((workshop) => ({
            value: String(workshop?.value || ''),
            label: String(workshop?.label || ''),
            subtitle: String(workshop?.subtitle || ''),
        })).filter((workshop) => workshop.value !== '' && workshop.label !== '')
        : []
));
const supplierAsyncOptions = computed(() => (
    Array.isArray(props.supplierOptions)
        ? props.supplierOptions.map((supplier) => ({
            value: String(supplier?.id || ''),
            label: String(supplier?.name || ''),
        })).filter((supplier) => supplier.value !== '' && supplier.label !== '')
        : []
));
const warehouseAsyncOptions = computed(() => (
    Array.isArray(props.warehouseOptions)
        ? props.warehouseOptions.map((warehouse) => ({
            value: String(warehouse?.id || ''),
            label: [
                [String(warehouse?.name || ''), String(warehouse?.code || '').trim() !== '' ? `(${String(warehouse?.code || '').trim()})` : '']
                    .filter((segment) => segment !== '')
                    .join(' '),
                String(warehouse?.workshop_name || '').trim() !== ''
                    ? `- ${String(warehouse?.workshop_name || '').trim()}${String(warehouse?.workshop_code || '').trim() !== '' ? ` (${String(warehouse?.workshop_code || '').trim()})` : ''}`
                    : '',
            ]
                .filter((segment) => segment.trim() !== '')
                .join(' '),
        })).filter((warehouse) => warehouse.value !== '' && warehouse.label !== '')
        : []
));
const categoryAsyncOptions = computed(() => {
    const baseOptions = Array.isArray(props.sparePartCategoryOptions)
        ? props.sparePartCategoryOptions.map((category) => ({
            value: String(category?.name || '').trim(),
            label: String(category?.name || '').trim(),
        })).filter((category) => category.value !== '' && category.label !== '')
        : [];

    const selectedCategory = String(props.form?.category || '').trim();
    if (
        selectedCategory !== ''
        && !baseOptions.some((category) => category.value === selectedCategory)
    ) {
        return [
            { value: selectedCategory, label: selectedCategory },
            ...baseOptions,
        ];
    }

    return baseOptions;
});
const unitAsyncOptions = computed(() => {
    const baseOptions = Array.isArray(props.sparePartUnitOptions)
        ? props.sparePartUnitOptions.map((unit) => {
            const name = String(unit?.name || '').trim();
            const symbol = String(unit?.symbol || '').trim();

            return {
                value: name,
                label: symbol !== '' ? `${name} (${symbol})` : name,
            };
        }).filter((unit) => unit.value !== '' && unit.label !== '')
        : [];

    const selectedUnit = String(props.form?.unit || '').trim();
    if (
        selectedUnit !== ''
        && !baseOptions.some((unit) => unit.value === selectedUnit)
    ) {
        return [
            { value: selectedUnit, label: selectedUnit },
            ...baseOptions,
        ];
    }

    return baseOptions;
});

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
                {{ isEditMode ? 'Edit Sparepart' : 'Tambah Sparepart' }}
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
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-workshop">
                        Bengkel Tujuan
                    </label>
                    <AsyncSelect
                        id="owner-sparepart-workshop"
                        v-model="form.workshop_id"
                        :options="workshopAsyncOptions"
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
                    id="owner-sparepart-name"
                    v-model="form.name"
                    label="Nama Sparepart (Wajib)"
                    placeholder="Contoh: Kampas Rem Depan"
                    :error="form.errors.name"
                />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid min-w-0 gap-2" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-supplier">
                            Supplier (Opsional)
                        </label>
                        <AsyncSelect
                            id="owner-sparepart-supplier"
                            v-model="form.supplier_id"
                            :options="supplierAsyncOptions"
                            placeholder="Tanpa supplier"
                            search-placeholder="Cari supplier..."
                            empty-text="Supplier tidak ditemukan."
                            :trigger-class="form.errors.supplier_id
                                ? 'h-11 border-rose-400/80 bg-rose-50/40 text-rose-700 hover:border-rose-400 focus-visible:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/70 dark:focus-visible:ring-rose-300/30'
                                : 'h-11 border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus-visible:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus-visible:ring-emerald-400/20'"
                        />
                        <p v-if="form.errors.supplier_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.supplier_id }}</p>
                    </div>

                    <div class="grid min-w-0 gap-2" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-warehouse">
                            Gudang Stok (Opsional)
                        </label>
                        <AsyncSelect
                            id="owner-sparepart-warehouse"
                            v-model="form.warehouse_id"
                            :options="warehouseAsyncOptions"
                            placeholder="Pilih gudang"
                            search-placeholder="Cari gudang..."
                            empty-text="Gudang tidak ditemukan."
                            :trigger-class="form.errors.warehouse_id
                                ? 'h-11 border-rose-400/80 bg-rose-50/40 text-rose-700 hover:border-rose-400 focus-visible:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/70 dark:focus-visible:ring-rose-300/30'
                                : 'h-11 border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus-visible:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus-visible:ring-emerald-400/20'"
                        />
                        <p v-if="form.errors.warehouse_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.warehouse_id }}</p>
                    </div>
                </div>

                <InputField
                    class="min-w-0"
                    id="owner-sparepart-sku"
                    v-model="form.sku"
                    label="SKU (Opsional)"
                    placeholder="Contoh: KRM-DEP-001"
                    :error="form.errors.sku"
                />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div v-if="categoryAsyncOptions.length > 0" class="grid gap-2" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-category">
                            Kategori (Wajib)
                        </label>
                        <AsyncSelect
                            id="owner-sparepart-category"
                            v-model="form.category"
                            :options="categoryAsyncOptions"
                            placeholder="Pilih kategori"
                            search-placeholder="Cari kategori..."
                            empty-text="Kategori tidak ditemukan."
                            :clearable="false"
                            :trigger-class="form.errors.category
                                ? 'h-11 border-rose-400/80 bg-rose-50/40 text-rose-700 hover:border-rose-400 focus-visible:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/70 dark:focus-visible:ring-rose-300/30'
                                : 'h-11 border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus-visible:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus-visible:ring-emerald-400/20'"
                        />
                        <p v-if="form.errors.category" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.category }}</p>
                    </div>
                    <InputField
                        v-else
                        id="owner-sparepart-category"
                        v-model="form.category"
                        label="Kategori (Wajib)"
                        placeholder="Contoh: Rem"
                        :error="form.errors.category"
                    />

                    <div v-if="unitAsyncOptions.length > 0" class="grid gap-2" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-unit">
                            Satuan (Wajib)
                        </label>
                        <AsyncSelect
                            id="owner-sparepart-unit"
                            v-model="form.unit"
                            :options="unitAsyncOptions"
                            placeholder="Pilih satuan"
                            search-placeholder="Cari satuan..."
                            empty-text="Satuan tidak ditemukan."
                            :clearable="false"
                            :trigger-class="form.errors.unit
                                ? 'h-11 border-rose-400/80 bg-rose-50/40 text-rose-700 hover:border-rose-400 focus-visible:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/70 dark:focus-visible:ring-rose-300/30'
                                : 'h-11 border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus-visible:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus-visible:ring-emerald-400/20'"
                        />
                        <p v-if="form.errors.unit" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.unit }}</p>
                    </div>
                    <InputField
                        v-else
                        id="owner-sparepart-unit"
                        v-model="form.unit"
                        label="Satuan (Wajib)"
                        placeholder="Contoh: pcs"
                        :error="form.errors.unit"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-purchase-price">
                            Harga Beli (Opsional)
                        </label>
                        <CurrencyInput
                            id="owner-sparepart-purchase-price"
                            v-model="form.purchase_price"
                            placeholder="0"
                        />
                        <p v-if="form.errors.purchase_price" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.purchase_price }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-selling-price">
                            Harga Jual (Opsional)
                        </label>
                        <CurrencyInput
                            id="owner-sparepart-selling-price"
                            v-model="form.selling_price"
                            placeholder="0"
                        />
                        <p v-if="form.errors.selling_price" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.selling_price }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <InputField
                        id="owner-sparepart-stock"
                        v-model="form.stock"
                        type="number"
                        label="Stok Saat Ini"
                        placeholder="Contoh: 10"
                        :error="form.errors.stock"
                    />
                    <InputField
                        id="owner-sparepart-minimum-stock"
                        v-model="form.minimum_stock"
                        type="number"
                        label="Stok Minimum (Opsional)"
                        placeholder="Contoh: 2"
                        :error="form.errors.minimum_stock"
                    />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-sparepart-notes">
                        Catatan (Opsional)
                    </label>
                    <textarea
                        id="owner-sparepart-notes"
                        v-model="form.notes"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                        placeholder="Catatan tambahan sparepart"
                    />
                    <p v-if="form.errors.notes" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.notes }}</p>
                </div>

                <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Sparepart aktif
                </label>

                <p
                    v-if="errors?.create_sparepart && !form.errors.create_sparepart"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_sparepart }}
                </p>
                <p
                    v-if="errors?.update_sparepart && !form.errors.update_sparepart"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.update_sparepart }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Sparepart') }}
                </button>
            </form>
        </div>
    </article>
</template>
