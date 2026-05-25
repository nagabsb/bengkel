<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import CurrencyInput from '../../../../Components/UI/CurrencyInput.vue';
import DatePicker from '../../../../Components/UI/DatePicker.vue';
import { formatRupiah } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const createEmptyEstimateItem = () => ({
    item_type: 'service',
    label: '',
    description: '',
    unit_label: '',
    qty: 1,
    unit_price: null,
    spare_part_id: '',
});

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    diagnosisForm: {
        type: Object,
        default: () => ({
            symptoms_text: '',
            context_note: '',
            errors: {},
        }),
    },
    order: {
        type: Object,
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
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
    aiGenerating: {
        type: Boolean,
        default: false,
    },
    diagnosisGenerating: {
        type: Boolean,
        default: false,
    },
    diagnosisDraft: {
        type: Object,
        default: null,
    },
    canUseAiFeature: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'submit',
    'search-spareparts',
    'filter-spareparts-category',
    'load-more-spareparts',
    'generate-diagnosis',
    'generate-ai',
]);

const latestEstimate = computed(() => (
    props.order && typeof props.order === 'object'
        ? (props.order.latest_estimate || null)
        : null
));

const rows = computed(() => (Array.isArray(props.form?.items) ? props.form.items : []));
const indexedRows = computed(() => rows.value.map((row, index) => ({ row, index })));
const sparePartSearchInput = ref(String(props.sparePartOptions?.search || ''));
const sparePartCategoryInput = ref(String(props.sparePartOptions?.category || ''));
const showSelectedOnly = ref(false);
const selectionNotice = ref('');
const selectAllVisibleRef = ref(null);
const sparePartRowRefs = ref({});
const sparePartQtyInputRefs = ref({});
const lastRequestedSparePartCursor = ref('');
let sparePartSearchDebounceTimer = null;
let selectionNoticeTimer = null;
let isSyncingSearchFromProps = false;
let isSyncingCategoryFromProps = false;

const normalizedSparePartRows = computed(() => (
    Array.isArray(props.sparePartOptions?.data)
        ? props.sparePartOptions.data
            .map((sparePart) => ({
                id: String(sparePart?.id || '').trim(),
                name: String(sparePart?.name || '').trim(),
                category: String(sparePart?.category || '').trim(),
                unit: String(sparePart?.unit || '').trim(),
                stock: Number(sparePart?.stock || 0),
                selling_price: Number(sparePart?.selling_price || 0),
            }))
            .filter((sparePart) => sparePart.id !== '' && sparePart.name !== '')
        : []
));

const normalizedSparePartCategoryOptions = computed(() => {
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

            const normalizedKey = category.toLowerCase();
            if (seen.has(normalizedKey)) {
                return false;
            }

            seen.add(normalizedKey);

            return true;
        })
        .map((category) => ({
            value: category,
            label: category,
            category,
        }));
});

const normalizedSparePartOptions = computed(() => {
    const optionsById = new Map();

    normalizedSparePartRows.value.forEach((sparePart) => {
        optionsById.set(sparePart.id, {
            value: sparePart.id,
            label: sparePart.name,
            id: sparePart.id,
            name: sparePart.name,
            category: sparePart.category,
            unit: sparePart.unit,
            stock: sparePart.stock,
            selling_price: sparePart.selling_price,
        });
    });

    rows.value.forEach((row) => {
        const sparePartId = String(row?.spare_part_id || '').trim();
        if (sparePartId === '' || optionsById.has(sparePartId)) {
            return;
        }

        const fallbackLabel = String(row?.label || '').trim();
        const fallbackUnitLabel = String(row?.unit_label || '').trim();
        optionsById.set(sparePartId, {
            value: sparePartId,
            label: fallbackLabel !== '' ? fallbackLabel : `Sparepart ${sparePartId}`,
            id: sparePartId,
            name: fallbackLabel !== '' ? fallbackLabel : `Sparepart ${sparePartId}`,
            category: '',
            unit: fallbackUnitLabel,
            stock: 0,
            selling_price: Number(row?.unit_price || 0),
            is_fallback: true,
        });
    });

    return Array.from(optionsById.values());
});

const isSparePartRow = (row) => String(row?.item_type || 'service').trim().toLowerCase() === 'sparepart';
const serviceRows = computed(() => indexedRows.value.filter(({ row }) => !isSparePartRow(row)));
const sparePartRows = computed(() => indexedRows.value.filter(({ row }) => isSparePartRow(row)));
const filteredSparePartRows = computed(() => (
    showSelectedOnly.value
        ? normalizedSparePartRows.value.filter((sparePart) => isSparePartSelected(sparePart.id))
        : normalizedSparePartRows.value
));
const visibleSparePartIds = computed(() => filteredSparePartRows.value.map((sparePart) => sparePart.id));
const hasVisibleSparePartRows = computed(() => visibleSparePartIds.value.length > 0);

const hasMoreSparePartRows = computed(() => Boolean(
    props.sparePartOptions?.has_more_pages
    && String(props.sparePartOptions?.next_cursor || '').trim() !== '',
));

