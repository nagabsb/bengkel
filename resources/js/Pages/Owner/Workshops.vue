<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import { formatDateIndonesia, formatDateTimeIndonesia } from '../../Utils/indonesiaDate';
import { formatRupiah } from '../../Utils/formatCurrency';
import OwnerWorkshopTableCard from './Workshops/Components/OwnerWorkshopTableCard.vue';
import OwnerWorkshopFormCard from './Workshops/Components/OwnerWorkshopFormCard.vue';
import {
    destroyOwnerWorkshop,
    fetchOwnerWorkshops,
    storeOwnerWorkshop,
    switchOwnerWorkshopPlan,
    updateOwnerWorkshop,
} from './Services/ownerWorkshopService';

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
    planOptions: {
        type: Array,
        default: () => [],
    },
    paymentOptions: {
        type: Object,
        default: () => ({
            available_methods: [],
            midtrans: {
                enabled: false,
                environment: 'sandbox',
                client_key: '',
            },
            manual: {
                enabled: false,
                providers: [],
            },
        }),
    },
    pendingMidtransPayment: {
        type: Object,
        default: null,
    },
    workshops: {
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
    workshopFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    workshopSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            limit: null,
            remaining: null,
        }),
    },
});

const WORKSHOP_CODE_MAX_LENGTH = 20;
const MIDTRANS_AUTO_POLL_BOOTSTRAP_INTERVAL_MS = 8000;
const MIDTRANS_AUTO_POLL_ACTIVE_INTERVAL_MS = 15000;
const MIDTRANS_AUTO_POLL_IDLE_INTERVAL_MS = 30000;
const MIDTRANS_AUTO_POLL_BACKGROUND_INTERVAL_MS = 60000;
const MIDTRANS_AUTO_POLL_BOOTSTRAP_WINDOW_MS = 180000;
const MIDTRANS_AUTO_POLL_ACTIVE_WINDOW_MS = 900000;
const MIDTRANS_AUTO_POLL_EXPIRY_GRACE_MS = 120000;

