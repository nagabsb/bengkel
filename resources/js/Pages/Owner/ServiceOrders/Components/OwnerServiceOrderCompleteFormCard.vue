<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import CurrencyInput from '../../../../Components/UI/CurrencyInput.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    order: {
        type: Object,
        default: null,
    },
    mechanicOptions: {
        type: Array,
        default: () => [],
    },
    sparePartOptions: {
        type: Object,
        default: () => ({
            mode: 'cursor',
            data: [],
            per_page: 20,
            total: 0,
            from: 0,
            to: 0,
            current_cursor: null,
            next_cursor: null,
            prev_cursor: null,
            has_more_pages: false,
            search: '',
            category: '',
            categories: [],
        }),
    },
    sparePartLoading: {
        type: Boolean,
        default: false,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    'close',
    'submit',
    'search-spareparts',
    'filter-spareparts-category',
    'load-more-spareparts',
]);

const normalizedMechanicOptions = computed(() => (
    props.mechanicOptions
        .map((mechanic) => {
            const id = String(mechanic?.id || '').trim();
            const name = String(mechanic?.name || '').trim();
            const email = String(mechanic?.email || '').trim();

            return {
                value: id,
                label: email !== '' ? `${name} (${email})` : name,
                raw: mechanic,
            };
        })
        .filter((mechanic) => mechanic.value !== '' && mechanic.label !== '')
));

const normalizedSparePartRows = computed(() => (
    Array.isArray(props.sparePartOptions?.data)
        ? props.sparePartOptions.data
            .map((sparePart) => ({
                id: String(sparePart?.id || '').trim(),
                workshop_id: String(sparePart?.workshop_id || '').trim(),
                name: String(sparePart?.name || '').trim(),
                category: String(sparePart?.category || '').trim(),
                unit: String(sparePart?.unit || '').trim(),
                stock: Number(sparePart?.stock || 0),
                selling_price: Number(sparePart?.selling_price || 0),
            }))
            .filter((sparePart) => sparePart.id !== '' && sparePart.name !== '')
        : []
));

const sparePartSearchInput = ref(String(props.sparePartOptions?.search || ''));
const sparePartCategoryInput = ref(String(props.sparePartOptions?.category || ''));
const selectAllVisibleRef = ref(null);
const sparePartQtyInputRefs = ref({});
const lastRequestedSparePartCursor = ref('');
let sparePartSearchDebounceTimer = null;
let isSyncingSearchFromProps = false;
let isSyncingCategoryFromProps = false;

const resolveError = (key) => String(props.form.errors?.[key] || props.errors?.[key] || '');
const isWithoutSpareparts = computed(() => Boolean(props.form.allow_no_spareparts));
const hasApprovedEstimate = computed(() => String(props.order?.latest_estimate?.status || '').trim().toLowerCase() === 'approved');

const ensureSparePartRowsArray = () => {
    if (!Array.isArray(props.form.spareparts)) {
        props.form.spareparts = [];
    }

    return props.form.spareparts;
};

const addMechanicRow = () => {
    if (!Array.isArray(props.form.mechanic_user_ids)) {
        props.form.mechanic_user_ids = [''];
        return;
    }

    props.form.mechanic_user_ids.push('');
};

const removeMechanicRow = (index) => {
    if (!Array.isArray(props.form.mechanic_user_ids) || props.form.mechanic_user_ids.length <= 1) {
        props.form.mechanic_user_ids = [''];
        return;
    }

    props.form.mechanic_user_ids.splice(index, 1);
};

const normalizeQtyValue = (value) => {
    const digitsOnly = String(value ?? '').replace(/[^\d]/g, '');
    const parsed = Number(digitsOnly);

    if (!Number.isFinite(parsed) || parsed < 1) {
        return 1;
    }

    return parsed;
};

const findSparePartUsageIndex = (sparePartId) => ensureSparePartRowsArray().findIndex(
    (row) => String(row?.spare_part_id || '').trim() === String(sparePartId || '').trim(),
);

const findSparePartUsageRow = (sparePartId) => {
    const index = findSparePartUsageIndex(sparePartId);
    if (index < 0) {
        return null;
    }

    return ensureSparePartRowsArray()[index] || null;
};

const isSparePartSelected = (sparePartId) => findSparePartUsageIndex(sparePartId) >= 0;