const isAllVisibleSelected = computed(() => {
    if (visibleSparePartIds.value.length < 1) {
        return false;
    }

    return visibleSparePartIds.value.every((sparePartId) => isSparePartSelected(sparePartId));
});

const isSomeVisibleSelected = computed(() => {
    if (visibleSparePartIds.value.length < 1) {
        return false;
    }

    const selectedCount = visibleSparePartIds.value.filter((sparePartId) => isSparePartSelected(sparePartId)).length;

    return selectedCount > 0 && selectedCount < visibleSparePartIds.value.length;
});

const findSparePartOptionById = (sparePartId) => (
    normalizedSparePartOptions.value.find(
        (option) => String(option?.id || option?.value || '').trim() === String(sparePartId || '').trim(),
    ) || null
);

const findSparePartEstimateEntry = (sparePartId) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return null;
    }

    return sparePartRows.value.find(
        ({ row }) => String(row?.spare_part_id || '').trim() === normalizedSparePartId,
    ) || null;
};

const isSparePartSelected = (sparePartId) => findSparePartEstimateEntry(sparePartId) !== null;

const resolveSparePartDisplayName = (sparePartId, row = null) => {
    const option = findSparePartOptionById(sparePartId);
    if (option) {
        return String(option.name || option.label || '').trim();
    }

    const fallbackLabel = String(row?.label || '').trim();
    if (fallbackLabel !== '') {
        return fallbackLabel;
    }

    return `Sparepart ${String(sparePartId || '').trim()}`;
};

const selectedSparePartSummaryRows = computed(() => (
    sparePartRows.value
        .map(({ row }) => {
            const sparePartId = String(row?.spare_part_id || '').trim();
            if (sparePartId === '') {
                return null;
            }

            const normalizedQty = Number(String(row?.qty ?? '').replace(/[^\d]/g, ''));
            const qty = Number.isFinite(normalizedQty) && normalizedQty > 0 ? normalizedQty : 1;
            const unitPrice = Math.max(0, Number(row?.unit_price || 0));
            const option = findSparePartOptionById(sparePartId);

            return {
                id: sparePartId,
                name: resolveSparePartDisplayName(sparePartId, row),
                category: String(option?.category || '').trim(),
                unit: String(option?.unit || row?.unit_label || '').trim(),
                qty,
                unitPrice,
                subtotal: qty * unitPrice,
            };
        })
        .filter((item) => item !== null)
));

const resolveSelectedSparePartQty = (sparePartId) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return 1;
    }

    return normalizeQtyValue(entry.row.qty);
};

const resolveSelectedSparePartUnitPrice = (sparePartId) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return null;
    }

    const normalizedUnitPrice = Number(entry.row?.unit_price || 0);
    return normalizedUnitPrice > 0 ? normalizedUnitPrice : null;
};

const setSelectedSparePartUnitPrice = (sparePartId, nextUnitPrice) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return;
    }

    const normalizedUnitPrice = Number(nextUnitPrice || 0);
    entry.row.unit_price = Number.isFinite(normalizedUnitPrice) && normalizedUnitPrice > 0
        ? normalizedUnitPrice
        : null;
};

const resolveRowSubtotal = (row) => {
    const qty = isSparePartRow(row)
        ? Math.max(1, Number(row?.qty || 0))
        : 1;
    const unitPrice = Math.max(0, Number(row?.unit_price || 0));

    return qty * unitPrice;
};

const subtotalService = computed(() => rows.value
    .filter((row) => !isSparePartRow(row))
    .reduce((total, row) => total + resolveRowSubtotal(row), 0));

const subtotalSparepart = computed(() => rows.value
    .filter((row) => isSparePartRow(row))
    .reduce((total, row) => total + resolveRowSubtotal(row), 0));

const grandTotal = computed(() => subtotalService.value + subtotalSparepart.value);
const normalizedDiagnosisDraft = computed(() => (
    props.diagnosisDraft && typeof props.diagnosisDraft === 'object'
        ? props.diagnosisDraft
        : null
));
const hasDiagnosisResult = computed(() => Boolean(
    normalizedDiagnosisDraft.value
    && (
        String(normalizedDiagnosisDraft.value.summary || '').trim() !== ''
        || (
            Array.isArray(normalizedDiagnosisDraft.value.possible_causes)
            && normalizedDiagnosisDraft.value.possible_causes.length > 0
        )
    )
));

const addServiceRow = () => {
    if (!Array.isArray(props.form.items)) {
        props.form.items = [];
    }

    props.form.items.push(createEmptyEstimateItem());
};

const isEffectivelyEmptyServiceRow = (row) => {
    if (isSparePartRow(row)) {
        return false;
    }

    const label = String(row?.label || '').trim();
    const description = String(row?.description || '').trim();
    const unitPrice = Number(row?.unit_price || 0);
    const sparePartId = String(row?.spare_part_id || '').trim();

    return label === ''
        && description === ''
        && sparePartId === ''
        && unitPrice <= 0;
};