const page = usePage();
const logoutForm = useForm({});
const workshopForm = useForm({
    name: '',
    code: '',
    phone: '',
    address: '',
    is_active: true,
});
const continuePendingPaymentForm = useForm({});
const confirmMidtransPaymentForm = useForm({
    order_id: '',
    silent: true,
});
const planSwitchForm = useForm({
    plan_price_id: null,
    payment_method: '',
    manual_provider_id: 0,
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const editingWorkshopId = ref(null);
const deletingWorkshopId = ref(null);
const isWorkshopModalOpen = ref(false);
const isWorkshopLimitModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingWorkshopId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const workshopsPath = computed(() => `${baseOwnerPath.value}/workshops`);
const workshopStorePath = computed(() => `${baseOwnerPath.value}/workshops`);
const workshopSwitchPlanPath = computed(() => `${baseOwnerPath.value}/workshops/switch-plan`);
const workshopContinuePendingPaymentPath = computed(() => `${baseOwnerPath.value}/workshops/continue-pending-payment`);
const workshopConfirmMidtransPaymentPath = computed(() => `${baseOwnerPath.value}/workshops/confirm-midtrans-payment`);
const workshopUpdatePath = (workshopId) => `${baseOwnerPath.value}/workshops/${workshopId}`;
const workshopDeletePath = (workshopId) => `${baseOwnerPath.value}/workshops/${workshopId}`;
let midtransAutoPollTimeoutId = null;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const activeWorkshopId = computed(() => String(
    page.props?.ownerWorkshopSwitcher?.active_workshop_id
    || props.tenantId
    || '',
).trim());
const pageErrors = computed(() => page.props?.errors || {});
const workshopError = computed(() => String(
    workshopForm.errors?.create_workshop
    || workshopForm.errors?.update_workshop
    || workshopForm.errors?.delete_workshop
    || pageErrors.value?.create_workshop
    || pageErrors.value?.update_workshop
    || pageErrors.value?.delete_workshop
    || '',
));
const planSwitchErrorMessage = computed(() => String(
    planSwitchForm.errors?.plan_price_id
    || planSwitchForm.errors?.payment_method
    || planSwitchForm.errors?.manual_provider_id
    || pageErrors.value?.plan_price_id
    || pageErrors.value?.payment_method
    || pageErrors.value?.manual_provider_id
    || pageErrors.value?.switch_plan
    || '',
));
const workshopLimitInfo = computed(() => {
    const total = Number(props.workshopSummary?.total) || 0;
    const normalizedLimit = Number(props.workshopSummary?.limit);
    const hasLimit = Number.isFinite(normalizedLimit) && normalizedLimit > 0;

    if (!hasLimit) {
        return {
            hasLimit: false,
            reached: false,
            total,
            limit: null,
            remaining: null,
        };
    }

    const limit = Math.max(1, Math.floor(normalizedLimit));
    const normalizedRemaining = Number(props.workshopSummary?.remaining);
    const remaining = Number.isFinite(normalizedRemaining)
        ? Math.max(0, Math.floor(normalizedRemaining))
        : Math.max(limit - total, 0);

    return {
        hasLimit: true,
        reached: remaining <= 0 || total >= limit,
        total,
        limit,
        remaining,
    };
});
const isWorkshopLimitReached = computed(() => workshopLimitInfo.value.reached);
const isAnyModalOpen = computed(() => isWorkshopModalOpen.value || isWorkshopLimitModalOpen.value);
const pendingMidtransRedirectUrl = computed(() => String(props.pendingMidtransPayment?.redirect_url || '').trim());
const pendingMidtransOrderId = computed(() => String(props.pendingMidtransPayment?.order_id || '').trim());
const hasPendingMidtransPayment = computed(() => (
    pendingMidtransRedirectUrl.value !== '' && pendingMidtransOrderId.value !== ''
));
const pendingMidtransExpiresAtText = computed(() => formatDateTimeIndonesia(props.pendingMidtransPayment?.expires_at || null));
const pendingMidtransExpiresAtTimestamp = computed(() => {
    const rawValue = String(props.pendingMidtransPayment?.expires_at || '').trim();
    if (rawValue === '') {
        return null;
    }

    const parsedTimestamp = Date.parse(rawValue);
    return Number.isFinite(parsedTimestamp) ? parsedTimestamp : null;
});
const packagePlanName = computed(() => String(props.package?.plan?.name || 'Belum ada paket aktif'));
const packagePriceText = computed(() => {
    const amount = Number(props.package?.price?.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
        return 'Hubungi admin untuk detail harga';
    }

    return `${new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount)} / bulan`;
});
const packageStatusText = computed(() => String(props.package?.status || '-'));
const workshopLimitNotice = computed(() => {
    if (!workshopLimitInfo.value.hasLimit) {
        return 'Paket saat ini tidak memiliki batas jumlah bengkel.';
    }

    return `Anda sudah menggunakan ${workshopLimitInfo.value.total}/${workshopLimitInfo.value.limit} bengkel. Silakan upgrade paket untuk menambah bengkel baru.`;
});
const activePlanPriceId = computed(() => Number(props.package?.price?.id) || 0);
const normalizedPlanOptions = computed(() => (
    Array.isArray(props.planOptions)
        ? props.planOptions
            .map((option) => ({
                id: Number(option?.id) || 0,
                label: String(option?.label || ''),
                duration_months: Number(option?.duration_months) || 1,
                amount: Number(option?.amount) || 0,
                discount_pct: Number(option?.discount_pct) || 0,
                plan: {
                    id: Number(option?.plan?.id) || 0,
                    name: String(option?.plan?.name || '-'),
                    slug: String(option?.plan?.slug || '-'),
                    max_workshops: Number(option?.plan?.max_workshops) || 0,
                    max_users_per_ws: Number(option?.plan?.max_users_per_ws) || 0,
                },
            }))
            .filter((option) => option.id > 0)
        : []
));

const hasPlanOptions = computed(() => normalizedPlanOptions.value.length > 0);
const normalizedPaymentMethods = computed(() => {
    const availableMethods = Array.isArray(props.paymentOptions?.available_methods)
        ? props.paymentOptions.available_methods
        : [];

    return availableMethods
        .map((method) => String(method || '').trim().toLowerCase())
        .filter((method) => method === 'midtrans' || method === 'manual')
        .map((method) => ({
            value: method,
            label: method === 'midtrans' ? 'Otomatis' : 'Manual',
        }));
});
const hasPaymentMethods = computed(() => normalizedPaymentMethods.value.length > 0);
const selectedPaymentMethod = computed(() => String(planSwitchForm.payment_method || '').trim().toLowerCase());
const paymentMethodLabel = computed(() => {
    if (selectedPaymentMethod.value === 'midtrans') {
        return 'Otomatis';
    }
    if (selectedPaymentMethod.value === 'manual') {
        return 'Manual';
    }
    return '-';
});
const manualProviderOptions = computed(() => (
    Array.isArray(props.paymentOptions?.manual?.providers)
        ? props.paymentOptions.manual.providers
            .map((provider) => ({
                id: Number(provider?.id) || 0,
                provider_name: String(provider?.provider_name || ''),
                account_name: String(provider?.account_name || ''),
                account_number: String(provider?.account_number || ''),
                notes: String(provider?.notes || ''),
                is_active: Boolean(provider?.is_active),
            }))
            .filter((provider) => provider.id > 0 && provider.is_active)
        : []
));
const selectedManualProviderId = computed(() => Number(planSwitchForm.manual_provider_id) || 0);
const selectedManualProvider = computed(() => (
    manualProviderOptions.value.find((provider) => provider.id === selectedManualProviderId.value) || null
));
const requiresManualProvider = computed(() => selectedPaymentMethod.value === 'manual');
const hasManualProviders = computed(() => manualProviderOptions.value.length > 0);

const resolveMidtransAutoPollStorageKey = (orderId) => (
    `owner.workshops.midtrans.auto_poll.${props.tenantId}.${String(orderId || '').trim()}`
);

const isMidtransPollingPaused = () => {
    if (typeof document === 'undefined') {
        return false;
    }

    if (document.hidden) {
        return true;
    }

    if (typeof window !== 'undefined' && window.navigator && window.navigator.onLine === false) {
        return true;
    }

    return false;
};

const resolveMidtransAutoPollInterval = (startedAtTimestamp) => {
    if (isMidtransPollingPaused()) {
        return MIDTRANS_AUTO_POLL_BACKGROUND_INTERVAL_MS;
    }

    const elapsedMs = Date.now() - startedAtTimestamp;
    if (elapsedMs <= MIDTRANS_AUTO_POLL_BOOTSTRAP_WINDOW_MS) {
        return MIDTRANS_AUTO_POLL_BOOTSTRAP_INTERVAL_MS;
    }

    if (elapsedMs <= MIDTRANS_AUTO_POLL_ACTIVE_WINDOW_MS) {
        return MIDTRANS_AUTO_POLL_ACTIVE_INTERVAL_MS;
    }

    return MIDTRANS_AUTO_POLL_IDLE_INTERVAL_MS;
};

const clearMidtransAutoPollTimeout = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (midtransAutoPollTimeoutId !== null) {
        window.clearTimeout(midtransAutoPollTimeoutId);
        midtransAutoPollTimeoutId = null;
    }
};

const resetMidtransAutoPollState = (orderId = '') => {
    if (typeof window === 'undefined') {
        return;
    }

    clearMidtransAutoPollTimeout();

    const normalizedOrderId = String(orderId || '').trim();
    if (normalizedOrderId === '') {
        return;
    }

    window.sessionStorage.removeItem(resolveMidtransAutoPollStorageKey(normalizedOrderId));
};

const isMidtransAutoPollDeadlineReached = (orderId) => {
    const normalizedOrderId = String(orderId || '').trim();
    if (normalizedOrderId === '') {
        return true;
    }

    if (pendingMidtransOrderId.value !== normalizedOrderId) {
        return true;
    }

    const expiresAtTimestamp = pendingMidtransExpiresAtTimestamp.value;
    if (!Number.isFinite(expiresAtTimestamp)) {
        return false;
    }

    return Date.now() >= (Number(expiresAtTimestamp) + MIDTRANS_AUTO_POLL_EXPIRY_GRACE_MS);
};

const refreshMidtransPaymentState = () => {
    router.reload({
        preserveScroll: true,
        preserveState: true,
        only: ['package', 'workshopSummary', 'pendingMidtransPayment', 'flash'],
    });
};

const requestMidtransPaymentSync = (orderId, { silent = true, onFinish = null } = {}) => {
    const normalizedOrderId = String(orderId || '').trim();
    if (normalizedOrderId === '' || confirmMidtransPaymentForm.processing) {
        return false;
    }

    confirmMidtransPaymentForm.clearErrors();
    confirmMidtransPaymentForm.order_id = normalizedOrderId;
    confirmMidtransPaymentForm.silent = Boolean(silent);
    confirmMidtransPaymentForm.post(workshopConfirmMidtransPaymentPath.value, {
        preserveScroll: true,
        preserveState: true,
        only: ['package', 'workshopSummary', 'pendingMidtransPayment', 'flash'],
        onFinish: () => {
            confirmMidtransPaymentForm.reset('order_id', 'silent');
            confirmMidtransPaymentForm.silent = true;
            if (typeof onFinish === 'function') {
                onFinish();
            }
        },
    });

    return true;
};

const scheduleMidtransAutoPoll = (orderId) => {
    if (typeof window === 'undefined') {
        return;
    }

    const normalizedOrderId = String(orderId || '').trim();
    if (normalizedOrderId === '' || pendingMidtransOrderId.value !== normalizedOrderId) {
        resetMidtransAutoPollState(normalizedOrderId);
        return;
    }

    if (isMidtransAutoPollDeadlineReached(normalizedOrderId)) {
        resetMidtransAutoPollState(normalizedOrderId);
        refreshMidtransPaymentState();
        return;
    }

    const storageKey = resolveMidtransAutoPollStorageKey(normalizedOrderId);
    const nowTimestamp = Date.now();
    const savedStartedAt = Number(window.sessionStorage.getItem(storageKey));
    const startedAt = Number.isFinite(savedStartedAt) && savedStartedAt > 0
        ? savedStartedAt
        : nowTimestamp;

    if (startedAt === nowTimestamp) {
        window.sessionStorage.setItem(storageKey, String(startedAt));
    }

    clearMidtransAutoPollTimeout();
    const pollInterval = resolveMidtransAutoPollInterval(startedAt);
    midtransAutoPollTimeoutId = window.setTimeout(() => {
        if (pendingMidtransOrderId.value !== normalizedOrderId) {
            resetMidtransAutoPollState(normalizedOrderId);
            return;
        }

        if (isMidtransAutoPollDeadlineReached(normalizedOrderId)) {
            resetMidtransAutoPollState(normalizedOrderId);
            refreshMidtransPaymentState();
            return;
        }

        if (isMidtransPollingPaused()) {
            scheduleMidtransAutoPoll(normalizedOrderId);
            return;
        }

        const requested = requestMidtransPaymentSync(normalizedOrderId, {
            silent: true,
            onFinish: () => {
                scheduleMidtransAutoPoll(normalizedOrderId);
            },
        });

        if (!requested) {
            scheduleMidtransAutoPoll(normalizedOrderId);
        }
    }, pollInterval);
};

const startMidtransAutoPoll = (orderId, { immediate = true } = {}) => {
    if (typeof window === 'undefined') {
        return;
    }

    const normalizedOrderId = String(orderId || '').trim();
    if (normalizedOrderId === '') {
        return;
    }

    const storageKey = resolveMidtransAutoPollStorageKey(normalizedOrderId);
    if (!window.sessionStorage.getItem(storageKey)) {
        window.sessionStorage.setItem(storageKey, String(Date.now()));
    }

    if (!immediate || isMidtransPollingPaused()) {
        scheduleMidtransAutoPoll(normalizedOrderId);
        return;
    }

    const requested = requestMidtransPaymentSync(normalizedOrderId, {
        silent: true,
        onFinish: () => {
            scheduleMidtransAutoPoll(normalizedOrderId);
        },
    });

    if (requested) {
        return;
    }

    scheduleMidtransAutoPoll(normalizedOrderId);
};

const continuePendingMidtransPayment = async () => {
    if (!hasPendingMidtransPayment.value) {
        return;
    }

    await openMidtransSnapModal(
        '',
        pendingMidtransRedirectUrl.value,
        pendingMidtransOrderId.value,
    );
};

const checkPendingMidtransStatus = () => {
    if (!hasPendingMidtransPayment.value) {
        return;
    }

    requestMidtransPaymentSync(pendingMidtransOrderId.value, {
        silent: false,
    });
};

const resolveMidtransSnapScriptUrl = () => {
    const environment = String(props.paymentOptions?.midtrans?.environment || 'sandbox')
        .trim()
        .toLowerCase();

    return environment === 'production'
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
};

const resolveMidtransClientKey = () => String(props.paymentOptions?.midtrans?.client_key || '').trim();

const extractMidtransSnapTokenFromRedirectUrl = (redirectUrl) => {
    const normalizedRedirectUrl = String(redirectUrl || '').trim();
    if (normalizedRedirectUrl === '') {
        return '';
    }

    try {
        const parsedUrl = new URL(normalizedRedirectUrl);
        const segments = parsedUrl.pathname
            .split('/')
            .map((segment) => segment.trim())
            .filter((segment) => segment !== '');

        const tokenCandidate = String(segments[segments.length - 1] || '').trim();
        if (tokenCandidate === '') {
            return '';
        }

        return /^[A-Za-z0-9._-]+$/.test(tokenCandidate) ? tokenCandidate : '';
    } catch {
        return '';
    }
};
const ensureMidtransSnapScript = () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return Promise.reject(new Error('Browser context tidak tersedia.'));
    }

    if (window.snap && typeof window.snap.pay === 'function') {
        return Promise.resolve();
    }

    const clientKey = resolveMidtransClientKey();
    if (clientKey === '') {
        return Promise.reject(new Error('Client key Midtrans belum tersedia.'));
    }

    const scriptUrl = resolveMidtransSnapScriptUrl();
    const existingScript = document.querySelector('script[data-midtrans-snap="true"]');

    if (existingScript instanceof HTMLScriptElement) {
        const existingSrc = existingScript.getAttribute('src') || '';
        const existingClientKey = existingScript.getAttribute('data-client-key') || '';

        if (existingSrc !== scriptUrl || existingClientKey !== clientKey) {
            existingScript.remove();
        } else {
            return new Promise((resolve, reject) => {
                if (window.snap && typeof window.snap.pay === 'function') {
                    resolve();
                    return;
                }

                existingScript.addEventListener('load', () => resolve(), { once: true });
                existingScript.addEventListener('error', () => reject(new Error('Gagal memuat script Snap Midtrans.')), { once: true });
            });
        }
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.async = true;
        script.setAttribute('data-midtrans-snap', 'true');
        script.setAttribute('data-client-key', clientKey);
        script.onload = () => {
            if (window.snap && typeof window.snap.pay === 'function') {
                resolve();
                return;
            }

            reject(new Error('Snap Midtrans tidak tersedia setelah script dimuat.'));
        };
        script.onerror = () => reject(new Error('Gagal memuat script Snap Midtrans.'));
        document.head.appendChild(script);
    });
};