const resolveSelectedQty = (sparePartId) => {
    const row = findSparePartUsageRow(sparePartId);
    if (!row) {
        return '1';
    }

    return String(normalizeQtyValue(row.qty));
};

const sanitizeSparePartRows = () => {
    const rows = ensureSparePartRowsArray();
    const groupedBySparePart = new Map();

    rows.forEach((row) => {
        const sparePartId = String(row?.spare_part_id || '').trim();
        if (sparePartId === '') {
            return;
        }

        const qty = normalizeQtyValue(row?.qty);
        const current = groupedBySparePart.get(sparePartId) || {
            spare_part_id: sparePartId,
            qty: 0,
            warehouse_id: null,
            notes: '',
        };

        current.qty += qty;

        const warehouseId = String(row?.warehouse_id || '').trim();
        if (warehouseId !== '' && !current.warehouse_id) {
            current.warehouse_id = warehouseId;
        }

        const notes = String(row?.notes || '').trim();
        if (notes !== '' && current.notes === '') {
            current.notes = notes;
        }

        groupedBySparePart.set(sparePartId, current);
    });

    props.form.spareparts = Array.from(groupedBySparePart.values()).map((row) => ({
        ...row,
        qty: String(Math.max(1, Number(row.qty || 1))),
    }));
};

const saveSparePartSelection = () => {
    sanitizeSparePartRows();

    if (typeof props.form.clearErrors === 'function') {
        props.form.clearErrors('spareparts');
    }
};

const setSparePartQty = (sparePartId, nextQty) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    const rows = ensureSparePartRowsArray();
    const index = findSparePartUsageIndex(normalizedSparePartId);
    const normalizedQty = Math.max(1, normalizeQtyValue(nextQty));

    if (index < 0) {
        rows.push({
            spare_part_id: normalizedSparePartId,
            qty: String(normalizedQty),
            warehouse_id: '',
            notes: '',
        });

        return;
    }

    rows[index].qty = String(normalizedQty);
};

const decreaseQty = (sparePartId) => {
    if (!isSparePartSelected(sparePartId)) {
        return;
    }

    const currentQty = Number(resolveSelectedQty(sparePartId));
    setSparePartQty(sparePartId, Math.max(1, currentQty - 1));
};

const increaseQty = (sparePartId) => {
    if (!isSparePartSelected(sparePartId)) {
        return;
    }

    const currentQty = Number(resolveSelectedQty(sparePartId));
    setSparePartQty(sparePartId, currentQty + 1);
};

const normalizeQtyInput = (event, sparePartId) => {
    if (!isSparePartSelected(sparePartId)) {
        return;
    }

    const digitsOnly = String(event?.target?.value || '').replace(/[^\d]/g, '');
    if (digitsOnly === '') {
        setSparePartQty(sparePartId, 1);
        return;
    }

    setSparePartQty(sparePartId, Number(digitsOnly));
};

const handleQtyBeforeInput = (event) => {
    if (!event || typeof event.data !== 'string' || event.data === '') {
        return;
    }

    if (!/^\d+$/.test(event.data)) {
        event.preventDefault();
    }
};

const handleQtyKeydown = (event) => {
    const allowedKeys = [
        'Backspace',
        'Delete',
        'ArrowLeft',
        'ArrowRight',
        'ArrowUp',
        'ArrowDown',
        'Tab',
        'Home',
        'End',
        'Enter',
    ];

    if (allowedKeys.includes(event.key)) {
        return;
    }

    if (event.ctrlKey || event.metaKey) {
        const normalizedKey = String(event.key || '').toLowerCase();
        if (['a', 'c', 'x', 'v'].includes(normalizedKey)) {
            return;
        }
    }

    if (!/^\d$/.test(event.key)) {
        event.preventDefault();
    }
};

const handleQtyPaste = (event, sparePartId) => {
    if (!isSparePartSelected(sparePartId)) {
        return;
    }

    const pastedText = String(event?.clipboardData?.getData('text') || '');
    const digitsOnly = pastedText.replace(/[^\d]/g, '');
    if (digitsOnly === pastedText) {
        return;
    }

    event.preventDefault();
    setSparePartQty(sparePartId, digitsOnly === '' ? 1 : Number(digitsOnly));
};