const removeServiceRow = (index) => {
    if (!Array.isArray(props.form.items) || index < 0 || index >= props.form.items.length) {
        props.form.items = [createEmptyEstimateItem()];
        return;
    }

    props.form.items.splice(index, 1);
    if (props.form.items.length < 1) {
        props.form.items = [createEmptyEstimateItem()];
    }
};

const applySelectedSparePart = (row, sparePartId) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (!row || typeof row !== 'object') {
        return;
    }

    if (normalizedSparePartId === '') {
        row.item_type = 'service';
        row.spare_part_id = '';
        row.label = '';
        row.unit_label = '';
        row.unit_price = null;
        return;
    }

    const sparePart = findSparePartOptionById(normalizedSparePartId);

    row.item_type = 'sparepart';
    row.spare_part_id = normalizedSparePartId;
    if (!sparePart) {
        return;
    }

    row.label = String(sparePart.name || sparePart.label || '').trim();
    row.unit_label = String(sparePart.unit || '').trim();

    const sellingPrice = Number(sparePart.selling_price || 0);
    row.unit_price = sellingPrice > 0 ? sellingPrice : (Number(row.unit_price || 0) > 0 ? Number(row.unit_price) : null);
};

const setSelectionNotice = (message) => {
    const normalizedMessage = String(message || '').trim();

    if (selectionNoticeTimer !== null) {
        clearTimeout(selectionNoticeTimer);
        selectionNoticeTimer = null;
    }

    selectionNotice.value = normalizedMessage;
    if (normalizedMessage === '') {
        return;
    }

    selectionNoticeTimer = setTimeout(() => {
        selectionNotice.value = '';
        selectionNoticeTimer = null;
    }, 2600);
};

const toggleSparePartSelection = (sparePartId, checked, options = {}) => {
    const shouldNotifyWhenAlreadySelected = Boolean(options?.notifyWhenAlreadySelected);
    const shouldFocusWhenAlreadySelected = Boolean(options?.focusWhenAlreadySelected);
    const shouldNotifyWhenRemoved = Boolean(options?.notifyWhenRemoved);
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    if (!Array.isArray(props.form.items)) {
        props.form.items = [createEmptyEstimateItem()];
    }

    const currentEntry = findSparePartEstimateEntry(normalizedSparePartId);

    if (checked) {
        if (currentEntry) {
            applySelectedSparePart(currentEntry.row, normalizedSparePartId);
            if (shouldNotifyWhenAlreadySelected) {
                setSelectionNotice(`${resolveSparePartDisplayName(normalizedSparePartId, currentEntry.row)} sudah dipilih.`);
            }

            if (shouldFocusWhenAlreadySelected) {
                focusSparePartQtyInput(normalizedSparePartId);
            }

            return;
        }

        const nextRow = createEmptyEstimateItem();
        nextRow.qty = 1;
        applySelectedSparePart(nextRow, normalizedSparePartId);

        if (props.form.items.length === 1 && isEffectivelyEmptyServiceRow(props.form.items[0])) {
            props.form.items = [nextRow];
            return;
        }

        props.form.items.push(nextRow);
        return;
    }

    if (!currentEntry) {
        return;
    }

    props.form.items.splice(currentEntry.index, 1);
    if (props.form.items.length < 1) {
        props.form.items = [createEmptyEstimateItem()];
    }

    if (shouldNotifyWhenRemoved) {
        setSelectionNotice(`${resolveSparePartDisplayName(normalizedSparePartId, currentEntry.row)} dihapus dari estimasi.`);
    }
};

const normalizeQtyValue = (value) => {
    const normalizedQty = Number(String(value ?? '').replace(/[^\d]/g, ''));
    if (!Number.isFinite(normalizedQty) || normalizedQty < 1) {
        return 1;
    }

    return normalizedQty;
};

const setSparePartQty = (sparePartId, nextQty) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return;
    }

    entry.row.qty = normalizeQtyValue(nextQty);
};

const decreaseSparePartQty = (sparePartId) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return;
    }

    setSparePartQty(sparePartId, Math.max(1, normalizeQtyValue(entry.row.qty) - 1));
};

const increaseSparePartQty = (sparePartId) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return;
    }

    setSparePartQty(sparePartId, normalizeQtyValue(entry.row.qty) + 1);
};

const handleSparePartQtyInput = (event, sparePartId) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return;
    }

    const raw = String(event?.target?.value || '').replace(/[^\d]/g, '');
    setSparePartQty(sparePartId, raw === '' ? 1 : Number(raw));
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

const resolveSparePartQtyWarning = (sparePartId, stock, unit) => {
    if (!isSparePartSelected(sparePartId)) {
        return '';
    }

    const qty = resolveSelectedSparePartQty(sparePartId);
    const normalizedStock = Number(stock || 0);
    const unitLabel = String(unit || '').trim();

    if (qty > normalizedStock) {
        return `Qty melebihi stok (${normalizedStock} ${unitLabel || '-'})`;
    }

    return '';
};