const openMidtransSnapModal = async (snapToken, paymentRedirectUrl = '', paymentOrderId = '') => {
    const normalizedSnapToken = String(snapToken || '').trim()
        || extractMidtransSnapTokenFromRedirectUrl(paymentRedirectUrl);
    const normalizedRedirectUrl = String(paymentRedirectUrl || '').trim();
    const normalizedOrderId = String(paymentOrderId || '').trim();

    if (normalizedSnapToken === '') {
        planSwitchForm.setError('payment_method', normalizedRedirectUrl !== ''
            ? 'Tagihan ditemukan, tetapi token Snap tidak tersedia. Silakan hubungi admin.'
            : 'Token pembayaran Midtrans tidak tersedia.');
        return;
    }

    try {
        await ensureMidtransSnapScript();

        if (!window.snap || typeof window.snap.pay !== 'function') {
            throw new Error('Midtrans Snap belum tersedia.');
        }

        const confirmMidtransPayment = (result = null) => {
            const orderId = String(result?.order_id || '').trim() || normalizedOrderId;
            if (orderId === '') {
                router.reload({
                    preserveScroll: true,
                    preserveState: true,
                });
                return;
            }

            const requested = requestMidtransPaymentSync(orderId, {
                silent: true,
                onFinish: () => {
                    startMidtransAutoPoll(orderId, { immediate: false });
                },
            });

            if (!requested) {
                startMidtransAutoPoll(orderId, { immediate: false });
            }
        };

        window.snap.pay(normalizedSnapToken, {
            onSuccess: (result) => {
                confirmMidtransPayment(result);
            },
            onPending: (result) => {
                confirmMidtransPayment(result);
            },
            onError: () => {
                router.reload({
                    preserveScroll: true,
                    preserveState: true,
                });
            },
            onClose: () => {
                router.reload({
                    preserveScroll: true,
                    preserveState: true,
                });
            },
        });
    } catch {
        router.reload({
            preserveScroll: true,
            preserveState: true,
        });
    }
};

