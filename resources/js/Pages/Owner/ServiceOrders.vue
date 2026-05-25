<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerServiceOrderFormCard from './ServiceOrders/Components/OwnerServiceOrderFormCard.vue';
import OwnerServiceOrderCompleteFormCard from './ServiceOrders/Components/OwnerServiceOrderCompleteFormCard.vue';
import OwnerServiceOrderEstimateFormCard from './ServiceOrders/Components/OwnerServiceOrderEstimateFormCard.vue';
import OwnerServiceOrderTableCard from './ServiceOrders/Components/OwnerServiceOrderTableCard.vue';
import { formatRupiah } from '../../Utils/formatCurrency';
import { formatDateIndonesia, formatDateTimeIndonesia } from '../../Utils/indonesiaDate';
import {
    fetchOwnerServiceOrders,
    generateOwnerServiceOrderDiagnosisAiDraft,
    generateOwnerServiceOrderEstimateAiDraft,
    storeOwnerServiceOrder,
    storeOwnerServiceOrderEstimate,
    updateOwnerServiceOrderStatus,
} from './Services/ownerServiceOrderService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenantId: {
        type: String,
        default: '',
    },
    package: {
        type: Object,
        default: null,
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Object,
        default: () => ({
            mode: 'cursor',
            data: [],
            per_page: 10,
            total: 0,
            from: 0,
            to: 0,
            current_cursor: null,
            next_cursor: null,
            prev_cursor: null,
            has_more_pages: false,
        }),
    },
    orderFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'service_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    orderSummary: {
        type: Object,
        default: () => ({
            total: 0,
            open: 0,
            in_progress: 0,
            done: 0,
            cancelled: 0,
        }),
    },
    customerOptions: {
        type: Array,
        default: () => [],
    },
    customerVehicleOptions: {
        type: Array,
        default: () => [],
    },
    vehicleMasterOptions: {
        type: Array,
        default: () => [],
    },
    mechanicOptions: {
        type: Array,
        default: () => [],
    },
    completionSparePartOptions: {
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
});