const toggleSparePartSelection = (sparePartId, checked) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    const rows = ensureSparePartRowsArray();
    const index = findSparePartUsageIndex(normalizedSparePartId);

    if (checked) {
        if (index >= 0) {
            rows[index].qty = String(normalizeQtyValue(rows[index].qty));
            return;
        }

        rows.push({
            spare_part_id: normalizedSparePartId,
            qty: '1',
            warehouse_id: '',
            notes: '',
        });

        return;
    }

    if (index >= 0) {
        rows.splice(index, 1);
    }
};

const visibleSparePartIds = computed(() => normalizedSparePartRows.value.map((sparePart) => sparePart.id));

const isAllVisibleSelected = computed(() => {
    if (visibleSparePartIds.value.length === 0) {
        return false;
    }

    return visibleSparePartIds.value.every((sparePartId) => isSparePartSelected(sparePartId));
});

const isSomeVisibleSelected = computed(() => {
    if (visibleSparePartIds.value.length === 0) {
        return false;
    }

    const selectedCount = visibleSparePartIds.value.filter((sparePartId) => isSparePartSelected(sparePartId)).length;

    return selectedCount > 0 && selectedCount < visibleSparePartIds.value.length;
});

watch(
    isSomeVisibleSelected,
    (isIndeterminate) => {
        if (typeof window === 'undefined' || typeof HTMLInputElement === 'undefined') {
            return;
        }

        if (!(selectAllVisibleRef.value instanceof HTMLInputElement)) {
            return;
        }

        selectAllVisibleRef.value.indeterminate = isIndeterminate;
    },
    { immediate: true },
);

const toggleSelectAllVisible = (event) => {
    const shouldSelect = Boolean(event?.target?.checked);
    if (isWithoutSpareparts.value) {
        return;
    }

    visibleSparePartIds.value.forEach((sparePartId) => {
        toggleSparePartSelection(sparePartId, shouldSelect);
    });
};

const hasVisibleSparePartRows = computed(() => normalizedSparePartRows.value.length > 0);
const sparePartCategoryOptions = computed(() => {
    if (!Array.isArray(props.sparePartOptions?.categories)) {
        return [];
    }

    const seen = new Set();

    return props.sparePartOptions.categories
        .map((category) => String(category || '').trim())
        .filter((category) => {
            if (category === '') {
                return false;
            }

            const key = category.toLowerCase();
            if (seen.has(key)) {
                return false;
            }

            seen.add(key);
            return true;
        });
});
const normalizedSparePartCategoryOptions = computed(() => sparePartCategoryOptions.value.map((category) => ({
    value: category,
    label: category,
    raw: {
        category,
    },
})));

const handleSparePartCheckboxChange = (sparePartId, event) => {
    const checked = Boolean(event?.target?.checked);
    toggleSparePartSelection(sparePartId, checked);
    saveSparePartSelection();

    if (checked) {
        focusSparePartQtyInput(sparePartId);
    }
};

const shouldIgnoreSparePartRowToggle = (event) => {
    const target = event?.target;
    if (!target || typeof target.closest !== 'function') {
        return false;
    }

    return Boolean(target.closest('button, input, textarea, select, label, a'));
};

const handleSparePartRowClick = (sparePartId, event = null) => {
    if (isWithoutSpareparts.value || props.form.processing) {
        return;
    }

    if (shouldIgnoreSparePartRowToggle(event)) {
        return;
    }

    if (!isSparePartSelected(sparePartId)) {
        toggleSparePartSelection(sparePartId, true);
        saveSparePartSelection();
    }

    focusSparePartQtyInput(sparePartId);
};

const handleSparePartRowKeydown = (event, sparePartId) => {
    if (shouldIgnoreSparePartRowToggle(event)) {
        return;
    }

    const key = String(event?.key || '');
    if (!['Enter', ' '].includes(key)) {
        return;
    }

    event.preventDefault();
    handleSparePartRowClick(sparePartId);
};

const handleDecreaseQty = (sparePartId) => {
    decreaseQty(sparePartId);
    saveSparePartSelection();
};

const handleIncreaseQty = (sparePartId) => {
    increaseQty(sparePartId);
    saveSparePartSelection();
};

const hasMoreSparePartRows = computed(() => Boolean(
    props.sparePartOptions?.has_more_pages
    && String(props.sparePartOptions?.next_cursor || '').trim() !== '',
));