const handleSparePartCheckboxChange = (sparePartId, event) => {
    const checked = Boolean(event?.target?.checked);
    toggleSparePartSelection(sparePartId, checked, {
        notifyWhenAlreadySelected: checked,
        focusWhenAlreadySelected: checked,
    });

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
    if (props.form.processing) {
        return;
    }

    if (shouldIgnoreSparePartRowToggle(event)) {
        return;
    }

    if (!isSparePartSelected(sparePartId)) {
        toggleSparePartSelection(sparePartId, true);
    } else {
        setSelectionNotice(`${resolveSparePartDisplayName(sparePartId)} sudah dipilih.`);
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

const toggleSelectAllVisible = (event) => {
    const shouldSelect = Boolean(event?.target?.checked);

    visibleSparePartIds.value.forEach((sparePartId) => {
        toggleSparePartSelection(sparePartId, shouldSelect);
    });
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

const setSparePartRowRef = (sparePartId, element) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    if (!(element instanceof HTMLElement)) {
        delete sparePartRowRefs.value[normalizedSparePartId];
        return;
    }

    sparePartRowRefs.value[normalizedSparePartId] = element;
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

const focusSelectedSparePartRow = async (sparePartId) => {
    const normalizedSparePartId = String(sparePartId || '').trim();
    if (normalizedSparePartId === '') {
        return;
    }

    await nextTick();

    const rowElement = sparePartRowRefs.value[normalizedSparePartId];
    if (!(rowElement instanceof HTMLElement)) {
        setSelectionNotice('Item belum terlihat di tabel. Coba ubah filter pencarian atau kategori.');
        return;
    }

    try {
        rowElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch {
        rowElement.scrollIntoView();
    }

    try {
        rowElement.focus({ preventScroll: true });
    } catch {
        rowElement.focus();
    }

    focusSparePartQtyInput(normalizedSparePartId);
};

const removeSelectedSparePart = (sparePartId) => {
    toggleSparePartSelection(sparePartId, false, { notifyWhenRemoved: true });
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

const resolveFieldError = (field) => (
    String(
        props.form?.errors?.[field]
        || props.errors?.[field]
        || '',
    )
);

const resolveDiagnosisError = (field) => {
    if (field === 'symptoms') {
        return String(
            props.diagnosisForm?.errors?.symptoms
            || props.diagnosisForm?.errors?.['symptoms.0']
            || props.errors?.symptoms
            || props.errors?.['symptoms.0']
            || '',
        );
    }

    return String(
        props.diagnosisForm?.errors?.[field]
        || props.errors?.[field]
        || '',
    );
};

const resolveItemError = (index, field) => (
    resolveFieldError(`items.${index}.${field}`)
);

const resolveSparePartItemError = (sparePartId, field) => {
    const entry = findSparePartEstimateEntry(sparePartId);
    if (!entry) {
        return '';
    }

    return resolveItemError(entry.index, field);
};

const resolveFirstSparePartRowError = () => {
    const rowFields = ['spare_part_id', 'label', 'qty', 'unit_price', 'unit_label', 'description'];

    for (const { index } of sparePartRows.value) {
        for (const field of rowFields) {
            const message = resolveItemError(index, field);
            if (message !== '') {
                return message;
            }
        }
    }

    return '';
};

const resolveEstimateStatusClass = (status) => {
    const normalizedStatus = String(status || '').trim().toLowerCase();
    if (normalizedStatus === 'approved') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }
    if (normalizedStatus === 'pending_approval') {
        return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300';
    }
    if (normalizedStatus === 'rejected') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300';
    }
    if (normalizedStatus === 'expired') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-200';
};

const resolveDiagnosisSeverityClass = (severity) => {
    const normalizedSeverity = String(severity || '').trim().toLowerCase();
    if (normalizedSeverity === 'high') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/15 dark:text-rose-300';
    }

    if (normalizedSeverity === 'low') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300';
};

const resolveDiagnosisSeverityLabel = (severity) => {
    const normalizedSeverity = String(severity || '').trim().toLowerCase();
    if (normalizedSeverity === 'high') {
        return 'Tinggi';
    }

    if (normalizedSeverity === 'low') {
        return 'Rendah';
    }

    return 'Sedang';
};

const submit = (submitForApproval) => {
    if (!Array.isArray(props.form.items)) {
        props.form.items = [createEmptyEstimateItem()];
    } else {
        const normalizedItems = props.form.items.filter((row) => (
            row
            && typeof row === 'object'
            && (isSparePartRow(row) || !isEffectivelyEmptyServiceRow(row))
        ));

        props.form.items = normalizedItems.length > 0
            ? normalizedItems
            : [createEmptyEstimateItem()];
    }

    emit('submit', {
        submit_for_approval: Boolean(submitForApproval),
    });
};

const requestNextSparePartCursor = () => {
    if (props.sparePartLoading || showSelectedOnly.value) {
        return;
    }

    const nextCursor = String(props.sparePartOptions?.next_cursor || '').trim();
    if (nextCursor === '' || nextCursor === lastRequestedSparePartCursor.value) {
        return;
    }

    lastRequestedSparePartCursor.value = nextCursor;
    emit('load-more-spareparts', nextCursor);
};

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
        const normalizedSearch = String(nextSearch || '').trim();
        if (normalizedSearch !== sparePartSearchInput.value) {
            isSyncingSearchFromProps = true;
            sparePartSearchInput.value = normalizedSearch;
        }
    },
);