const calculateDiscountedAmount = (option) => {
    const amount = Number(option?.amount) || 0;
    const discountPct = Math.max(0, Math.min(100, Number(option?.discount_pct) || 0));
    return Math.max(0, Math.round(amount - ((amount * discountPct) / 100)));
};

const formatPlanAmount = (amount) => formatRupiah(Number(amount) || 0);

const isCurrentPlanOption = (planPriceId) => Number(planPriceId) === activePlanPriceId.value;

watch(
    () => props.workshopFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'created_at'),
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

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerWorkshops(workshopsPath.value, nextFilters, {
        onStart: () => {
            tableLoading.value = true;
        },
        onFinish: () => {
            tableLoading.value = false;
        },
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

const normalizeWorkshopCode = (code) => String(code || '')
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9-]/g, '')
    .slice(0, WORKSHOP_CODE_MAX_LENGTH);

const buildWorkshopCodeBase = (name) => {
    const words = String(name || '')
        .normalize('NFKD')
        .replace(/[^\w\s-]/g, ' ')
        .toUpperCase()
        .replace(/[_-]+/g, ' ')
        .replace(/[^A-Z0-9\s]/g, ' ')
        .split(/\s+/)
        .map((word) => word.trim())
        .filter((word) => word !== '');

    const abbreviated = words
        .slice(0, 4)
        .map((word, index) => word.slice(0, index === 0 ? 4 : 3))
        .filter((segment) => segment !== '')
        .join('-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');

    const sliced = abbreviated.slice(0, WORKSHOP_CODE_MAX_LENGTH).replace(/^-+|-+$/g, '');
    return sliced || 'WS';
};