const page = usePage();
const logoutForm = useForm({});
const serviceOrderForm = useForm({
    workshop_id: '',
    customer_id: null,
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    customer_address: '',
    vehicle_id: null,
    vehicle_master_id: null,
    vehicle_type: 'motor',
    vehicle_brand: '',
    vehicle_model: '',
    vehicle_variant: '',
    vehicle_plate_number: '',
    vehicle_year: '',
    vehicle_notes: '',
    service_date: new Date(),
    vehicle_condition: '',
    estimated_days: '',
    complaint: '',
    odometer: '',
});
const completeOrderForm = useForm({
    status: 'done',
    allow_no_spareparts: false,
    service_fee: null,
    mechanic_user_ids: [''],
    spareparts: [],
    completion_notes: '',
});
const estimateForm = useForm({
    approval_expires_at: null,
    internal_note: '',
    submit_for_approval: false,
    items: [{
        item_type: 'service',
        label: '',
        description: '',
        unit_label: '',
        qty: 1,
        unit_price: null,
        spare_part_id: '',
    }],
});
const aiEstimateForm = useForm({
    context_note: '',
});
const aiDiagnosisForm = useForm({
    symptoms_text: '',
    context_note: '',
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    sort_by: 'service_date',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const isServiceOrderModalOpen = ref(false);
const isCompleteOrderModalOpen = ref(false);
const isEstimateModalOpen = ref(false);
const isDetailOrderModalOpen = ref(false);
const selectedCompleteOrder = ref(null);
const selectedEstimateOrder = ref(null);
const selectedDetailOrder = ref(null);
const statusProcessingOrderId = ref('');
const lastAppliedAiEstimateLogId = ref('');
const lastAppliedAiDiagnosisLogId = ref('');
const latestAiDiagnosisDraft = ref(null);
const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowX = ref('');
const previousOverflowY = ref('');
const previousHtmlOverflow = ref('');
const previousHtmlOverflowX = ref('');
const previousHtmlOverflowY = ref('');
const previousBodyOverflow = ref('');
const previousBodyOverflowX = ref('');
const previousBodyOverflowY = ref('');
const isAnyModalOpen = computed(() => (
    isServiceOrderModalOpen.value
    || isCompleteOrderModalOpen.value
    || isEstimateModalOpen.value
    || isDetailOrderModalOpen.value
));
const createEmptyCompletionSparePartPayload = () => ({
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
});
const normalizeCompletionSparePartPayload = (payload) => {
    const rows = Array.isArray(payload?.data)
        ? payload.data
            .map((row) => ({
                id: String(row?.id || '').trim(),
                workshop_id: String(row?.workshop_id || '').trim(),
                name: String(row?.name || '').trim(),
                category: String(row?.category || '').trim(),
                unit: String(row?.unit || '').trim(),
                stock: Number(row?.stock || 0),
                selling_price: Number(row?.selling_price || 0),
            }))
            .filter((row) => row.id !== '' && row.name !== '')
        : [];
    const categories = Array.isArray(payload?.categories)
        ? payload.categories
            .map((category) => String(category || '').trim())
            .filter((category, index, items) => category !== '' && items.indexOf(category) === index)
        : [];

    return {
        mode: String(payload?.mode || 'cursor'),
        data: rows,
        per_page: Number(payload?.per_page) || 20,
        total: Number(payload?.total) || 0,
        from: Number(payload?.from) || 0,
        to: Number(payload?.to) || 0,
        current_cursor: payload?.current_cursor ? String(payload.current_cursor) : null,
        next_cursor: payload?.next_cursor ? String(payload.next_cursor) : null,
        prev_cursor: payload?.prev_cursor ? String(payload.prev_cursor) : null,
        has_more_pages: Boolean(payload?.has_more_pages),
        search: String(payload?.search || ''),
        category: String(payload?.category || ''),
        categories,
    };
};
const mergeCompletionSparePartRows = (currentRows, nextRows) => {
    const mergedById = new Map();

    currentRows.forEach((row) => {
        mergedById.set(String(row?.id || ''), row);
    });

    nextRows.forEach((row) => {
        mergedById.set(String(row?.id || ''), row);
    });

    return Array.from(mergedById.values()).filter((row) => String(row?.id || '').trim() !== '');
};
const completionSparePartOptionsState = ref(createEmptyCompletionSparePartPayload());
const completionSparePartLoading = ref(false);
const completionSparePartFetchMode = ref('replace');
const completionSparePartFetchSearch = ref('');
const completionSparePartFetchCategory = ref('');

const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const ordersPath = computed(() => `${baseOwnerPath.value}/orders`);
const salesReportPath = computed(() => `${baseOwnerPath.value}/reports/sales`);
const orderStatusPath = (orderId) => `${baseOwnerPath.value}/orders/${orderId}/status`;
const orderEstimatePath = (orderId) => `${baseOwnerPath.value}/orders/${orderId}/estimates`;
const orderEstimateAiDraftPath = (orderId) => `${baseOwnerPath.value}/orders/${orderId}/estimates/ai-draft`;
const orderDiagnosisAiDraftPath = (orderId) => `${baseOwnerPath.value}/orders/${orderId}/diagnosis/ai-draft`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const flashEstimateApprovalLink = computed(() => String(page.props?.flash?.estimate_approval_link || '').trim());
const flashEstimateCode = computed(() => String(page.props?.flash?.estimate_code || '').trim());
const flashAiEstimateDraft = computed(() => {
    const payload = page.props?.flash?.ai_estimate_draft;
    return payload && typeof payload === 'object' ? payload : null;
});
const flashAiDiagnosisDraft = computed(() => {
    const payload = page.props?.flash?.ai_diagnosis_draft;
    return payload && typeof payload === 'object' ? payload : null;
});
const pageErrors = computed(() => page.props?.errors || {});
const orderError = computed(() => String(
    serviceOrderForm.errors?.create_service_order
    || completeOrderForm.errors?.update_order_status
    || estimateForm.errors?.estimate
    || aiEstimateForm.errors?.estimate_ai
    || aiDiagnosisForm.errors?.diagnosis_ai
    || pageErrors.value?.create_service_order
    || pageErrors.value?.update_order_status
    || pageErrors.value?.estimate
    || pageErrors.value?.estimate_ai
    || pageErrors.value?.diagnosis_ai
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageOrders = computed(() => permissionNames.value.includes('service_orders.manage'));
const canUseAiFeature = computed(() => Boolean(props.package?.plan?.has_ai_feature));
const workshopSwitcher = computed(() => {
    const payload = page.props?.ownerWorkshopSwitcher;
    return payload && typeof payload === 'object' ? payload : null;
});
const activeWorkshopId = computed(() => String(workshopSwitcher.value?.active_workshop_id || '').trim());
const isGlobalWorkshopFilter = computed(() => activeWorkshopId.value === '__all__');
const workshopOptions = computed(() => (
    Array.isArray(workshopSwitcher.value?.workshops)
        ? workshopSwitcher.value.workshops
            .map((workshop) => ({
                value: String(workshop?.id || '').trim(),
                label: String(workshop?.name || '').trim(),
                subtitle: String(workshop?.code || '').trim(),
                is_all: Boolean(workshop?.is_all),
            }))
            .filter((workshop) => workshop.value !== '' && workshop.label !== '' && !workshop.is_all)
        : []
));

const resolveDefaultWorkshopId = () => {
    if (activeWorkshopId.value !== '' && activeWorkshopId.value !== '__all__') {
        return activeWorkshopId.value;
    }

    return String(workshopOptions.value[0]?.value || '').trim();
};

const selectedWorkshopId = computed(() => String(serviceOrderForm.workshop_id || '').trim());
const scopedCustomerOptions = computed(() => (
    Array.isArray(props.customerOptions)
        ? props.customerOptions.filter((customer) => {
            if (selectedWorkshopId.value === '') {
                return true;
            }

            return String(customer?.workshop_id || '').trim() === selectedWorkshopId.value;
        })
        : []
));

const selectedCustomerId = computed(() => String(serviceOrderForm.customer_id || '').trim());
const filteredVehicleOptions = computed(() => (
    Array.isArray(props.customerVehicleOptions)
        ? props.customerVehicleOptions.filter((vehicle) => {
            const matchesCustomer = String(vehicle?.customer_id || '') === selectedCustomerId.value;
            if (!matchesCustomer) {
                return false;
            }

            if (selectedWorkshopId.value === '') {
                return true;
            }

            return String(vehicle?.workshop_id || '').trim() === selectedWorkshopId.value;
        })
        : []
));

watch(
    () => props.orderFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'service_date'),
            sort_dir: String(filters?.sort_dir || 'desc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    () => props.completionSparePartOptions,
    (payload) => {
        const normalizedPayload = normalizeCompletionSparePartPayload(payload);
        const isAppendRequest = completionSparePartFetchMode.value === 'append';
        const isSameSearch = completionSparePartFetchSearch.value === normalizedPayload.search;
        const isSameCategory = completionSparePartFetchCategory.value === normalizedPayload.category;

        if (isAppendRequest && isSameSearch && isSameCategory) {
            completionSparePartOptionsState.value = {
                ...normalizedPayload,
                data: mergeCompletionSparePartRows(
                    completionSparePartOptionsState.value.data,
                    normalizedPayload.data,
                ),
            };
        } else {
            completionSparePartOptionsState.value = normalizedPayload;
        }

        completionSparePartLoading.value = false;
        completionSparePartFetchMode.value = 'replace';
        completionSparePartFetchSearch.value = normalizedPayload.search;
        completionSparePartFetchCategory.value = normalizedPayload.category;
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    selectedWorkshopId,
    () => {
        serviceOrderForm.customer_id = null;
        serviceOrderForm.vehicle_id = null;
        serviceOrderForm.vehicle_master_id = null;
    },
);

watch(
    selectedCustomerId,
    () => {
        serviceOrderForm.vehicle_id = null;
        serviceOrderForm.vehicle_master_id = null;
    },
);

watch(
    flashAiEstimateDraft,
    (draftPayload) => {
        applyAiEstimateDraftToForm(draftPayload);
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    flashAiDiagnosisDraft,
    (draftPayload) => {
        applyAiDiagnosisDraft(draftPayload);
    },
    {
        immediate: true,
        deep: true,
    },
);

const formatDateForBackend = (value) => {
    const parsedDate = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    const year = parsedDate.getFullYear();
    const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
    const day = String(parsedDate.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const resetServiceOrderForm = () => {
    serviceOrderForm.clearErrors();
    serviceOrderForm.workshop_id = resolveDefaultWorkshopId();
    serviceOrderForm.customer_id = null;
    serviceOrderForm.customer_name = '';
    serviceOrderForm.customer_phone = '';
    serviceOrderForm.customer_email = '';
    serviceOrderForm.customer_address = '';
    serviceOrderForm.vehicle_id = null;
    serviceOrderForm.vehicle_master_id = null;
    serviceOrderForm.vehicle_type = 'motor';
    serviceOrderForm.vehicle_brand = '';
    serviceOrderForm.vehicle_model = '';
    serviceOrderForm.vehicle_variant = '';
    serviceOrderForm.vehicle_plate_number = '';
    serviceOrderForm.vehicle_year = '';
    serviceOrderForm.vehicle_notes = '';
    serviceOrderForm.service_date = new Date();
    serviceOrderForm.vehicle_condition = '';
    serviceOrderForm.estimated_days = '';
    serviceOrderForm.complaint = '';
    serviceOrderForm.odometer = '';
};

const resetCompleteOrderForm = () => {
    completeOrderForm.clearErrors();
    completeOrderForm.status = 'done';
    completeOrderForm.allow_no_spareparts = false;
    completeOrderForm.service_fee = null;
    completeOrderForm.mechanic_user_ids = [''];
    completeOrderForm.spareparts = [];
    completeOrderForm.completion_notes = '';
};

const resetEstimateForm = () => {
    estimateForm.clearErrors();
    aiEstimateForm.clearErrors();
    aiDiagnosisForm.clearErrors();
    aiEstimateForm.context_note = '';
    aiDiagnosisForm.context_note = '';
    aiDiagnosisForm.symptoms_text = '';
    latestAiDiagnosisDraft.value = null;
    estimateForm.approval_expires_at = null;
    estimateForm.internal_note = '';
    estimateForm.submit_for_approval = false;
    estimateForm.items = [{
        item_type: 'service',
        label: '',
        description: '',
        unit_label: '',
        qty: 1,
        unit_price: null,
        spare_part_id: '',
    }];
};

const resolveOrderLatestEstimate = (order) => {
    if (!order || typeof order !== 'object') {
        return null;
    }

    const latestEstimate = order.latest_estimate;
    if (!latestEstimate || typeof latestEstimate !== 'object') {
        return null;
    }

    return latestEstimate;
};

const isApprovedEstimateStatus = (status) => String(status || '').trim().toLowerCase() === 'approved';

const hydrateEstimateFormFromOrder = (order) => {
    const latestEstimate = resolveOrderLatestEstimate(order);
    const latestEstimateItems = Array.isArray(latestEstimate?.items) ? latestEstimate.items : [];

    if (latestEstimateItems.length < 1) {
        return;
    }

    estimateForm.approval_expires_at = latestEstimate?.valid_until || null;
    estimateForm.internal_note = String(latestEstimate?.internal_note || '').trim();
    estimateForm.items = latestEstimateItems.map((item) => {
        const itemType = String(item?.item_type || 'service').toLowerCase() === 'sparepart' ? 'sparepart' : 'service';

        return {
            item_type: itemType,
            label: String(item?.label || '').trim(),
            description: String(item?.description || '').trim(),
            unit_label: itemType === 'sparepart' ? String(item?.unit_label || '').trim() : '',
            qty: itemType === 'sparepart'
                ? (Number(item?.qty || 1) > 0 ? Number(item.qty) : 1)
                : 1,
            unit_price: Number(item?.unit_price || 0) > 0 ? Number(item.unit_price) : null,
            spare_part_id: String(item?.spare_part_id || '').trim(),
        };
    });
};

function applyAiEstimateDraftToForm(draft) {
    if (!draft || typeof draft !== 'object') {
        return;
    }

    const draftOrderId = String(draft?.order_id || '').trim();
    const selectedOrderId = String(selectedEstimateOrder.value?.id || '').trim();

    if (draftOrderId === '' || selectedOrderId === '' || draftOrderId !== selectedOrderId) {
        return;
    }

    const logId = String(draft?.log_id || '').trim();
    if (logId !== '' && logId === lastAppliedAiEstimateLogId.value) {
        return;
    }

    const normalizedItems = Array.isArray(draft?.items)
        ? draft.items
            .map((item) => {
                const itemType = String(item?.item_type || 'service').trim().toLowerCase() === 'sparepart'
                    ? 'sparepart'
                    : 'service';

                return {
                    item_type: itemType,
                    label: String(item?.label || '').trim(),
                    description: String(item?.description || '').trim(),
                    unit_label: itemType === 'sparepart' ? String(item?.unit_label || '').trim() : '',
                    qty: itemType === 'sparepart'
                        ? (Number(item?.qty || 1) > 0 ? Number(item.qty) : 1)
                        : 1,
                    unit_price: Number(item?.unit_price || 0) > 0 ? Number(item.unit_price) : null,
                    spare_part_id: itemType === 'sparepart' ? String(item?.spare_part_id || '').trim() : '',
                };
            })
            .filter((item) => item.label !== '')
        : [];

    if (normalizedItems.length < 1) {
        return;
    }

    estimateForm.items = normalizedItems;

    const adviceRows = [];
    const confidenceLevel = Number(draft?.confidence_level || 0);
    if (confidenceLevel > 0) {
        adviceRows.push(`AI confidence: ${confidenceLevel}%`);
    }

    if (Array.isArray(draft?.warnings) && draft.warnings.length > 0) {
        adviceRows.push(`Peringatan AI: ${draft.warnings.map((warning) => String(warning || '').trim()).filter((warning) => warning !== '').join('; ')}`);
    }

    if (String(draft?.disclaimer || '').trim() !== '') {
        adviceRows.push(String(draft.disclaimer).trim());
    }

    if (adviceRows.length > 0) {
        const existingNote = String(estimateForm.internal_note || '').trim();
        const generatedNote = adviceRows.join(' | ');
        estimateForm.internal_note = existingNote !== ''
            ? `${existingNote}\n${generatedNote}`
            : generatedNote;
    }

    if (logId !== '') {
        lastAppliedAiEstimateLogId.value = logId;
    }
}

const normalizeDiagnosisCauseSeverity = (value) => {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'high' || normalized === 'medium' || normalized === 'low') {
        return normalized;
    }

    return 'medium';
};

const normalizeDiagnosisStringList = (rows = [], limit = 6) => (
    Array.isArray(rows)
        ? rows
            .map((row) => String(row || '').trim())
            .filter((row, index, items) => row !== '' && items.indexOf(row) === index)
            .slice(0, Math.max(1, Number(limit) || 1))
        : []
);

function applyAiDiagnosisDraft(draft) {
    if (!draft || typeof draft !== 'object') {
        return;
    }

    const draftOrderId = String(draft?.order_id || '').trim();
    const selectedOrderId = String(selectedEstimateOrder.value?.id || '').trim();

    if (draftOrderId === '' || selectedOrderId === '' || draftOrderId !== selectedOrderId) {
        return;
    }

    const logId = String(draft?.log_id || '').trim();
    if (logId !== '' && logId === lastAppliedAiDiagnosisLogId.value) {
        return;
    }

    const normalizedPossibleCauses = Array.isArray(draft?.possible_causes)
        ? draft.possible_causes
            .map((cause) => ({
                label: String(cause?.label || '').trim(),
                confidence: Math.max(0, Math.min(100, Number(cause?.confidence || 0))),
                severity: normalizeDiagnosisCauseSeverity(cause?.severity),
                reason: String(cause?.reason || '').trim(),
                recommended_checks: normalizeDiagnosisStringList(cause?.recommended_checks, 5),
                recommended_actions: normalizeDiagnosisStringList(cause?.recommended_actions, 5),
            }))
            .filter((cause) => cause.label !== '')
        : [];

    const normalizedSummary = String(draft?.summary || '').trim();
    if (normalizedSummary === '' && normalizedPossibleCauses.length < 1) {
        return;
    }

    latestAiDiagnosisDraft.value = {
        order_id: draftOrderId,
        log_id: logId,
        confidence_level: Math.max(0, Math.min(100, Number(draft?.confidence_level || 0))),
        summary: normalizedSummary,
        possible_causes: normalizedPossibleCauses,
        warnings: normalizeDiagnosisStringList(draft?.warnings, 8),
        customer_advice: normalizeDiagnosisStringList(draft?.customer_advice, 8),
        disclaimer: String(draft?.disclaimer || '').trim(),
        symptoms: normalizeDiagnosisStringList(draft?.symptoms, 10),
    };

    if (logId !== '') {
        lastAppliedAiDiagnosisLogId.value = logId;
    }
}

const hydrateAiDiagnosisDraftFromOrder = (order) => {
    const latestDiagnosis = order && typeof order === 'object'
        ? (order.latest_diagnosis || null)
        : null;

    if (!latestDiagnosis || typeof latestDiagnosis !== 'object') {
        latestAiDiagnosisDraft.value = null;
        return;
    }

    latestAiDiagnosisDraft.value = {
        order_id: String(order?.id || '').trim(),
        log_id: String(latestDiagnosis?.log_id || '').trim(),
        confidence_level: Math.max(0, Math.min(100, Number(latestDiagnosis?.confidence_level || 0))),
        summary: String(latestDiagnosis?.summary || '').trim(),
        possible_causes: Array.isArray(latestDiagnosis?.possible_causes)
            ? latestDiagnosis.possible_causes
                .map((cause) => ({
                    label: String(cause?.label || '').trim(),
                    confidence: Math.max(0, Math.min(100, Number(cause?.confidence || 0))),
                    severity: normalizeDiagnosisCauseSeverity(cause?.severity),
                    reason: String(cause?.reason || '').trim(),
                    recommended_checks: normalizeDiagnosisStringList(cause?.recommended_checks, 5),
                    recommended_actions: normalizeDiagnosisStringList(cause?.recommended_actions, 5),
                }))
                .filter((cause) => cause.label !== '')
            : [],
        warnings: normalizeDiagnosisStringList(latestDiagnosis?.warnings, 8),
        customer_advice: normalizeDiagnosisStringList(latestDiagnosis?.customer_advice, 8),
        disclaimer: String(latestDiagnosis?.disclaimer || '').trim(),
        symptoms: normalizeDiagnosisStringList(latestDiagnosis?.symptoms, 10),
        generated_at: String(latestDiagnosis?.generated_at || '').trim(),
    };

    const savedLogId = String(latestDiagnosis?.log_id || '').trim();
    if (savedLogId !== '') {
        lastAppliedAiDiagnosisLogId.value = savedLogId;
    }

    if (String(aiDiagnosisForm.symptoms_text || '').trim() === '' && latestAiDiagnosisDraft.value.symptoms.length > 0) {
        aiDiagnosisForm.symptoms_text = latestAiDiagnosisDraft.value.symptoms.join('\n');
    }
};

const buildCompletionSparepartsFromEstimate = (estimateItems = []) => {
    if (!Array.isArray(estimateItems)) {
        return [];
    }

    const groupedBySparePart = new Map();

    estimateItems.forEach((item) => {
        const itemType = String(item?.item_type || '').trim().toLowerCase();
        if (itemType !== 'sparepart') {
            return;
        }

        const sparePartId = String(item?.spare_part_id || '').trim();
        if (sparePartId === '') {
            return;
        }

        const qty = Number(item?.qty || 0);
        const normalizedQty = Number.isFinite(qty) && qty > 0 ? Math.max(1, Math.trunc(qty)) : 1;
        const existing = groupedBySparePart.get(sparePartId) || {
            spare_part_id: sparePartId,
            qty: 0,
            warehouse_id: '',
            notes: '',
        };

        existing.qty += normalizedQty;
        groupedBySparePart.set(sparePartId, existing);
    });

    return Array.from(groupedBySparePart.values()).map((row) => ({
        ...row,
        qty: String(Math.max(1, Number(row.qty || 1))),
    }));
};

const hydrateCompleteOrderFormFromApprovedEstimate = (order) => {
    const latestEstimate = resolveOrderLatestEstimate(order);
    if (!latestEstimate || !isApprovedEstimateStatus(latestEstimate.status)) {
        return;
    }

    const normalizedServiceFee = Number(latestEstimate?.subtotal_service ?? 0);
    completeOrderForm.service_fee = Number.isFinite(normalizedServiceFee) && normalizedServiceFee >= 0
        ? Math.trunc(normalizedServiceFee)
        : 0;

    const estimateSpareparts = buildCompletionSparepartsFromEstimate(latestEstimate?.items);
    completeOrderForm.spareparts = estimateSpareparts;
    completeOrderForm.allow_no_spareparts = estimateSpareparts.length < 1;
};

const resolveDisplayText = (value) => {
    const normalized = String(value || '').trim();
    return normalized !== '' ? normalized : '-';
};

const resolveSparePartCost = (order) => {
    const serviceFee = Math.max(0, Number(order?.service_fee || 0));
    const totalAmount = Math.max(0, Number(order?.total_amount || 0));

    if (totalAmount <= 0) {
        return 0;
    }

    if (totalAmount >= serviceFee) {
        return Math.max(totalAmount - serviceFee, 0);
    }

    return totalAmount;
};

const resolveServiceTotal = (order) => {
    const serviceFee = Math.max(0, Number(order?.service_fee || 0));
    return serviceFee + resolveSparePartCost(order);
};

const resolveMechanicNames = (order) => (
    Array.isArray(order?.mechanic_names)
        ? order.mechanic_names
            .map((mechanicName) => String(mechanicName || '').trim())
            .filter((mechanicName) => mechanicName !== '')
        : []
);

const resolveSparePartRows = (order) => (
    Array.isArray(order?.spareparts)
        ? order.spareparts
            .map((sparePart) => ({
                id: String(sparePart?.id || '').trim(),
                name: String(sparePart?.name || '').trim(),
                qty: Math.max(0, Number(sparePart?.qty || 0)),
                unit: String(sparePart?.unit || '').trim(),
                unit_price: Math.max(0, Number(sparePart?.unit_price || 0)),
                subtotal: Math.max(0, Number(sparePart?.subtotal || 0)),
            }))
            .filter((sparePart) => sparePart.name !== '' && sparePart.qty > 0)
        : []
);

const resolveInvoiceStatusClass = (status) => {
    const normalizedStatus = String(status || '').trim().toLowerCase();
    if (normalizedStatus === 'paid') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalizedStatus === 'partial') {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
};

const openDetailOrderModal = (order) => {
    if (!canManageOrders.value) {
        return;
    }

    const orderId = String(order?.id || '').trim();
    if (orderId === '') {
        return;
    }

    if (String(order?.status || '').trim().toLowerCase() !== 'done') {
        return;
    }

    selectedDetailOrder.value = order;
    isDetailOrderModalOpen.value = true;
};

const printOrderNote = (order) => {
    if (!canManageOrders.value || typeof window === 'undefined' || typeof URLSearchParams === 'undefined') {
        return;
    }

    const orderId = String(order?.id || '').trim();
    if (orderId === '') {
        return;
    }

    if (String(order?.status || '').trim().toLowerCase() !== 'done') {
        return;
    }

    const orderCode = String(order?.code || '').trim();
    const searchParams = new URLSearchParams({
        sales_report_search: orderCode !== '' ? orderCode : orderId,
        sales_report_per_page: '10',
        auto_print_note: '1',
        auto_print_order_id: orderId,
        auto_print_return_to: `${window.location.pathname}${window.location.search}`,
    });

    window.location.assign(`${salesReportPath.value}?${searchParams.toString()}`);
};

const closeDetailOrderModal = () => {
    isDetailOrderModalOpen.value = false;
    selectedDetailOrder.value = null;
};

const openCreateServiceOrderModal = () => {
    if (!canManageOrders.value) {
        return;
    }

    resetServiceOrderForm();
    isServiceOrderModalOpen.value = true;
};

const closeServiceOrderModal = () => {
    isServiceOrderModalOpen.value = false;
    resetServiceOrderForm();
};

const openCompleteOrderModal = (order) => {
    if (!canManageOrders.value) {
        return;
    }

    const orderId = String(order?.id || '').trim();
    if (orderId === '') {
        return;
    }

    selectedCompleteOrder.value = order;
    resetCompleteOrderForm();
    hydrateCompleteOrderFormFromApprovedEstimate(order);
    isCompleteOrderModalOpen.value = true;
    completionSparePartOptionsState.value = createEmptyCompletionSparePartPayload();
    completionSparePartFetchMode.value = 'replace';
    completionSparePartFetchSearch.value = '';
    completionSparePartFetchCategory.value = '';
    requestCompletionSparePartOptions({
        search: '',
        category: '',
        cursor: '',
        append: false,
    });
};

const openEstimateOrderModal = (order) => {
    if (!canManageOrders.value) {
        return;
    }

    const orderId = String(order?.id || '').trim();
    if (orderId === '') {
        return;
    }

    const latestEstimateStatus = resolveOrderLatestEstimate(order)?.status;
    if (isApprovedEstimateStatus(latestEstimateStatus)) {
        window.alert('Estimasi sudah disetujui pelanggan dan tidak bisa diubah lagi.');
        return;
    }

    selectedEstimateOrder.value = order;
    lastAppliedAiEstimateLogId.value = '';
    lastAppliedAiDiagnosisLogId.value = '';
    resetEstimateForm();
    hydrateEstimateFormFromOrder(order);
    hydrateAiDiagnosisDraftFromOrder(order);
    requestCompletionSparePartOptions({
        search: completionSparePartOptionsState.value.search,
        category: completionSparePartOptionsState.value.category,
        cursor: '',
        append: false,
    });
    isEstimateModalOpen.value = true;
};

const closeCompleteOrderModal = () => {
    isCompleteOrderModalOpen.value = false;
    selectedCompleteOrder.value = null;
    resetCompleteOrderForm();
};

const closeEstimateOrderModal = () => {
    isEstimateModalOpen.value = false;
    selectedEstimateOrder.value = null;
    lastAppliedAiEstimateLogId.value = '';
    lastAppliedAiDiagnosisLogId.value = '';
    resetEstimateForm();
};

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerServiceOrders(ordersPath.value, nextFilters, {
        onStart: () => {
            tableLoading.value = true;
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
};

const requestCompletionSparePartOptions = ({
    search = '',
    category = completionSparePartOptionsState.value.category,
    cursor = '',
    append = false,
} = {}) => {
    const normalizedSearch = String(search || '').trim();
    const normalizedCategory = String(category || '').trim();
    const normalizedCursor = String(cursor || '').trim();

    completionSparePartFetchMode.value = append ? 'append' : 'replace';
    completionSparePartFetchSearch.value = normalizedSearch;
    completionSparePartFetchCategory.value = normalizedCategory;

    fetchOwnerServiceOrders(
        ordersPath.value,
        {
            ...tableFilters.value,
            completion_sparepart_search: normalizedSearch,
            completion_sparepart_category: normalizedCategory,
            completion_sparepart_cursor: normalizedCursor,
            completion_sparepart_per_page: completionSparePartOptionsState.value.per_page || 20,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['completionSparePartOptions', 'flash', 'errors'],
            onStart: () => {
                completionSparePartLoading.value = true;
            },
            onFinish: () => {
                completionSparePartLoading.value = false;
            },
        },
    );
};

const handleCompletionSparePartSearch = (search) => {
    const normalizedSearch = typeof search === 'object' && search !== null
        ? String(search.search || '').trim()
        : String(search || '').trim();
    const normalizedCategory = typeof search === 'object' && search !== null
        ? String(search.category || '').trim()
        : String(completionSparePartOptionsState.value.category || '').trim();

    requestCompletionSparePartOptions({
        search: normalizedSearch,
        category: normalizedCategory,
        cursor: '',
        append: false,
    });
};

const handleCompletionSparePartCategory = (payload) => {
    const normalizedCategory = typeof payload === 'object' && payload !== null
        ? String(payload.category || '').trim()
        : String(payload || '').trim();
    const normalizedSearch = typeof payload === 'object' && payload !== null
        ? String(payload.search || '').trim()
        : String(completionSparePartOptionsState.value.search || '').trim();

    requestCompletionSparePartOptions({
        search: normalizedSearch,
        category: normalizedCategory,
        cursor: '',
        append: false,
    });
};

const handleCompletionSparePartLoadMore = (nextCursor) => {
    if (completionSparePartLoading.value) {
        return;
    }

    const normalizedCursor = String(nextCursor || '').trim();
    if (normalizedCursor === '') {
        return;
    }

    requestCompletionSparePartOptions({
        search: completionSparePartOptionsState.value.search,
        category: completionSparePartOptionsState.value.category,
        cursor: normalizedCursor,
        append: true,
    });
};

const handleSearch = (search) => {
    requestTable({
        search,
        cursor: null,
    });
};

const handleSort = ({ key, direction }) => {
    requestTable({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handlePerPage = (perPage) => {
    requestTable({
        per_page: perPage,
        cursor: null,
    });
};

const handlePage = (payload) => {
    if (payload && typeof payload === 'object' && payload.type === 'cursor') {
        requestTable({
            cursor: String(payload.cursor || ''),
        });
    }
};

const submitServiceOrderForm = () => {
    const payload = (data) => {
        const customerId = String(data.customer_id || '').trim();
        const vehicleId = String(data.vehicle_id || '').trim();

        return {
            ...data,
            customer_id: customerId !== '' ? customerId : null,
            workshop_id: String(data.workshop_id || '').trim(),
            customer_name: customerId === '' ? String(data.customer_name || '').trim() : '',
            customer_phone: customerId === '' ? String(data.customer_phone || '').trim() : '',
            customer_email: customerId === '' ? String(data.customer_email || '').trim().toLowerCase() : '',
            customer_address: customerId === '' ? String(data.customer_address || '').trim() : '',
            vehicle_id: vehicleId !== '' ? vehicleId : null,
            vehicle_master_id: vehicleId === '' ? String(data.vehicle_master_id || '').trim() : null,
            vehicle_type: vehicleId === '' ? String(data.vehicle_type || '').trim().toLowerCase() : '',
            vehicle_brand: vehicleId === '' ? String(data.vehicle_brand || '').trim() : '',
            vehicle_model: vehicleId === '' ? String(data.vehicle_model || '').trim() : '',
            vehicle_variant: vehicleId === '' ? String(data.vehicle_variant || '').trim() : '',
            vehicle_plate_number: vehicleId === '' ? String(data.vehicle_plate_number || '').trim() : '',
            vehicle_year: vehicleId === '' ? String(data.vehicle_year || '').trim() : '',
            vehicle_notes: vehicleId === '' ? String(data.vehicle_notes || '').trim() : '',
            service_date: formatDateForBackend(data.service_date),
            vehicle_condition: String(data.vehicle_condition || '').trim(),
            estimated_days: String(data.estimated_days || '').trim(),
            complaint: String(data.complaint || '').trim(),
            odometer: String(data.odometer || '').trim(),
        };
    };

    storeOwnerServiceOrder(
        serviceOrderForm.transform(payload),
        ordersPath.value,
        {
            onSuccess: closeServiceOrderModal,
        },
    );
};

const generateEstimateAiDraft = () => {
    if (!canUseAiFeature.value) {
        aiEstimateForm.setError('estimate_ai', 'Fitur AI belum tersedia pada paket aktif tenant.');
        return;
    }

    const orderId = String(selectedEstimateOrder.value?.id || '').trim();
    if (orderId === '') {
        return;
    }

    aiEstimateForm.clearErrors();

    generateOwnerServiceOrderEstimateAiDraft(
        aiEstimateForm.transform((data) => ({
            context_note: String(data.context_note || '').trim(),
        })),
        orderEstimateAiDraftPath(orderId),
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                aiEstimateForm.transform((data) => data);
            },
        },
    );
};

const parseSymptomRows = (rawText) => String(rawText || '')
    .split(/\r?\n|,|;/g)
    .map((row) => String(row || '').trim())
    .filter((row, index, rows) => row !== '' && rows.indexOf(row) === index)
    .slice(0, 10);

const generateDiagnosisAiDraft = () => {
    if (!canUseAiFeature.value) {
        aiDiagnosisForm.setError('diagnosis_ai', 'Fitur AI belum tersedia pada paket aktif tenant.');
        return;
    }

    const orderId = String(selectedEstimateOrder.value?.id || '').trim();
    if (orderId === '') {
        return;
    }

    aiDiagnosisForm.clearErrors();

    generateOwnerServiceOrderDiagnosisAiDraft(
        aiDiagnosisForm.transform((data) => ({
            context_note: String(data.context_note || '').trim(),
            symptoms: parseSymptomRows(data.symptoms_text),
        })),
        orderDiagnosisAiDraftPath(orderId),
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                aiDiagnosisForm.transform((data) => data);
            },
        },
    );
};

const submitEstimateForm = ({ submit_for_approval: submitForApproval = false } = {}) => {
    const orderId = String(selectedEstimateOrder.value?.id || '').trim();
    if (orderId === '') {
        return;
    }

    const payload = (data) => ({
        approval_expires_at: data.approval_expires_at ? formatDateForBackend(data.approval_expires_at) : null,
        internal_note: String(data.internal_note || '').trim(),
        submit_for_approval: Boolean(submitForApproval),
        items: Array.isArray(data.items)
            ? data.items.map((row) => {
                const itemType = String(row?.item_type || 'service').trim().toLowerCase();

                return {
                    item_type: itemType,
                    label: String(row?.label || '').trim(),
                    description: String(row?.description || '').trim(),
                    unit_label: itemType === 'sparepart' ? String(row?.unit_label || '').trim() : '',
                    qty: itemType === 'sparepart'
                        ? (Number(row?.qty || 0) > 0 ? Number(row.qty) : 1)
                        : 1,
                    unit_price: Number(row?.unit_price || 0) > 0 ? Number(row.unit_price) : 0,
                    spare_part_id: String(row?.spare_part_id || '').trim() || null,
                };
            })
            : [],
    });

    storeOwnerServiceOrderEstimate(
        estimateForm.transform(payload),
        orderEstimatePath(orderId),
        {
            onSuccess: () => {
                closeEstimateOrderModal();
            },
        },
    );
};

const copyApprovalLink = async () => {
    const link = flashEstimateApprovalLink.value;
    if (link === '') {
        return;
    }

    try {
        await navigator.clipboard.writeText(link);
        window.alert('Link approval berhasil disalin.');
    } catch (error) {
        window.prompt('Salin link approval berikut:', link);
    }
};

const submitCompleteOrderForm = () => {
    const orderId = String(selectedCompleteOrder.value?.id || '').trim();
    if (orderId === '') {
        return;
    }

    completeOrderForm.clearErrors();

    const payload = (data) => {
        const normalizedServiceFee = String(data.service_fee ?? '').replace(/[^\d]/g, '');
        const normalizedSpareparts = Boolean(data.allow_no_spareparts)
            ? []
            : collectSparepartsForSubmission(data.spareparts);

        return {
            status: 'done',
            allow_no_spareparts: Boolean(data.allow_no_spareparts),
            service_fee: normalizedServiceFee === '' ? null : Number(normalizedServiceFee),
            mechanic_user_ids: Array.isArray(data.mechanic_user_ids)
                ? data.mechanic_user_ids
                    .map((mechanicId) => String(mechanicId || '').trim())
                    .filter((mechanicId) => mechanicId !== '')
                : [],
            spareparts: normalizedSpareparts,
            completion_notes: String(data.completion_notes || '').trim(),
        };
    };

    completeOrderForm
        .transform(payload)
        .patch(orderStatusPath(orderId), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                statusProcessingOrderId.value = orderId;
            },
            onFinish: () => {
                statusProcessingOrderId.value = '';
                completeOrderForm.transform((data) => data);
            },
            onSuccess: () => {
                closeCompleteOrderModal();
            },
        });
};

const collectSparepartsForSubmission = (rows) => {
    if (!Array.isArray(rows)) {
        return [];
    }

    const groupedBySparePart = new Map();

    rows.forEach((row) => {
        const sparePartId = String(row?.spare_part_id || '').trim();
        if (sparePartId === '') {
            return;
        }

        const qty = Number(String(row?.qty ?? '').replace(/[^\d]/g, '')) || 0;
        if (qty < 1) {
            return;
        }

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

    return Array.from(groupedBySparePart.values());
};

const updateOrderStatus = (order, status, confirmationMessage = '') => {
    if (!canManageOrders.value) {
        return;
    }

    const orderId = String(order?.id || '').trim();
    if (orderId === '') {
        return;
    }

    const normalizedStatus = String(status || '').trim().toLowerCase();
    if (!['in_progress', 'done', 'cancelled'].includes(normalizedStatus)) {
        return;
    }

    if (confirmationMessage !== '' && !window.confirm(confirmationMessage)) {
        return;
    }

    updateOwnerServiceOrderStatus(
        orderStatusPath(orderId),
        normalizedStatus,
        {
            onStart: () => {
                statusProcessingOrderId.value = orderId;
            },
            onError: (errors) => {
                statusProcessingOrderId.value = '';
                const message = String(
                    errors?.update_order_status
                    || errors?.status
                    || 'Status servis gagal diperbarui.',
                );
                window.alert(message);
                console.error('updateOrderStatus error', {
                    orderId,
                    status: normalizedStatus,
                    errors,
                });
            },
            onException: (exception) => {
                statusProcessingOrderId.value = '';
                console.error('updateOrderStatus exception', {
                    orderId,
                    status: normalizedStatus,
                    exception,
                });
            },
            onFinish: () => {
                statusProcessingOrderId.value = '';
            },
        },
    );
};

const startServiceOrder = (order) => {
    updateOrderStatus(order, 'in_progress');
};

const completeServiceOrder = (order) => {
    openCompleteOrderModal(order);
};

const cancelServiceOrder = (order) => {
    updateOrderStatus(
        order,
        'cancelled',
        'Batalkan servis ini? Status batal tidak dapat dilanjutkan lagi.',
    );
};

const resolvePageScrollContainer = () => {
    if (!pageContentRef.value || typeof pageContentRef.value.closest !== 'function') {
        if (typeof document === 'undefined') {
            return null;
        }

        const fallbackContainer = document.querySelector('.dashboard-scroll');
        if (fallbackContainer instanceof HTMLElement) {
            return fallbackContainer;
        }

        return document.documentElement instanceof HTMLElement ? document.documentElement : null;
    }

    const container = pageContentRef.value.closest('.dashboard-scroll');
    if (container instanceof HTMLElement) {
        return container;
    }

    if (typeof document === 'undefined') {
        return null;
    }

    const fallbackContainer = document.querySelector('.dashboard-scroll');
    if (fallbackContainer instanceof HTMLElement) {
        return fallbackContainer;
    }

    return document.documentElement instanceof HTMLElement ? document.documentElement : null;
};

const setPageScrollLock = (isLocked) => {
    const container = lockedScrollContainer.value ?? resolvePageScrollContainer();

    if (isLocked) {
        if (container instanceof HTMLElement) {
            lockedScrollContainer.value = container;
            previousOverflow.value = container.style.overflow;
            previousOverflowX.value = container.style.overflowX;
            previousOverflowY.value = container.style.overflowY;
            container.style.overflow = 'hidden';
            container.style.overflowX = 'hidden';
            container.style.overflowY = 'hidden';
        }

        if (typeof document !== 'undefined') {
            const htmlElement = document.documentElement;
            const bodyElement = document.body;

            previousHtmlOverflow.value = htmlElement.style.overflow;
            previousHtmlOverflowX.value = htmlElement.style.overflowX;
            previousHtmlOverflowY.value = htmlElement.style.overflowY;
            previousBodyOverflow.value = bodyElement.style.overflow;
            previousBodyOverflowX.value = bodyElement.style.overflowX;
            previousBodyOverflowY.value = bodyElement.style.overflowY;

            htmlElement.style.overflow = 'hidden';
            htmlElement.style.overflowX = 'hidden';
            htmlElement.style.overflowY = 'hidden';
            bodyElement.style.overflow = 'hidden';
            bodyElement.style.overflowX = 'hidden';
            bodyElement.style.overflowY = 'hidden';
        }
        return;
    }

    if (container instanceof HTMLElement) {
        container.style.overflow = previousOverflow.value;
        container.style.overflowX = previousOverflowX.value;
        container.style.overflowY = previousOverflowY.value;
    }

    previousOverflow.value = '';
    previousOverflowX.value = '';
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;

    if (typeof document !== 'undefined') {
        const htmlElement = document.documentElement;
        const bodyElement = document.body;

        htmlElement.style.overflow = previousHtmlOverflow.value;
        htmlElement.style.overflowX = previousHtmlOverflowX.value;
        htmlElement.style.overflowY = previousHtmlOverflowY.value;
        bodyElement.style.overflow = previousBodyOverflow.value;
        bodyElement.style.overflowX = previousBodyOverflowX.value;
        bodyElement.style.overflowY = previousBodyOverflowY.value;

        previousHtmlOverflow.value = '';
        previousHtmlOverflowX.value = '';
        previousHtmlOverflowY.value = '';
        previousBodyOverflow.value = '';
        previousBodyOverflowX.value = '';
        previousBodyOverflowY.value = '';
    }
};

const blurActiveElement = () => {
    if (typeof document === 'undefined') {
        return;
    }

    const activeElement = document.activeElement;
    if (activeElement instanceof HTMLElement) {
        activeElement.blur();
    }
};

watch(
    isAnyModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (!isAnyModalOpen.value) {
        return;
    }

    event.preventDefault();
    blurActiveElement();

    if (isDetailOrderModalOpen.value) {
        closeDetailOrderModal();
        return;
    }

    if (isEstimateModalOpen.value) {
        closeEstimateOrderModal();
        return;
    }

    if (isCompleteOrderModalOpen.value) {
        closeCompleteOrderModal();
        return;
    }

    if (isServiceOrderModalOpen.value) {
        closeServiceOrderModal();
    }
};

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscapeKey);
    setPageScrollLock(false);
});

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Servis Owner" />

    <AppDashboardLayout
        title="Servis"
        subtitle="Input transaksi servis dan simpan histori pelanggan serta kendaraan"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <section
                v-if="flashEstimateApprovalLink"
                class="rounded-xl border border-sky-200 bg-sky-50/80 p-4 dark:border-sky-400/20 dark:bg-sky-500/10"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                    Link Approval Siap Dikirim
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ flashEstimateCode ? `Estimasi ${flashEstimateCode}` : 'Estimasi terbaru' }}
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <input
                        :value="flashEstimateApprovalLink"
                        readonly
                        class="h-10 min-w-[16rem] flex-1 rounded-xl border border-sky-200 bg-white px-3 text-xs text-slate-700 outline-none dark:border-sky-500/30 dark:bg-slate-900 dark:text-slate-200"
                    >
                    <button
                        type="button"
                        class="inline-flex h-10 items-center rounded-lg border border-sky-300 bg-sky-100 px-3 text-sm font-semibold text-sky-700 transition hover:bg-sky-200 dark:border-sky-400/30 dark:bg-sky-500/20 dark:text-sky-300 dark:hover:bg-sky-500/30"
                        @click="copyApprovalLink"
                    >
                        Salin Link
                    </button>
                </div>
            </section>

            <OwnerServiceOrderTableCard
                :orders="orders"
                :filters="tableFilters"
                :order-summary="orderSummary"
                :flash-status="flashStatus"
                :error-message="orderError"
                :table-loading="tableLoading"
                :can-manage="canManageOrders"
                :status-processing-order-id="statusProcessingOrderId"
                @create="openCreateServiceOrderModal"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
                @detail-order="openDetailOrderModal"
                @print-order="printOrderNote"
                @estimate-order="openEstimateOrderModal"
                @start-order="startServiceOrder"
                @complete-order="completeServiceOrder"
                @cancel-order="cancelServiceOrder"
            />
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isServiceOrderModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeServiceOrderModal"
                />

                <div class="relative z-20 min-w-0 w-full max-w-6xl">
                    <OwnerServiceOrderFormCard
                        :form="serviceOrderForm"
                        :customer-options="scopedCustomerOptions"
                        :filtered-vehicle-options="filteredVehicleOptions"
                        :vehicle-master-options="vehicleMasterOptions"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        :errors="pageErrors"
                        @close="closeServiceOrderModal"
                        @submit="submitServiceOrderForm"
                    />
                </div>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isEstimateModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-[58] flex items-start justify-center overflow-x-hidden overflow-y-auto overscroll-contain bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeEstimateOrderModal"
                />

                <div class="relative z-20 min-w-0 w-full max-w-5xl">
                    <OwnerServiceOrderEstimateFormCard
                        :form="estimateForm"
                        :diagnosis-form="aiDiagnosisForm"
                        :order="selectedEstimateOrder"
                        :spare-part-options="completionSparePartOptionsState"
                        :spare-part-loading="completionSparePartLoading"
                        :ai-generating="aiEstimateForm.processing"
                        :diagnosis-generating="aiDiagnosisForm.processing"
                        :diagnosis-draft="latestAiDiagnosisDraft"
                        :can-use-ai-feature="canUseAiFeature"
                        :errors="pageErrors"
                        @close="closeEstimateOrderModal"
                        @search-spareparts="handleCompletionSparePartSearch"
                        @filter-spareparts-category="handleCompletionSparePartCategory"
                        @load-more-spareparts="handleCompletionSparePartLoadMore"
                        @generate-diagnosis="generateDiagnosisAiDraft"
                        @generate-ai="generateEstimateAiDraft"
                        @submit="submitEstimateForm"
                    />
                </div>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isCompleteOrderModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-[60] flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeCompleteOrderModal"
                />

                <div class="relative z-20 min-w-0 w-full max-w-4xl">
                    <OwnerServiceOrderCompleteFormCard
                        :form="completeOrderForm"
                        :order="selectedCompleteOrder"
                        :mechanic-options="mechanicOptions"
                        :spare-part-options="completionSparePartOptionsState"
                        :spare-part-loading="completionSparePartLoading"
                        :errors="pageErrors"
                        @close="closeCompleteOrderModal"
                        @search-spareparts="handleCompletionSparePartSearch"
                        @filter-spareparts-category="handleCompletionSparePartCategory"
                        @load-more-spareparts="handleCompletionSparePartLoadMore"
                        @submit="submitCompleteOrderForm"
                    />
                </div>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isDetailOrderModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-[62] flex items-start justify-center overflow-x-hidden overflow-y-auto overscroll-contain bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeDetailOrderModal"
                />

                <section class="relative z-20 min-w-0 w-full max-w-5xl flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:max-h-[calc(100dvh-3rem)] dark:border-slate-700 dark:bg-slate-900">
                    <header class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Detail Servis Selesai</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ringkasan hasil pengerjaan, sparepart terpakai, dan status invoice.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            @click="closeDetailOrderModal"
                        >
                            Tutup
                        </button>
                    </header>

                    <div class="modal-scroll-green min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode Servis</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedDetailOrder?.code || '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Servis</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ selectedDetailOrder?.service_date ? formatDateIndonesia(selectedDetailOrder.service_date) : '-' }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Selesai Pada</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ selectedDetailOrder?.completed_at ? formatDateTimeIndonesia(selectedDetailOrder.completed_at) : '-' }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</p>
                                <span class="mt-1 inline-flex h-6 items-center rounded-full bg-emerald-100 px-2 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                    Selesai
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pelanggan</p>
                                <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedDetailOrder?.customer_name || '-' }}</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ selectedDetailOrder?.customer_phone || '-' }}</p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    Bengkel: {{ selectedDetailOrder?.workshop_name || '-' }}<span v-if="selectedDetailOrder?.workshop_code"> ({{ selectedDetailOrder.workshop_code }})</span>
                                </p>
                            </section>

                            <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kendaraan</p>
                                <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedDetailOrder?.vehicle_name || '-' }}</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ selectedDetailOrder?.vehicle_plate_number || '-' }}</p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    Odometer: {{ selectedDetailOrder?.odometer ?? '-' }} km
                                </p>
                            </section>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <section class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Servis</p>
                                <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-300">
                                    {{ formatRupiah(resolveServiceTotal(selectedDetailOrder)) }}
                                </p>
                            </section>
                            <section class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-500/30 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Subtotal Jasa</p>
                                <p class="mt-1 text-base font-bold text-sky-700 dark:text-sky-300">
                                    {{ formatRupiah(selectedDetailOrder?.service_fee || 0) }}
                                </p>
                            </section>
                            <section class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Subtotal Sparepart</p>
                                <p class="mt-1 text-base font-bold text-amber-700 dark:text-amber-300">
                                    {{ formatRupiah(resolveSparePartCost(selectedDetailOrder)) }}
                                </p>
                            </section>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Mekanik</p>
                                <div v-if="resolveMechanicNames(selectedDetailOrder).length > 0" class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-for="mechanicName in resolveMechanicNames(selectedDetailOrder)"
                                        :key="mechanicName"
                                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ mechanicName }}
                                    </span>
                                </div>
                                <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada mekanik tercatat.</p>
                            </section>

                            <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Invoice</p>
                                <template v-if="selectedDetailOrder?.invoice">
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            {{ selectedDetailOrder.invoice.code || '-' }}
                                        </p>
                                        <span
                                            class="inline-flex h-6 items-center rounded-full px-2 text-xs font-semibold"
                                            :class="resolveInvoiceStatusClass(selectedDetailOrder.invoice.status)"
                                        >
                                            {{ selectedDetailOrder.invoice.status_label || '-' }}
                                        </span>
                                    </div>
                                    <div class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                        <p>Total: {{ formatRupiah(selectedDetailOrder.invoice.total_amount || 0) }}</p>
                                        <p>Dibayar: {{ formatRupiah(selectedDetailOrder.invoice.paid_amount || 0) }}</p>
                                        <p>Sisa: {{ formatRupiah(selectedDetailOrder.invoice.remaining_amount || 0) }}</p>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        Jatuh tempo: {{ selectedDetailOrder.invoice.due_date ? formatDateIndonesia(selectedDetailOrder.invoice.due_date) : '-' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Pembayaran terakhir: {{ selectedDetailOrder.invoice.last_paid_at ? formatDateTimeIndonesia(selectedDetailOrder.invoice.last_paid_at) : '-' }}
                                    </p>
                                </template>
                                <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada invoice.</p>
                            </section>
                        </div>

                        <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sparepart Terpakai</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ resolveSparePartRows(selectedDetailOrder).length }} item
                                </p>
                            </div>
                            <div v-if="resolveSparePartRows(selectedDetailOrder).length > 0" class="mt-3 space-y-2">
                                <div
                                    v-for="sparePart in resolveSparePartRows(selectedDetailOrder)"
                                    :key="sparePart.id || `${sparePart.name}-${sparePart.qty}`"
                                    class="grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/60 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]"
                                >
                                    <div>
                                        <p class="font-semibold text-slate-700 dark:text-slate-100">{{ sparePart.name }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ sparePart.unit || '-' }}</p>
                                    </div>
                                    <p class="text-slate-600 dark:text-slate-300">Qty {{ sparePart.qty }}</p>
                                    <p class="text-slate-600 dark:text-slate-300">{{ formatRupiah(sparePart.unit_price) }}</p>
                                    <p class="font-semibold text-slate-700 dark:text-slate-100">{{ formatRupiah(sparePart.subtotal) }}</p>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm text-slate-500 dark:text-slate-400">Tidak ada sparepart tercatat.</p>
                        </section>

                        <section class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Keluhan Pelanggan</p>
                                <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">{{ resolveDisplayText(selectedDetailOrder?.complaint) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan Penyelesaian</p>
                                <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">{{ resolveDisplayText(selectedDetailOrder?.completion_notes) }}</p>
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kondisi Kendaraan Saat Masuk</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">{{ resolveDisplayText(selectedDetailOrder?.vehicle_condition) }}</p>
                        </section>
                    </div>
                </section>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>
