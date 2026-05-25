import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { exportPlatformTenants, fetchPlatformTenants } from '../Services/platformTenantService';

const parseDateInput = (value) => {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsedDate = new Date(value);
        return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
    }

    return null;
};

const formatDateForBackend = (value) => {
    const date = parseDateInput(value);
    if (!date) {
        return null;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const normalizeNameToCodeBase = (value) => {
    const normalized = String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return normalized;
};

const normalizeTenantSubdomain = (value) => {
    return String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);
};

const buildDeterministicSuffix = (value) => {
    const source = String(value || '').trim();
    if (source === '') {
        return '0001';
    }

    let hash = 0;
    for (let index = 0; index < source.length; index += 1) {
        hash = ((hash << 5) - hash) + source.charCodeAt(index);
        hash |= 0;
    }

    const normalizedHash = Math.abs(hash);
    return normalizedHash.toString(36).toUpperCase().padStart(4, '0').slice(-4);
};

const buildRandomSuffix = (length = 4) => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';

    for (let index = 0; index < length; index += 1) {
        const randomIndex = Math.floor(Math.random() * chars.length);
        result += chars[randomIndex];
    }

    return result;
};

export const usePlatformTenantPage = (props) => {
    const page = usePage();
    const logoutForm = useForm({});
    const tenantForm = useForm({
        name: '',
        code: '',
        subdomain: '',
        phone: '',
        address: '',
        owner_name: '',
        owner_email: '',
        owner_password: '',
        plan_price_id: null,
        plan_started_at: null,
        is_active: true,
    });
    const statusForm = useForm({
        is_active: false,
    });

    const editingTenantId = ref(null);
    const togglingTenantId = ref(null);
    const tableLoading = ref(false);
    const isTenantModalOpen = ref(false);
    const isEditMode = computed(() => String(editingTenantId.value || '').trim() !== '');
    const subdomainEditedManually = ref(false);
    const syncingAutoSubdomain = ref(false);

    const dashboardPath = '/platform/dashboard';
    const tenantsPath = '/platform/tenants';
    const permissionsPath = '/platform/settings/permissions';
    const menusPath = '/platform/settings/menus';
    const plansPath = '/platform/settings/plans';
    const applicationPath = '/platform/settings/application';
    const paymentsPath = '/platform/settings/payments';
    const vehicleMastersPath = '/platform/settings/vehicle-masters';
    const aiAgentPath = '/platform/settings/ai-agent';
    const tenantStorePath = '/platform/tenants';
    const tenantExportPath = '/platform/tenants/export';
    const tenantUpdatePath = (tenantId) => `/platform/tenants/${tenantId}`;
    const tenantStatusPath = (tenantId) => `/platform/tenants/${tenantId}/status`;

    const currentPath = computed(() => String(page.url || '').split('?')[0] || '');

    const activeSettingsKey = computed(() => {
        if (currentPath.value === permissionsPath) return 'permissions';
        if (currentPath.value === menusPath) return 'menus';
        if (currentPath.value === plansPath) return 'plans';
        if (currentPath.value === applicationPath) return 'application';
        if (currentPath.value === paymentsPath) return 'payments';
        if (currentPath.value === vehicleMastersPath) return 'vehicle-masters';
        if (currentPath.value === aiAgentPath) return 'ai-agent';

        return '';
    });

    const isSettingsActive = computed(() => ['permissions', 'menus', 'application', 'payments', 'vehicle-masters', 'ai-agent'].includes(activeSettingsKey.value));

    const menuItems = computed(() => [
        { key: 'dashboard', label: 'Dasbor', icon: 'dashboard', href: dashboardPath, active: currentPath.value === dashboardPath },
        {
            key: 'tenants',
            label: 'Tenant',
            icon: 'users',
            href: tenantsPath,
            active: currentPath.value === tenantsPath,
        },
        { key: 'plans', label: 'Plan', icon: 'billing', href: plansPath, active: activeSettingsKey.value === 'plans' },
        {
            key: 'settings',
            label: 'Pengaturan',
            icon: 'settings',
            active: isSettingsActive.value,
            children: [
                { key: 'permissions', label: 'Permission', href: permissionsPath, active: activeSettingsKey.value === 'permissions' },
                { key: 'menus', label: 'Management Menu', href: menusPath, active: activeSettingsKey.value === 'menus' },
                { key: 'application', label: 'Aplikasi', href: applicationPath, active: activeSettingsKey.value === 'application' },
                { key: 'payments', label: 'Pembayaran', href: paymentsPath, active: activeSettingsKey.value === 'payments' },
                { key: 'vehicle-masters', label: 'Master Kendaraan', href: vehicleMastersPath, active: activeSettingsKey.value === 'vehicle-masters' },
                { key: 'ai-agent', label: 'AI Agent', href: aiAgentPath, active: activeSettingsKey.value === 'ai-agent' },
            ],
        },
    ]);

    const tableFilters = ref({
        search: '',
        sort_by: 'created_at',
        sort_dir: 'desc',
        per_page: 10,
        cursor: null,
    });

    watch(
        () => props.tenantFilters,
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

    const existingTenantCodeSet = computed(() => {
        if (!Array.isArray(props.tenants?.data)) {
            return new Set();
        }

        return new Set(
            props.tenants.data
                .map((tenant) => String(tenant?.code || '').trim().toUpperCase())
                .filter((code) => code !== ''),
        );
    });

    const generateUniqueTenantCode = (name) => {
        const normalizedBase = normalizeNameToCodeBase(name);
        const base = (normalizedBase === '' ? 'TENANT' : normalizedBase).slice(0, 15);

        const deterministicSuffix = buildDeterministicSuffix(name);
        let candidate = `${base.slice(0, Math.max(1, 20 - (deterministicSuffix.length + 1)))}-${deterministicSuffix}`;

        if (!existingTenantCodeSet.value.has(candidate)) {
            return candidate;
        }

        for (let attempt = 0; attempt < 25; attempt += 1) {
            const randomSuffix = buildRandomSuffix(4);
            candidate = `${base.slice(0, Math.max(1, 20 - 5))}-${randomSuffix}`;
            if (!existingTenantCodeSet.value.has(candidate)) {
                return candidate;
            }
        }

        return `${base.slice(0, Math.max(1, 20 - 5))}-${Date.now().toString().slice(-4)}`;
    };

    const applyAutoSubdomain = (value) => {
        syncingAutoSubdomain.value = true;
        tenantForm.subdomain = value;
        syncingAutoSubdomain.value = false;
    };

    watch(
        () => tenantForm.name,
        (name) => {
            if (isEditMode.value) {
                return;
            }

            const normalizedName = String(name || '').trim();
            if (normalizedName === '') {
                tenantForm.code = '';
                if (!subdomainEditedManually.value) {
                    applyAutoSubdomain('');
                }
                return;
            }

            tenantForm.code = generateUniqueTenantCode(normalizedName);
            if (!subdomainEditedManually.value || String(tenantForm.subdomain || '').trim() === '') {
                applyAutoSubdomain(normalizeTenantSubdomain(normalizedName));
            }
        },
    );

    watch(
        () => tenantForm.subdomain,
        (subdomain) => {
            if (syncingAutoSubdomain.value || isEditMode.value) {
                return;
            }

            const normalizedSubdomain = normalizeTenantSubdomain(subdomain);
            if (normalizedSubdomain !== String(subdomain || '')) {
                syncingAutoSubdomain.value = true;
                tenantForm.subdomain = normalizedSubdomain;
                syncingAutoSubdomain.value = false;
            }

            subdomainEditedManually.value = normalizedSubdomain !== '';
        },
        { flush: 'sync' },
    );

    const tenantRootDomain = computed(() => {
        const rootDomainFromProps = String(props.tenantRootDomain || '').trim().toLowerCase();
        if (rootDomainFromProps !== '') {
            return rootDomainFromProps;
        }

        if (typeof window === 'undefined') {
            return '';
        }

        return String(window.location.hostname || '').trim().toLowerCase();
    });

    const flashStatus = computed(() => String(page.props?.flash?.status || ''));
    const pageErrors = computed(() => page.props?.errors || {});
    const tenantError = computed(() => String(
        tenantForm.errors?.create_tenant
        || tenantForm.errors?.update_tenant
        || statusForm.errors?.status_tenant
        || pageErrors.value?.create_tenant
        || pageErrors.value?.update_tenant
        || pageErrors.value?.status_tenant
        || '',
    ));


    const requestTable = (override = {}) => {
        const nextFilters = {
            ...tableFilters.value,
            ...override,
        };

        tableFilters.value = nextFilters;

        fetchPlatformTenants(tenantsPath, nextFilters, {
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

    const handleExport = () => {
        exportPlatformTenants(tenantExportPath, tableFilters.value);
    };

    const handlePage = (payload) => {
        if (payload && typeof payload === 'object' && payload.type === 'cursor') {
            requestTable({
                cursor: String(payload.cursor || ''),
            });
        }
    };

    const normalizePlanPriceId = (planPriceId) => {
        const normalized = Number(planPriceId);
        return Number.isInteger(normalized) && normalized > 0 ? normalized : null;
    };

    const resetTenantForm = () => {
        editingTenantId.value = null;
        subdomainEditedManually.value = false;
        tenantForm.clearErrors();
        tenantForm.name = '';
        tenantForm.code = '';
        tenantForm.subdomain = '';
        tenantForm.phone = '';
        tenantForm.address = '';
        tenantForm.owner_name = '';
        tenantForm.owner_email = '';
        tenantForm.owner_password = '';
        tenantForm.plan_price_id = null;
        tenantForm.plan_started_at = null;
        tenantForm.is_active = true;
    };

    const openCreateTenantModal = () => {
        resetTenantForm();
        isTenantModalOpen.value = true;
    };

    const closeTenantModal = () => {
        isTenantModalOpen.value = false;
        resetTenantForm();
    };

    const startEditTenant = (tenant) => {
        const tenantId = String(tenant?.id || '').trim();
        if (tenantId === '') return;

        editingTenantId.value = tenantId;
        tenantForm.clearErrors();
        tenantForm.name = String(tenant?.name || '');
        tenantForm.code = String(tenant?.code || '');
        syncingAutoSubdomain.value = true;
        tenantForm.subdomain = normalizeTenantSubdomain(String(tenant?.subdomain || tenant?.name || ''));
        syncingAutoSubdomain.value = false;
        subdomainEditedManually.value = true;
        tenantForm.phone = String(tenant?.phone || '');
        tenantForm.address = String(tenant?.address || '');
        tenantForm.owner_name = '';
        tenantForm.owner_email = '';
        tenantForm.owner_password = '';
        tenantForm.plan_price_id = normalizePlanPriceId(tenant?.package?.price?.id);
        tenantForm.plan_started_at = parseDateInput(tenant?.package?.started_at);
        tenantForm.is_active = Boolean(tenant?.is_active);
        isTenantModalOpen.value = true;
    };

    const submitTenantForm = () => {
        const payload = (data) => {
            const normalizedName = String(data.name || '').trim();
            const normalizedCode = String(data.code || '').trim().toUpperCase();
            const normalizedSubdomain = normalizeTenantSubdomain(String(data.subdomain || '').trim());

            return {
                ...data,
                name: normalizedName,
                code: normalizedCode !== '' ? normalizedCode : generateUniqueTenantCode(normalizedName),
                subdomain: normalizedSubdomain !== '' ? normalizedSubdomain : null,
                phone: String(data.phone || '').trim(),
                address: String(data.address || '').trim() || null,
                owner_name: String(data.owner_name || '').trim(),
                owner_email: String(data.owner_email || '').trim().toLowerCase(),
                owner_password: String(data.owner_password || ''),
                plan_price_id: normalizePlanPriceId(data.plan_price_id),
                plan_started_at: formatDateForBackend(data.plan_started_at),
                is_active: Boolean(data.is_active),
            };
        };

        if (isEditMode.value) {
            tenantForm
                .transform(payload)
                .patch(tenantUpdatePath(editingTenantId.value), {
                    preserveScroll: true,
                    onSuccess: closeTenantModal,
                });
            return;
        }

        tenantForm
            .transform(payload)
            .post(tenantStorePath, {
                preserveScroll: true,
                onSuccess: closeTenantModal,
            });
    };

    const toggleTenantStatus = (tenant) => {
        const tenantId = String(tenant?.id || '').trim();
        if (tenantId === '' || statusForm.processing) return;

        const nextStatus = !Boolean(tenant?.is_active);
        statusForm.clearErrors();

        statusForm
            .transform(() => ({
                is_active: nextStatus,
            }))
            .patch(tenantStatusPath(tenantId), {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    togglingTenantId.value = tenantId;
                },
                onFinish: () => {
                    togglingTenantId.value = null;
                },
            });
    };

    const logout = () => {
        logoutForm.post('/logout');
    };

    return {
        dashboardPath,
        flashStatus,
        pageErrors,
        tenantError,
        tableFilters,
        tableLoading,
        menuItems,
        tenantForm,
        statusForm,
        isTenantModalOpen,
        isEditMode,
        tenantRootDomain,
        togglingTenantId,
        handleSearch,
        handleSort,
        handlePerPage,
        handleExport,
        handlePage,
        openCreateTenantModal,
        closeTenantModal,
        startEditTenant,
        submitTenantForm,
        toggleTenantStatus,
        logout,
    };
};