const appendWorkshopCodeSuffix = (baseCode, suffix) => {
    const normalizedSuffix = normalizeWorkshopCode(suffix) || 'X';
    const baseLimit = Math.max(1, WORKSHOP_CODE_MAX_LENGTH - normalizedSuffix.length - 1);
    const normalizedBase = normalizeWorkshopCode(baseCode).slice(0, baseLimit) || 'WS';

    return `${normalizedBase}-${normalizedSuffix}`.slice(0, WORKSHOP_CODE_MAX_LENGTH);
};

const collectExistingWorkshopCodes = (excludeWorkshopId = null) => {
    const normalizedExcludeId = excludeWorkshopId ? String(excludeWorkshopId) : '';

    return new Set(
        (Array.isArray(props.workshops?.data) ? props.workshops.data : [])
            .filter((row) => {
                if (normalizedExcludeId === '') {
                    return true;
                }

                return String(row?.id || '') !== normalizedExcludeId;
            })
            .map((row) => normalizeWorkshopCode(row?.code || ''))
            .filter((code) => code !== ''),
    );
};

const generateWorkshopCode = (
    name,
    {
        preferredCode = '',
        excludeWorkshopId = null,
    } = {},
) => {
    const existingCodes = collectExistingWorkshopCodes(excludeWorkshopId);
    const normalizedPreferredCode = normalizeWorkshopCode(preferredCode);

    if (normalizedPreferredCode !== '' && !existingCodes.has(normalizedPreferredCode)) {
        return normalizedPreferredCode;
    }

    const baseCode = buildWorkshopCodeBase(name);
    if (!existingCodes.has(baseCode)) {
        return baseCode;
    }

    for (let sequence = 2; sequence <= 99; sequence += 1) {
        const candidate = appendWorkshopCodeSuffix(baseCode, String(sequence));
        if (!existingCodes.has(candidate)) {
            return candidate;
        }
    }

    return appendWorkshopCodeSuffix(baseCode, Math.random().toString(36).slice(2, 6).toUpperCase());
};

const resetWorkshopForm = () => {
    editingWorkshopId.value = null;
    workshopForm.clearErrors();
    workshopForm.name = '';
    workshopForm.code = generateWorkshopCode('');
    workshopForm.phone = '';
    workshopForm.address = '';
    workshopForm.is_active = true;
};

const openWorkshopLimitModal = () => {
    planSwitchForm.clearErrors();
    planSwitchForm.plan_price_id = null;
    planSwitchForm.payment_method = normalizedPaymentMethods.value[0]?.value || '';
    planSwitchForm.manual_provider_id = manualProviderOptions.value[0]?.id || 0;
    isWorkshopLimitModalOpen.value = true;
    isWorkshopModalOpen.value = false;
};

const openCreateWorkshopModal = async () => {
    if (isWorkshopLimitReached.value) {
        if (pendingMidtransRedirectUrl.value !== '') {
            await openMidtransSnapModal('', pendingMidtransRedirectUrl.value, pendingMidtransOrderId.value);
            return;
        }

        if (continuePendingPaymentForm.processing) {
            return;
        }

        continuePendingPaymentForm.post(workshopContinuePendingPaymentPath.value, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (pageResponse) => {
                const paymentRedirectUrl = String(pageResponse?.props?.flash?.payment_redirect_url || '').trim();
                const paymentOpenMode = String(pageResponse?.props?.flash?.payment_open_mode || '').trim().toLowerCase();
                const paymentOrderId = String(pageResponse?.props?.flash?.payment_order_id || '').trim();

                if (paymentOpenMode === 'redirect' && paymentRedirectUrl !== '') {
                    openMidtransSnapModal('', paymentRedirectUrl, paymentOrderId);
                    return;
                }

                openWorkshopLimitModal();
            },
            onError: () => {
                openWorkshopLimitModal();
            },
        });
        return;
    }

    resetWorkshopForm();
    isWorkshopLimitModalOpen.value = false;
    isWorkshopModalOpen.value = true;
};

const startEditWorkshop = (workshop) => {
    const workshopId = String(workshop?.id || '').trim();
    if (workshopId === '') {
        return;
    }

    editingWorkshopId.value = workshopId;
    workshopForm.clearErrors();
    workshopForm.name = String(workshop?.name || '');
    workshopForm.code = normalizeWorkshopCode(workshop?.code || '');
    workshopForm.phone = String(workshop?.phone || '');
    workshopForm.address = String(workshop?.address || '');
    workshopForm.is_active = Boolean(workshop?.is_active);
    isWorkshopModalOpen.value = true;
};