watch(
    () => props.sparePartOptions?.category,
    (nextCategory) => {
        const normalizedCategory = String(nextCategory || '').trim();
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

    if (selectionNoticeTimer !== null) {
        clearTimeout(selectionNoticeTimer);
    }
});
</script>

<template>
    <article class="relative flex max-h-[calc(100dvh-5rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-xl sm:max-h-[calc(100dvh-6rem)] dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="space-y-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Estimasi Biaya & Approval</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Buat rincian biaya sebelum servis dimulai agar tidak ada sengketa.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                    @click="emit('close')"
                >
                    Tutup
                </button>
            </div>

            <section class="grid gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3 text-sm dark:border-blue-400/20 dark:bg-blue-500/10 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Kode Servis</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ order?.code || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Pelanggan</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ order?.customer_name || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Kendaraan</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ order?.vehicle_name || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Tanggal Servis</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ order?.service_date ? formatDateIndonesia(order.service_date) : '-' }}
                    </p>
                </div>
            </section>

            <div
                v-if="latestEstimate"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold"
                :class="resolveEstimateStatusClass(latestEstimate?.status)"
            >
                <span>Estimasi terakhir: {{ latestEstimate?.code || '-' }}</span>
                <span>{{ latestEstimate?.status_label || '-' }}</span>
                <span v-if="latestEstimate?.total_amount">({{ formatRupiah(latestEstimate.total_amount) }})</span>
            </div>
        </header>

        <div class="modal-scroll-green min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-4 pb-6">
            <section
                v-if="resolveFieldError('estimate')"
                class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300"
            >
                {{ resolveFieldError('estimate') }}
            </section>

            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Rincian Item Estimasi</h4>
                </div>

                <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h5 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Item Jasa</h5>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Tambahkan item jasa secara manual.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            @click="addServiceRow"
                        >
                            + Tambah Jasa
                        </button>
                    </div>

                    <div
                        v-if="serviceRows.length < 1"
                        class="rounded-lg border border-dashed border-slate-300 bg-white/80 px-3 py-2 text-xs text-slate-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-400"
                    >
                        Belum ada item jasa. Tambah jasa atau pilih sparepart dari daftar di bawah.
                    </div>

                    <div
                        v-for="({ row, index }, serviceIndex) in serviceRows"
                        :key="`service-row-${index}`"
                        class="space-y-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70"
                    >
                        <div class="grid gap-3 md:grid-cols-12">
                            <div class="space-y-2 md:col-span-6">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                                    Nama Jasa #{{ serviceIndex + 1 }}
                                </label>
                                <input
                                    v-model="row.label"
                                    type="text"
                                    class="h-10 w-full rounded-xl border border-slate-300/80 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                                    placeholder="Contoh: Tune up mesin"
                                >
                                <p v-if="resolveItemError(index, 'label')" class="text-xs text-rose-600 dark:text-rose-300">
                                    {{ resolveItemError(index, 'label') }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-5">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Harga Satuan</label>
                                <CurrencyInput
                                    v-model="row.unit_price"
                                    placeholder="0"
                                />
                                <p v-if="resolveItemError(index, 'unit_price')" class="text-xs text-rose-600 dark:text-rose-300">
                                    {{ resolveItemError(index, 'unit_price') }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Aksi</label>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-full cursor-pointer items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                                    @click="removeServiceRow(index)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-12">
                            <div class="space-y-2 md:col-span-9">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Keterangan Item</label>
                                <textarea
                                    v-model="row.description"
                                    rows="2"
                                    class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                                    placeholder="Detail item agar pelanggan mudah paham"
                                />
                                <p v-if="resolveItemError(index, 'description')" class="text-xs text-rose-600 dark:text-rose-300">
                                    {{ resolveItemError(index, 'description') }}
                                </p>
                            </div>
                            <div class="space-y-2 md:col-span-3">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Subtotal</label>
                                <div class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold leading-10 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                    {{ formatRupiah(resolveRowSubtotal(row)) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h5 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Item Sparepart</h5>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Centang sparepart lalu atur qty dan harga estimasi langsung.
                            </p>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Dipilih
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedSparePartSummaryRows.length }}</span>
                            sparepart • Subtotal
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ formatRupiah(subtotalSparepart) }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                        <div class="space-y-1.5">
                            <label class="sr-only" for="estimate-sparepart-search">Cari sparepart</label>
                            <input
                                id="estimate-sparepart-search"
                                v-model="sparePartSearchInput"
                                type="text"
                                class="h-10 w-full rounded-xl border border-slate-300/80 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                                placeholder="Cari nama sparepart..."
                                :disabled="form.processing"
                            >
                        </div>
                        <div class="space-y-1.5">
                            <label class="sr-only" for="estimate-sparepart-category">Filter kategori sparepart</label>
                            <AsyncSelect
                                id="estimate-sparepart-category"
                                v-model="sparePartCategoryInput"
                                :options="normalizedSparePartCategoryOptions"
                                placeholder="Semua kategori"
                                search-placeholder="Cari kategori..."
                                clear-text="Semua kategori"
                                trigger-class="h-10 border-slate-300/80 bg-white text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                fixed-menu
                                :disabled="form.processing"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input
                                v-model="showSelectedOnly"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                                :disabled="form.processing || selectedSparePartSummaryRows.length < 1"
                            >
                            Tampilkan hanya item terpilih
                        </label>
                        <p v-if="selectionNotice !== ''" class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            {{ selectionNotice }}
                        </p>
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
                                        <th class="w-40 px-2 py-2.5 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            Kategori
                                        </th>
                                        <th class="w-52 px-3 py-2.5 text-center text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            Qty Estimasi
                                        </th>
                                        <th class="w-56 px-3 py-2.5 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            Harga Satuan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="sparePart in filteredSparePartRows"
                                        :key="`estimate-sparepart-row-${sparePart.id}`"
                                        :ref="(element) => setSparePartRowRef(sparePart.id, element)"
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
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                                    {{ sparePart.name }}
                                                </p>
                                                <span
                                                    v-if="isSparePartSelected(sparePart.id)"
                                                    class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                                                >
                                                    Terpilih
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Stok {{ sparePart.stock }} {{ sparePart.unit || '-' }}
                                            </p>
                                            <p v-if="resolveSparePartItemError(sparePart.id, 'label')" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                                                {{ resolveSparePartItemError(sparePart.id, 'label') }}
                                            </p>
                                            <p v-if="resolveSparePartItemError(sparePart.id, 'spare_part_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                                                {{ resolveSparePartItemError(sparePart.id, 'spare_part_id') }}
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
                                                    class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-slate-300/80 bg-white text-base font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-emerald-300/70 dark:hover:bg-emerald-500/10"
                                                    :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                    aria-label="Kurangi qty estimasi"
                                                    @click.stop
                                                    @click="decreaseSparePartQty(sparePart.id)"
                                                >
                                                    -
                                                </button>
                                                <input
                                                    :value="resolveSelectedSparePartQty(sparePart.id)"
                                                    :ref="(element) => setSparePartQtyInputRef(sparePart.id, element)"
                                                    type="text"
                                                    inputmode="numeric"
                                                    class="h-9 w-20 rounded-lg border border-slate-300/80 bg-white px-2 text-center text-sm font-medium text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20"
                                                    placeholder="1"
                                                    :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                    @click.stop
                                                    @input="handleSparePartQtyInput($event, sparePart.id)"
                                                    @beforeinput="handleQtyBeforeInput"
                                                    @keydown="handleQtyKeydown"
                                                    @paste="handleQtyPaste($event, sparePart.id)"
                                                >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-slate-300/80 bg-white text-base font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-emerald-300/70 dark:hover:bg-emerald-500/10"
                                                    :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                    aria-label="Tambah qty estimasi"
                                                    @click.stop
                                                    @click="increaseSparePartQty(sparePart.id)"
                                                >
                                                    +
                                                </button>
                                            </div>
                                            <p
                                                v-if="resolveSparePartQtyWarning(sparePart.id, sparePart.stock, sparePart.unit)"
                                                class="mt-1 text-center text-xs text-amber-700 dark:text-amber-300"
                                            >
                                                {{ resolveSparePartQtyWarning(sparePart.id, sparePart.stock, sparePart.unit) }}
                                            </p>
                                            <p v-if="resolveSparePartItemError(sparePart.id, 'qty')" class="mt-1 text-center text-xs text-rose-600 dark:text-rose-300">
                                                {{ resolveSparePartItemError(sparePart.id, 'qty') }}
                                            </p>
                                        </td>
                                        <td class="px-3 py-2.5 align-top" @click.stop>
                                            <CurrencyInput
                                                :model-value="resolveSelectedSparePartUnitPrice(sparePart.id)"
                                                placeholder="0"
                                                :disabled="form.processing || !isSparePartSelected(sparePart.id)"
                                                input-class="h-9 border-slate-300/80 bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                                @update:model-value="(value) => setSelectedSparePartUnitPrice(sparePart.id, value)"
                                            />
                                            <p v-if="resolveSparePartItemError(sparePart.id, 'unit_price')" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                                                {{ resolveSparePartItemError(sparePart.id, 'unit_price') }}
                                            </p>
                                        </td>
                                    </tr>

                                    <tr v-if="!sparePartLoading && !hasVisibleSparePartRows">
                                        <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                            {{ showSelectedOnly ? 'Belum ada sparepart terpilih yang cocok dengan filter.' : 'Tidak ada data sparepart.' }}
                                        </td>
                                    </tr>

                                    <tr v-if="sparePartLoading">
                                        <td colspan="5" class="px-3 py-4 text-center text-sm text-slate-500 dark:text-slate-400">
                                            Memuat data sparepart...
                                        </td>
                                    </tr>

                                    <tr v-if="!sparePartLoading && hasMoreSparePartRows && !showSelectedOnly">
                                        <td colspan="5" class="px-3 py-3 text-center text-xs text-slate-500 dark:text-slate-400">
                                            Scroll ke bawah untuk memuat sparepart berikutnya.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <p v-if="resolveFirstSparePartRowError()" class="text-xs text-rose-600 dark:text-rose-300">
                    {{ resolveFirstSparePartRowError() }}
                </p>
                <p v-if="resolveFieldError('items')" class="text-xs text-rose-600 dark:text-rose-300">
                    {{ resolveFieldError('items') }}
                </p>
            </section>

            <section class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-400/20 dark:bg-emerald-500/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h6 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Sparepart Terpilih</h6>
                    <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                        {{ selectedSparePartSummaryRows.length }} item
                    </span>
                </div>

                <div v-if="selectedSparePartSummaryRows.length > 0" class="mt-2 space-y-2">
                    <div
                        v-for="selectedItem in selectedSparePartSummaryRows"
                        :key="`selected-sparepart-summary-${selectedItem.id}`"
                        class="rounded-lg border border-emerald-200/80 bg-white px-3 py-2 dark:border-emerald-400/20 dark:bg-slate-900/70"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedItem.name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ selectedItem.category || 'Kategori lainnya' }}
                                </p>
                            </div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {{ formatRupiah(selectedItem.subtotal) }}
                            </p>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ selectedItem.qty }} {{ selectedItem.unit || 'unit' }} x {{ formatRupiah(selectedItem.unitPrice) }}
                            </p>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex h-7 cursor-pointer items-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                                    :disabled="form.processing"
                                    @click="focusSelectedSparePartRow(selectedItem.id)"
                                >
                                    Lihat baris
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-7 cursor-pointer items-center rounded-md border border-rose-200 bg-rose-50 px-2.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                                    :disabled="form.processing"
                                    @click="removeSelectedSparePart(selectedItem.id)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Belum ada sparepart dipilih.
                </p>
            </section>

            <section class="space-y-4 rounded-xl border border-amber-100 bg-amber-50/60 p-3 dark:border-amber-400/20 dark:bg-amber-500/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h6 class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">AI Diagnosa Awal</h6>
                    <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                        {{ hasDiagnosisResult ? 'Siap direview' : 'Belum digenerate' }}
                    </span>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Gejala Tambahan
                        </label>
                        <textarea
                            v-model="diagnosisForm.symptoms_text"
                            rows="4"
                            class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-amber-500/70 focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-amber-400/70 dark:focus:ring-amber-400/20"
                            placeholder="Contoh: suara ngelitik saat rpm rendah, getaran berlebih saat idle"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Tulis per baris atau pisahkan dengan koma agar AI lebih akurat.
                        </p>
                        <p v-if="resolveDiagnosisError('symptoms')" class="text-xs text-rose-600 dark:text-rose-300">
                            {{ resolveDiagnosisError('symptoms') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Konteks Diagnosa
                        </label>
                        <textarea
                            v-model="diagnosisForm.context_note"
                            rows="4"
                            class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-amber-500/70 focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-amber-400/70 dark:focus:ring-amber-400/20"
                            placeholder="Contoh: keluhan muncul saat mesin panas, customer butuh kendaraan untuk perjalanan jauh"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Opsional untuk menambahkan konteks sebelum AI menganalisis gejala.
                        </p>
                        <p v-if="resolveDiagnosisError('context_note')" class="text-xs text-rose-600 dark:text-rose-300">
                            {{ resolveDiagnosisError('context_note') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="hasDiagnosisResult && normalizedDiagnosisDraft"
                    class="rounded-xl border border-amber-200/80 bg-white p-3 dark:border-amber-400/20 dark:bg-slate-900/70"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Ringkasan Diagnosa</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ normalizedDiagnosisDraft.summary || 'Diagnosa awal tersedia. Lanjutkan pemeriksaan teknisi untuk validasi final.' }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300">
                            Confidence {{ Number(normalizedDiagnosisDraft.confidence_level || 0) }}%
                        </span>
                    </div>

                    <div v-if="Array.isArray(normalizedDiagnosisDraft.possible_causes) && normalizedDiagnosisDraft.possible_causes.length > 0" class="mt-3 space-y-2">
                        <article
                            v-for="(cause, causeIndex) in normalizedDiagnosisDraft.possible_causes"
                            :key="`diagnosis-cause-${causeIndex}`"
                            class="rounded-lg border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/70"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ cause.label || `Dugaan ${causeIndex + 1}` }}
                                </p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                                        :class="resolveDiagnosisSeverityClass(cause.severity)"
                                    >
                                        Risiko {{ resolveDiagnosisSeverityLabel(cause.severity) }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                        {{ Number(cause.confidence || 0) }}%
                                    </span>
                                </div>
                            </div>
                            <p v-if="cause.reason" class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ cause.reason }}</p>

                            <div v-if="Array.isArray(cause.recommended_checks) && cause.recommended_checks.length > 0" class="mt-2 space-y-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cek Awal Teknis</p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="(checkItem, checkIndex) in cause.recommended_checks"
                                        :key="`diagnosis-check-${causeIndex}-${checkIndex}`"
                                        class="text-xs text-slate-600 dark:text-slate-300"
                                    >
                                        - {{ checkItem }}
                                    </li>
                                </ul>
                            </div>

                            <div v-if="Array.isArray(cause.recommended_actions) && cause.recommended_actions.length > 0" class="mt-2 space-y-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Saran Tindakan</p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="(actionItem, actionIndex) in cause.recommended_actions"
                                        :key="`diagnosis-action-${causeIndex}-${actionIndex}`"
                                        class="text-xs text-slate-600 dark:text-slate-300"
                                    >
                                        - {{ actionItem }}
                                    </li>
                                </ul>
                            </div>
                        </article>
                    </div>

                    <div v-if="Array.isArray(normalizedDiagnosisDraft.warnings) && normalizedDiagnosisDraft.warnings.length > 0" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Peringatan</p>
                        <ul class="mt-1 space-y-1">
                            <li
                                v-for="(warning, warningIndex) in normalizedDiagnosisDraft.warnings"
                                :key="`diagnosis-warning-${warningIndex}`"
                                class="text-xs text-rose-700 dark:text-rose-300"
                            >
                                - {{ warning }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="Array.isArray(normalizedDiagnosisDraft.customer_advice) && normalizedDiagnosisDraft.customer_advice.length > 0" class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Saran ke Customer</p>
                        <ul class="mt-1 space-y-1">
                            <li
                                v-for="(advice, adviceIndex) in normalizedDiagnosisDraft.customer_advice"
                                :key="`diagnosis-advice-${adviceIndex}`"
                                class="text-xs text-slate-600 dark:text-slate-300"
                            >
                                - {{ advice }}
                            </li>
                        </ul>
                    </div>

                    <p v-if="normalizedDiagnosisDraft.disclaimer" class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {{ normalizedDiagnosisDraft.disclaimer }}
                    </p>
                </div>

                <p v-if="resolveDiagnosisError('diagnosis_ai')" class="text-xs text-rose-600 dark:text-rose-300">
                    {{ resolveDiagnosisError('diagnosis_ai') }}
                </p>
            </section>

            <section class="grid gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/10 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Subtotal Jasa</p>
                    <p class="mt-1 text-base font-bold text-emerald-700 dark:text-emerald-300">{{ formatRupiah(subtotalService) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Subtotal Sparepart</p>
                    <p class="mt-1 text-base font-bold text-emerald-700 dark:text-emerald-300">{{ formatRupiah(subtotalSparepart) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Estimasi</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ formatRupiah(grandTotal) }}</p>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-12">
                <div class="space-y-2 md:col-span-4">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Batas Waktu Approval</label>
                    <DatePicker
                        v-model="form.approval_expires_at"
                        placeholder="Pilih batas waktu"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">Disarankan 1-3 hari untuk keputusan pelanggan.</p>
                    <p v-if="resolveFieldError('approval_expires_at')" class="text-xs text-rose-600 dark:text-rose-300">
                        {{ resolveFieldError('approval_expires_at') }}
                    </p>
                </div>

                <div class="space-y-2 md:col-span-8">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Catatan Internal</label>
                    <textarea
                        v-model="form.internal_note"
                        rows="4"
                        class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                        placeholder="Contoh: estimasi sudah termasuk pengecekan rem belakang"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">Catatan internal untuk tim, tidak ditampilkan sebagai biaya.</p>
                    <p v-if="resolveFieldError('internal_note')" class="text-xs text-rose-600 dark:text-rose-300">
                        {{ resolveFieldError('internal_note') }}
                    </p>
                </div>
            </section>
        </div>

        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] dark:border-slate-700 dark:bg-slate-900">
            <div v-if="canUseAiFeature" class="mr-auto flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                    :disabled="form.processing || aiGenerating || diagnosisGenerating"
                    @click="emit('generate-diagnosis')"
                >
                    {{ diagnosisGenerating ? 'Memproses Diagnosa...' : 'AI Diagnosa Awal' }}
                </button>

                <button
                    type="button"
                    class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-300 dark:hover:bg-violet-500/20"
                    :disabled="form.processing || aiGenerating || diagnosisGenerating"
                    @click="emit('generate-ai')"
                >
                    {{ aiGenerating ? 'Memproses Estimasi...' : 'Generate Estimasi AI' }}
                </button>
            </div>
            <p
                v-else
                class="mr-auto text-xs font-medium text-slate-500 dark:text-slate-400"
            >
                Fitur Generate AI tersedia pada paket dengan AI feature aktif.
            </p>

            <button
                type="button"
                class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                :disabled="form.processing || aiGenerating || diagnosisGenerating"
                @click="emit('close')"
            >
                Batal
            </button>

            <button
                type="button"
                class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-blue-200 bg-blue-50 px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                :disabled="form.processing || aiGenerating || diagnosisGenerating"
                @click="submit(false)"
            >
                {{ form.processing ? 'Menyimpan...' : 'Simpan Draft' }}
            </button>

            <button
                type="button"
                class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                :disabled="form.processing || aiGenerating || diagnosisGenerating"
                @click="submit(true)"
            >
                {{ form.processing ? 'Menyimpan...' : 'Simpan & Buat Link Approval' }}
            </button>
        </footer>
    </article>
</template>