const requestNextSparePartCursor = () => {
    if (props.sparePartLoading || isWithoutSpareparts.value) {
        return;
    }

    const nextCursor = String(props.sparePartOptions?.next_cursor || '').trim();
    if (nextCursor === '' || nextCursor === lastRequestedSparePartCursor.value) {
        return;
    }

    lastRequestedSparePartCursor.value = nextCursor;
    emit('load-more-spareparts', nextCursor);
};

const handleSparePartScroll = (event) => {
    const element = event?.target;
    if (!(element instanceof HTMLElement)) {
        return;
    }

    if (element.scrollHeight - (element.scrollTop + element.clientHeight) <= 72) {
        requestNextSparePartCursor();
    }
};

watch(
    () => props.sparePartOptions?.next_cursor,
    () => {
        if (!props.sparePartLoading) {
            lastRequestedSparePartCursor.value = '';
        }
    },
);

watch(
    () => props.sparePartOptions?.search,
    (nextSearch) => {
        const normalizedSearch = String(nextSearch || '');
        if (normalizedSearch !== sparePartSearchInput.value) {
            isSyncingSearchFromProps = true;
            sparePartSearchInput.value = normalizedSearch;
        }
    },
);

watch(
    () => props.sparePartOptions?.category,
    (nextCategory) => {
        const normalizedCategory = String(nextCategory || '');
        if (normalizedCategory !== sparePartCategoryInput.value) {
            isSyncingCategoryFromProps = true;
            sparePartCategoryInput.value = normalizedCategory;
        }
    },
);

watch(
    sparePartSearchInput,
    (value) => {
        if (isSyncingSearchFromProps) {
            isSyncingSearchFromProps = false;
            return;
        }

        if (sparePartSearchDebounceTimer !== null) {
            clearTimeout(sparePartSearchDebounceTimer);
        }

        sparePartSearchDebounceTimer = setTimeout(() => {
            emit('search-spareparts', {
                search: String(value || '').trim(),
                category: String(sparePartCategoryInput.value || '').trim(),
            });
        }, 350);
    },
);

watch(
    sparePartCategoryInput,
    (value) => {
        if (isSyncingCategoryFromProps) {
            isSyncingCategoryFromProps = false;
            return;
        }

        emit('filter-spareparts-category', {
            category: String(value || '').trim(),
            search: String(sparePartSearchInput.value || '').trim(),
        });
    },
);

onBeforeUnmount(() => {
    if (sparePartSearchDebounceTimer !== null) {
        clearTimeout(sparePartSearchDebounceTimer);
    }
});

const resolveQtyWarning = (sparePartId, stock, unit) => {
    if (!isSparePartSelected(sparePartId)) {
        return '';
    }

    const qty = Number(resolveSelectedQty(sparePartId));
    const normalizedStock = Number(stock || 0);

    if (qty > normalizedStock) {
        return `Qty melebihi stok (${normalizedStock} ${String(unit || '').trim()})`;
    }

    return '';
};

const resolveCategoryBadgeClass = (category) => {
    const normalized = String(category || '').toLowerCase();

    if (normalized.includes('oli') || normalized.includes('pelumas') || normalized.includes('fluida')) {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/15 dark:text-sky-300';
    }

    if (normalized.includes('rem') || normalized.includes('kopling') || normalized.includes('pengereman')) {
        return 'border-lime-200 bg-lime-50 text-lime-700 dark:border-lime-400/30 dark:bg-lime-500/15 dark:text-lime-300';
    }

    if (normalized.includes('filter')) {
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/15 dark:text-violet-300';
    }

    return 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
};

const handleAllowNoSparepartsChange = () => {
    if (typeof props.form.clearErrors === 'function') {
        props.form.clearErrors('spareparts');
    }
};

const resolveFirstSparePartRowError = () => {
    const formErrors = props.form.errors || {};
    const firstKey = Object.keys(formErrors).find((key) => key.startsWith('spareparts.'));

    return firstKey ? String(formErrors[firstKey] || '') : '';
};

const resolveDisplayValue = (value) => {
    const normalized = String(value || '').trim();
    return normalized !== '' ? normalized : '-';
};

const setSparePartQtyInputRef = (sparePartId, element) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    if (!(element instanceof HTMLInputElement)) {
        delete sparePartQtyInputRefs.value[normalizedSparePartId];
        return;
    }

    sparePartQtyInputRefs.value[normalizedSparePartId] = element;
};