const closeWorkshopModal = () => {
    isWorkshopModalOpen.value = false;
    resetWorkshopForm();
};

const closeWorkshopLimitModal = () => {
    isWorkshopLimitModalOpen.value = false;
    planSwitchForm.clearErrors();
    planSwitchForm.plan_price_id = null;
    planSwitchForm.payment_method = normalizedPaymentMethods.value[0]?.value || '';
    planSwitchForm.manual_provider_id = manualProviderOptions.value[0]?.id || 0;
};

const handleEscapeKey = (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (isWorkshopLimitModalOpen.value) {
        closeWorkshopLimitModal();
        return;
    }

    if (isWorkshopModalOpen.value) {
        closeWorkshopModal();
    }
};

const switchPlan = (planOption) => {
    const planPriceId = Number(planOption?.id) || 0;
    if (planPriceId <= 0 || isCurrentPlanOption(planPriceId) || planSwitchForm.processing) {
        return;
    }
    if (!hasPaymentMethods.value || selectedPaymentMethod.value === '') {
        planSwitchForm.setError('payment_method', 'Metode pembayaran belum tersedia. Hubungi admin platform.');
        return;
    }
    if (requiresManualProvider.value && (!hasManualProviders.value || selectedManualProviderId.value <= 0)) {
        planSwitchForm.setError('manual_provider_id', 'Pilih provider pembayaran manual terlebih dahulu.');
        return;
    }

    planSwitchForm.clearErrors();
    planSwitchForm.plan_price_id = planPriceId;
    planSwitchForm.payment_method = selectedPaymentMethod.value;
    planSwitchForm.manual_provider_id = requiresManualProvider.value
        ? selectedManualProviderId.value
        : 0;

    switchOwnerWorkshopPlan(planSwitchForm, workshopSwitchPlanPath.value, {
        onSuccess: async (pageResponse) => {
            const paymentSnapToken = String(pageResponse?.props?.flash?.payment_snap_token || '').trim();
            const paymentRedirectUrl = String(pageResponse?.props?.flash?.payment_redirect_url || '').trim();
            const paymentOpenMode = String(pageResponse?.props?.flash?.payment_open_mode || '').trim().toLowerCase();
            const paymentOrderId = String(pageResponse?.props?.flash?.payment_order_id || '').trim();
            const resolvedSnapToken = paymentSnapToken !== ''
                ? paymentSnapToken
                : extractMidtransSnapTokenFromRedirectUrl(paymentRedirectUrl);

            if (paymentOpenMode === 'redirect' && paymentRedirectUrl !== '') {
                closeWorkshopLimitModal();
                await openMidtransSnapModal('', paymentRedirectUrl, paymentOrderId);
                return;
            }

            if (resolvedSnapToken !== '') {
                closeWorkshopLimitModal();
                await openMidtransSnapModal(resolvedSnapToken, paymentRedirectUrl, paymentOrderId);
                return;
            }

            if (paymentRedirectUrl !== '') {
                planSwitchForm.setError('payment_method', 'Tagihan pending ditemukan, tetapi token Snap tidak tersedia. Silakan hubungi admin.');
                return;
            }

            closeWorkshopLimitModal();
        },
    });
};

watch(
    () => workshopForm.name,
    (name) => {
        if (!isWorkshopModalOpen.value || isEditMode.value) {
            return;
        }

        workshopForm.code = generateWorkshopCode(name);
    },
);

watch(
    normalizedPaymentMethods,
    (methods) => {
        const currentMethod = String(planSwitchForm.payment_method || '').trim().toLowerCase();
        const hasCurrentMethod = methods.some((method) => method.value === currentMethod);
        if (hasCurrentMethod) {
            return;
        }

        planSwitchForm.payment_method = methods[0]?.value || '';
    },
    { immediate: true },
);

watch(
    [selectedPaymentMethod, manualProviderOptions],
    ([method, providers]) => {
        if (method !== 'manual') {
            planSwitchForm.manual_provider_id = 0;
            return;
        }

        const currentProviderId = Number(planSwitchForm.manual_provider_id) || 0;
        const hasCurrentProvider = providers.some((provider) => provider.id === currentProviderId);
        if (hasCurrentProvider) {
            return;
        }

        planSwitchForm.manual_provider_id = providers[0]?.id || 0;
    },
    { immediate: true },
);

watch(
    pendingMidtransOrderId,
    (currentOrderId, previousOrderId) => {
        const normalizedCurrentOrderId = String(currentOrderId || '').trim();
        const normalizedPreviousOrderId = String(previousOrderId || '').trim();

        if (normalizedPreviousOrderId !== '' && normalizedPreviousOrderId !== normalizedCurrentOrderId) {
            resetMidtransAutoPollState(normalizedPreviousOrderId);
        }

        if (normalizedCurrentOrderId === '') {
            clearMidtransAutoPollTimeout();
            return;
        }

        startMidtransAutoPoll(normalizedCurrentOrderId, { immediate: true });
    },
    { immediate: true },
);

const triggerPendingMidtransAutoSync = () => {
    if (!hasPendingMidtransPayment.value) {
        return;
    }

    startMidtransAutoPoll(pendingMidtransOrderId.value, { immediate: true });
};

const handleMidtransAutoPollVisibilityChange = () => {
    if (typeof document === 'undefined' || document.hidden) {
        return;
    }

    triggerPendingMidtransAutoSync();
};

const handleMidtransAutoPollWindowFocus = () => {
    triggerPendingMidtransAutoSync();
};

const handleMidtransAutoPollOnline = () => {
    triggerPendingMidtransAutoSync();
};

const submitWorkshopForm = () => {
    const payload = (data) => ({
        ...data,
        name: String(data.name || '').trim(),
        code: normalizeWorkshopCode(data.code),
        phone: String(data.phone || '').trim(),
        address: String(data.address || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerWorkshop(
            workshopForm.transform(payload),
            workshopUpdatePath(editingWorkshopId.value),
            {
                onSuccess: closeWorkshopModal,
            },
        );

        return;
    }

    storeOwnerWorkshop(
        workshopForm.transform(payload),
        workshopStorePath.value,
        {
            onSuccess: closeWorkshopModal,
        },
    );
};

const deleteWorkshop = (workshop) => {
    const workshopId = String(workshop?.id || '').trim();
    if (workshopId === '' || Boolean(workshop?.is_primary)) {
        return;
    }

    if (!window.confirm('Hapus bengkel ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerWorkshop(workshopDeletePath(workshopId), {
        onStart: () => {
            deletingWorkshopId.value = workshopId;
        },
        onFinish: () => {
            deletingWorkshopId.value = null;
        },
    });
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
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (isLocked) {
        lockedScrollContainer.value = container;
        previousOverflow.value = container.style.overflow;
        previousOverflowY.value = container.style.overflowY;
        container.style.overflow = 'hidden';
        container.style.overflowY = 'hidden';
        return;
    }

    container.style.overflow = previousOverflow.value;
    container.style.overflowY = previousOverflowY.value;
    previousOverflow.value = '';
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;
};

watch(
    isAnyModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscapeKey);
    window.removeEventListener('focus', handleMidtransAutoPollWindowFocus);
    window.removeEventListener('online', handleMidtransAutoPollOnline);
    document.removeEventListener('visibilitychange', handleMidtransAutoPollVisibilityChange);
    setPageScrollLock(false);
    clearMidtransAutoPollTimeout();
});

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
    window.addEventListener('focus', handleMidtransAutoPollWindowFocus);
    window.addEventListener('online', handleMidtransAutoPollOnline);
    document.addEventListener('visibilitychange', handleMidtransAutoPollVisibilityChange);
});