const focusSparePartQtyInput = async (sparePartId) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    await nextTick();

    const targetInput = sparePartQtyInputRefs.value[normalizedSparePartId];
    if (!(targetInput instanceof HTMLInputElement)) {
        return;
    }

    try {
        targetInput.focus({ preventScroll: true });
    } catch {
        targetInput.focus();
    }
    targetInput.select();
};
</script>

<template>
    <article
        class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-emerald-500/20 dark:bg-slate-900"
    >
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Selesaikan Servis</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ order?.code ? `Order: ${order.code}` : 'Isi mekanik dan sparepart terpakai sebelum selesai.' }}
                </p>
            </div>
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

        <div class="modal-scroll-green min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <form id="complete-order-form" class="space-y-5" @submit.prevent="emit('submit')">
                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Detail Order</h4>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <article class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/60">
                            <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Pelanggan
                            </h5>
                            <dl class="mt-2 space-y-1.5 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-slate-500 dark:text-slate-400">Nama</dt>
                                    <dd class="text-right font-medium text-slate-700 dark:text-slate-100">
                                        {{ resolveDisplayValue(order?.customer_name) }}
                                    </dd>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-slate-500 dark:text-slate-400">No. HP</dt>
                                    <dd class="text-right font-medium text-slate-700 dark:text-slate-100">
                                        {{ resolveDisplayValue(order?.customer_phone) }}
                                    </dd>
                                </div>
                            </dl>
                        </article>

                        <article class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/60">
                            <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Kendaraan
                            </h5>
                            <dl class="mt-2 space-y-1.5 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-slate-500 dark:text-slate-400">Model</dt>
                                    <dd class="text-right font-medium text-slate-700 dark:text-slate-100">
                                        {{ resolveDisplayValue(order?.vehicle_name) }}
                                    </dd>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-slate-500 dark:text-slate-400">Nomor Polisi</dt>
                                    <dd class="text-right font-medium text-slate-700 dark:text-slate-100">
                                        {{ resolveDisplayValue(order?.vehicle_plate_number) }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            Mekanik
                            <span class="ml-1 text-rose-500">*</span>
                        </h4>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            @click="addMechanicRow"
                        >
                            + Tambah Mekanik
                        </button>
                    </div>

                    <div
                        v-for="(_, mechanicIndex) in form.mechanic_user_ids"
                        :key="`mechanic-row-${mechanicIndex}`"
                        class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]"
                    >
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Mekanik #{{ mechanicIndex + 1 }}
                                <span class="ml-1 text-rose-500">*</span>
                            </label>
                            <AsyncSelect
                                v-model="form.mechanic_user_ids[mechanicIndex]"
                                :options="normalizedMechanicOptions"
                                placeholder="Pilih mekanik"
                                search-placeholder="Cari mekanik..."
                                trigger-class="h-11"
                                fixed-menu
                            />
                            <p v-if="resolveError(`mechanic_user_ids.${mechanicIndex}`)" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ resolveError(`mechanic_user_ids.${mechanicIndex}`) }}
                            </p>
                        </div>

                        <div class="self-end">
                            <button
                                type="button"
                                class="inline-flex h-11 cursor-pointer items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                                @click="removeMechanicRow(mechanicIndex)"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>

                    <p v-if="resolveError('mechanic_user_ids')" class="text-sm font-medium text-rose-600 dark:text-rose-300">
                        {{ resolveError('mechanic_user_ids') }}
                    </p>
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div
                        v-if="hasApprovedEstimate"
                        class="rounded-lg border border-emerald-200/80 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        Biaya jasa dan sparepart terpakai sudah terisi otomatis dari estimasi yang disetujui pelanggan.
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="complete-order-service-fee">
                                Biaya Jasa
                                <span class="ml-1 text-rose-500">*</span>
                            </label>
                            <CurrencyInput
                                id="complete-order-service-fee"
                                v-model="form.service_fee"
                                placeholder="Contoh: 150000"
                                :disabled="form.processing"
                                input-class="h-11 border-slate-300/80 dark:border-blue-200/20 dark:bg-slate-900/80"
                            />
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Biaya jasa akan ditambahkan ke total transaksi servis.
                            </p>
                            <p v-if="resolveError('service_fee')" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ resolveError('service_fee') }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Opsi Sparepart
                            </label>
                            <label class="inline-flex h-11 w-full cursor-pointer items-center gap-3 rounded-xl border border-slate-300/80 bg-white px-3 text-sm text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/70 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/70 dark:hover:bg-emerald-500/10">
                                <input
                                    v-model="form.allow_no_spareparts"
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30 disabled:cursor-not-allowed"
                                    :disabled="form.processing"
                                    @change="handleAllowNoSparepartsChange"
                                >
                                <span>Selesaikan tanpa sparepart</span>
                            </label>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Aktifkan untuk servis ringan yang tidak memakai sparepart.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Sparepart Terpakai
                                <span v-if="!isWithoutSpareparts" class="ml-1 text-rose-500">*</span>
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Centang sparepart lalu atur jumlah, bisa pilih banyak sekaligus.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="isWithoutSpareparts"
                        class="rounded-lg border border-blue-200/70 bg-blue-50/70 px-3 py-2 text-xs font-medium text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300"
                    >
                        Mode tanpa sparepart aktif. Anda bisa langsung selesaikan servis setelah memilih mekanik.
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                            <div class="space-y-1.5">
                                <label class="sr-only" for="completion-sparepart-search">Cari sparepart</label>
                                <input
                                    id="completion-sparepart-search"
                                    v-model="sparePartSearchInput"
                                    type="text"
                                    class="h-11 w-full rounded-xl border border-slate-300/80 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20"
                                    placeholder="Cari nama sparepart..."
                                    :disabled="form.processing"
                                >
                            </div>
                            <div class="space-y-1.5">
                                <label class="sr-only" for="completion-sparepart-category">Filter kategori sparepart</label>
                                <AsyncSelect
                                    id="completion-sparepart-category"
                                    v-model="sparePartCategoryInput"
                                    :options="normalizedSparePartCategoryOptions"
                                    placeholder="Semua kategori"
                                    search-placeholder="Cari kategori..."
                                    clear-text="Semua kategori"
                                    trigger-class="h-11 border-slate-300/80 bg-white text-slate-900 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100"
                                    menu-max-height-class="max-h-64"
                                    fixed-menu
                                    :disabled="form.processing"
                                >
                                    <template #option="{ option }">
                                        <span class="truncate">{{ option?.category || option?.label || '-' }}</span>
                                    </template>
                                </AsyncSelect>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/60">
                            <div class="max-h-80 overflow-y-auto" @scroll="handleSparePartScroll">
                                <table class="min-w-full border-collapse">
                                    <thead class="sticky top-0 z-10 bg-slate-100/95 dark:bg-slate-900/95">
                                        <tr class="border-b border-slate-200 dark:border-slate-700">
                                            <th class="w-11 px-3 py-2.5 text-left">
                                                <input
                                                    ref="selectAllVisibleRef"
                                                    type="checkbox"
                                                    class="h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30 disabled:cursor-not-allowed"
                                                    :checked="isAllVisibleSelected"
                                                    :disabled="form.processing || !hasVisibleSparePartRows"
                                                    @change="toggleSelectAllVisible"
                                                >
                                            </th>
                                            <th class="px-2 py-2.5 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">
                                                Nama Sparepart
                                            </th>
                                            <th class="w-44 px-2 py-2.5 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">
                                                Kategori
                                            </th>
                                            <th class="w-56 px-3 py-2.5 text-center text-xs font-semibold text-slate-600 dark:text-slate-300">
                                                Jumlah Pakai
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="sparePart in normalizedSparePartRows"
                                            :key="`sparepart-row-${sparePart.id}`"
                                            role="button"
                                            tabindex="0"
                                            class="border-b border-slate-100 transition last:border-b-0 dark:border-slate-800"
                                            :class="[
                                                isSparePartSelected(sparePart.id)
                                                    ? 'cursor-pointer bg-emerald-50/70 dark:bg-emerald-500/10'
                                                    : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40',
                                            ]"
                                            :aria-pressed="isSparePartSelected(sparePart.id) ? 'true' : 'false'"
                                            @click="handleSparePartRowClick(sparePart.id, $event)"
                                            @keydown="handleSparePartRowKeydown($event, sparePart.id)"
                                        >
                                            <td class="px-3 py-2.5 align-top">
                                                <input
                                                    type="checkbox"
                                                    class="mt-1 h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30 disabled:cursor-not-allowed"
                                                    :checked="isSparePartSelected(sparePart.id)"
                                                    :disabled="form.processing"
                                                    @click.stop
                                                    @change="handleSparePartCheckboxChange(sparePart.id, $event)"
                                                >
                                            </td>
                                            <td class="px-2 py-2.5 align-top">
                                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                                    {{ sparePart.name }}
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    Stok {{ sparePart.stock }} {{ sparePart.unit || '-' }}
                                                </p>
                                            </td>
                                            <td class="px-2 py-2.5 align-top">
                                                <span
                                                    class="inline-flex max-w-full items-center rounded-full border px-2 py-0.5 text-xs font-semibold capitalize"
                                                    :class="resolveCategoryBadgeClass(sparePart.category)"
                                                >
                                                    {{ sparePart.category || 'lainnya' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 align-top" @click.stop>
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-slate-300/80 bg-white text-base font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/70 dark:hover:bg-emerald-500/10"
                                                        :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                        aria-label="Kurangi jumlah pakai"
                                                        @click.stop
                                                        @click="handleDecreaseQty(sparePart.id)"
                                                    >
                                                        -
                                                    </button>
                                                    <input
                                                        :value="resolveSelectedQty(sparePart.id)"
                                                        :ref="(element) => setSparePartQtyInputRef(sparePart.id, element)"
                                                        type="text"
                                                        inputmode="numeric"
                                                        class="h-9 w-20 rounded-lg border border-slate-300/80 bg-white px-2 text-center text-sm font-medium text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20"
                                                        placeholder="1"
                                                        :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                        @click.stop
                                                        @input="normalizeQtyInput($event, sparePart.id)"
                                                        @beforeinput="handleQtyBeforeInput"
                                                        @keydown="handleQtyKeydown"
                                                        @paste="handleQtyPaste($event, sparePart.id)"
                                                        @blur="saveSparePartSelection"
                                                    >
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-slate-300/80 bg-white text-base font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/70 dark:hover:bg-emerald-500/10"
                                                        :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                        aria-label="Tambah jumlah pakai"
                                                        @click.stop
                                                        @click="handleIncreaseQty(sparePart.id)"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                                <p
                                                    v-if="resolveQtyWarning(sparePart.id, sparePart.stock, sparePart.unit)"
                                                    class="mt-1 text-center text-xs text-amber-700 dark:text-amber-300"
                                                >
                                                    {{ resolveQtyWarning(sparePart.id, sparePart.stock, sparePart.unit) }}
                                                </p>
                                            </td>
                                        </tr>

                                        <tr v-if="!sparePartLoading && !hasVisibleSparePartRows">
                                            <td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                                Tidak ada data sparepart.
                                            </td>
                                        </tr>

                                        <tr v-if="sparePartLoading">
                                            <td colspan="4" class="px-3 py-4 text-center text-sm text-slate-500 dark:text-slate-400">
                                                Memuat data sparepart...
                                            </td>
                                        </tr>

                                        <tr v-if="!sparePartLoading && hasMoreSparePartRows">
                                            <td colspan="4" class="px-3 py-3 text-center text-xs text-slate-500 dark:text-slate-400">
                                                Scroll ke bawah untuk memuat sparepart berikutnya.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <p v-if="resolveFirstSparePartRowError()" class="text-sm font-medium text-rose-600 dark:text-rose-300">
                            {{ resolveFirstSparePartRowError() }}
                        </p>
                        <p v-if="resolveError('spareparts')" class="text-sm font-medium text-rose-600 dark:text-rose-300">
                            {{ resolveError('spareparts') }}
                        </p>
                    </template>
                </section>

                <section class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="completion-notes">Catatan Pengerjaan (Opsional)</label>
                    <textarea
                        id="completion-notes"
                        v-model="form.completion_notes"
                        rows="3"
                        class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20"
                        placeholder="Contoh: ganti oli mesin, cek rem depan, test ride normal"
                    />
                    <p v-if="resolveError('completion_notes')" class="text-xs text-rose-600 dark:text-rose-300">
                        {{ resolveError('completion_notes') }}
                    </p>
                </section>

                <p v-if="resolveError('update_order_status')" class="text-sm font-medium text-rose-600 dark:text-rose-300">
                    {{ resolveError('update_order_status') }}
                </p>
            </form>
        </div>

        <div class="shrink-0 border-t border-slate-200 bg-white px-5 py-3 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="emit('close')"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    form="complete-order-form"
                    class="inline-flex min-w-40 cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Memproses...' : 'Selesaikan Servis' }}
                </button>
            </div>
        </div>
    </article>
</template>