const packageBadgeText = computed(() => {
    if (!props.package?.plan) {
        return 'Belum ada paket aktif';
    }

    const planName = String(props.package.plan.name || '-');
    const status = String(props.package.status || '-');

    if (status === 'trial' && props.package.trial_ends_at) {
        return `${planName} - Trial s.d. ${formatDateIndonesia(props.package.trial_ends_at)}`;
    }

    if (props.package.expired_at) {
        return `${planName} - Aktif s.d. ${formatDateIndonesia(props.package.expired_at)}`;
    }

    return `${planName} - ${status}`;
});

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Bengkel Owner" />

    <AppDashboardLayout
        title="Bengkel"
        subtitle="Kelola daftar bengkel untuk tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <div class="rounded-2xl border border-emerald-100 bg-white px-5 py-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Paket Tenant</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ packageBadgeText }}</p>

                <div
                    v-if="hasPendingMidtransPayment"
                    class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 dark:border-amber-400/30 dark:bg-amber-500/10"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Tagihan Midtrans Pending</p>
                        <span class="rounded-full border border-amber-300 bg-white px-2 py-0.5 text-xs font-semibold text-amber-700 dark:border-amber-300/40 dark:bg-amber-500/10 dark:text-amber-200">
                            Menunggu Pembayaran
                        </span>
                    </div>

                    <div class="mt-2 space-y-1 text-xs text-amber-900 dark:text-amber-100">
                        <p>Order ID: <span class="font-semibold">{{ pendingMidtransOrderId }}</span></p>
                        <p>Batas bayar: <span class="font-semibold">{{ pendingMidtransExpiresAtText }}</span></p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-200 active:scale-95 dark:border-amber-300/40 dark:bg-amber-500/20 dark:text-amber-100 dark:hover:bg-amber-500/30"
                            @click="continuePendingMidtransPayment"
                        >
                            Lanjutkan Pembayaran
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            :disabled="confirmMidtransPaymentForm.processing"
                            @click="checkPendingMidtransStatus"
                        >
                            {{ confirmMidtransPaymentForm.processing ? 'Mengecek...' : 'Cek Status' }}
                        </button>
                    </div>
                </div>
            </div>

            <OwnerWorkshopTableCard
                :workshops="workshops"
                :filters="tableFilters"
                :workshop-summary="workshopSummary"
                :active-workshop-id="activeWorkshopId"
                :flash-status="flashStatus"
                :error-message="workshopError"
                :table-loading="tableLoading"
                :form-processing="workshopForm.processing"
                :deleting-workshop-id="deletingWorkshopId"
                @create="openCreateWorkshopModal"
                @edit="startEditWorkshop"
                @delete="deleteWorkshop"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
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
                v-if="isWorkshopModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeWorkshopModal"
                />

                <div class="relative z-20 w-full max-w-xl">
                    <OwnerWorkshopFormCard
                        :is-edit-mode="isEditMode"
                        :form="workshopForm"
                        :errors="pageErrors"
                        @close="closeWorkshopModal"
                        @submit="submitWorkshopForm"
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
                v-if="isWorkshopLimitModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal limit bengkel"
                    @click="closeWorkshopLimitModal"
                />

                <article class="relative z-20 my-auto flex max-h-[calc(100dvh-2rem)] w-full max-w-xl flex-col rounded-2xl border border-amber-100 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-amber-400/20 dark:bg-slate-900">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Limit Bengkel Tercapai
                        </h3>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-amber-300 hover:text-amber-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-amber-400/40 dark:hover:text-amber-300"
                            aria-label="Tutup modal"
                            @click="closeWorkshopLimitModal"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </header>

                    <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 pb-5 pt-4">
                        <div
                            v-if="planSwitchErrorMessage"
                            class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300"
                        >
                            {{ planSwitchErrorMessage }}
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
                            {{ workshopLimitNotice }}
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/70">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Paket Aktif Saat Ini</p>
                                <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/15 dark:text-blue-300">
                                    {{ packageStatusText }}
                                </span>
                            </div>
                            <div class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-slate-500 dark:text-slate-400">Nama paket</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ packagePlanName }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-slate-500 dark:text-slate-400">Harga</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ packagePriceText }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/70">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Metode Pembayaran</p>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    {{ paymentMethodLabel }}
                                </span>
                            </div>

                            <div
                                v-if="hasPaymentMethods"
                                class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                            >
                                <label
                                    v-for="method in normalizedPaymentMethods"
                                    :key="method.value"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    <input
                                        v-model="planSwitchForm.payment_method"
                                        type="radio"
                                        :value="method.value"
                                        class="h-4 w-4 cursor-pointer border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                    >
                                    {{ method.label }}
                                </label>
                            </div>

                            <p
                                v-else
                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                            >
                                Metode pembayaran belum aktif. Hubungi admin platform.
                            </p>

                            <div
                                v-if="selectedPaymentMethod === 'manual' && hasPaymentMethods"
                                class="mt-3 space-y-2"
                            >
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300" for="manual-provider-select">
                                        Pilih Provider Manual
                                    </label>
                                    <select
                                        id="manual-provider-select"
                                        v-model.number="planSwitchForm.manual_provider_id"
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                    >
                                        <option :value="0">Pilih provider</option>
                                        <option
                                            v-for="provider in manualProviderOptions"
                                            :key="provider.id"
                                            :value="provider.id"
                                        >
                                            {{ provider.provider_name }} - {{ provider.account_number }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="selectedManualProvider"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-200"
                                >
                                    <p class="font-semibold">Instruksi Pembayaran Manual</p>
                                    <p class="mt-1">Provider: <span class="font-semibold">{{ selectedManualProvider.provider_name || '-' }}</span></p>
                                    <p>Atas nama: <span class="font-semibold">{{ selectedManualProvider.account_name || '-' }}</span></p>
                                    <p>No rekening / e-wallet: <span class="font-semibold">{{ selectedManualProvider.account_number || '-' }}</span></p>
                                    <p v-if="selectedManualProvider.notes">Catatan: {{ selectedManualProvider.notes }}</p>
                                </div>

                                <p
                                    v-else-if="!hasManualProviders"
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                                >
                                    Provider manual belum tersedia. Hubungi admin platform.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/70">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Pilih Pricing Plan</p>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    {{ normalizedPlanOptions.length }} plan tersedia
                                </span>
                            </div>

                            <div
                                v-if="hasPlanOptions"
                                class="max-h-[45dvh] space-y-3 overflow-y-auto pr-1"
                            >
                                <article
                                    v-for="planOption in normalizedPlanOptions"
                                    :key="planOption.id"
                                    class="rounded-xl border p-3"
                                    :class="isCurrentPlanOption(planOption.id)
                                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-400/30 dark:bg-emerald-500/10'
                                        : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/70'"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ planOption.plan.name }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ planOption.label }}</p>
                                        </div>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="isCurrentPlanOption(planOption.id)
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                                        >
                                            {{ isCurrentPlanOption(planOption.id) ? 'Aktif' : `${planOption.duration_months} bulan` }}
                                        </span>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 dark:text-slate-300 sm:grid-cols-2">
                                        <p>Maks bengkel: <span class="font-semibold">{{ planOption.plan.max_workshops || '-' }}</span></p>
                                        <p>User per bengkel: <span class="font-semibold">{{ planOption.plan.max_users_per_ws || '-' }}</span></p>
                                    </div>

                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                                {{ formatPlanAmount(calculateDiscountedAmount(planOption)) }}
                                            </p>
                                            <p
                                                v-if="Number(planOption.discount_pct) > 0"
                                                class="text-xs text-slate-500 line-through dark:text-slate-400"
                                            >
                                                {{ formatPlanAmount(planOption.amount) }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                                            :class="isCurrentPlanOption(planOption.id)
                                                ? 'cursor-not-allowed border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                : 'cursor-pointer border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 active:scale-95 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300 dark:hover:bg-amber-500/25'"
                                            :disabled="isCurrentPlanOption(planOption.id) || planSwitchForm.processing || !hasPaymentMethods || selectedPaymentMethod === '' || (requiresManualProvider && (!hasManualProviders || selectedManualProviderId <= 0))"
                                            @click="switchPlan(planOption)"
                                        >
                                            {{ isCurrentPlanOption(planOption.id) ? 'Paket Aktif' : (planSwitchForm.processing && planSwitchForm.plan_price_id === planOption.id ? 'Memproses...' : 'Bayar & Upgrade') }}
                                        </button>
                                    </div>
                                </article>
                            </div>

                            <p
                                v-else
                                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                            >
                                Plan aktif belum tersedia saat ini. Hubungi admin platform.
                            </p>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                                @click="closeWorkshopLimitModal"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

