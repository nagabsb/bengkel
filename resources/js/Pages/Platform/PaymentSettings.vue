<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import InputField from '../../Components/UI/InputField.vue';
import AsyncSelect from '../../Components/UI/AsyncSelect.vue';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    paymentSettings: {
        type: Object,
        default: () => ({
            midtrans_enabled: false,
            midtrans_environment: 'sandbox',
            midtrans_merchant_id: '',
            midtrans_server_key: '',
            midtrans_client_key: '',
            midtrans_has_server_key: false,
            midtrans_has_client_key: false,
            manual_payment_enabled: false,
            manual_providers: [],
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});

const dashboardPath = '/platform/dashboard';
const tenantsPath = '/platform/tenants';
const permissionsPath = '/platform/settings/permissions';
const menusPath = '/platform/settings/menus';
const plansPath = '/platform/settings/plans';
const applicationPath = '/platform/settings/application';
const paymentsPath = '/platform/settings/payments';
const vehicleMastersPath = '/platform/settings/vehicle-masters';
const aiAgentPath = '/platform/settings/ai-agent';
const paymentUpdatePath = '/platform/settings/payments';

const makeEmptyManualProvider = () => ({
    id: null,
    provider_name: '',
    account_name: '',
    account_number: '',
    notes: '',
    is_active: true,
});

const resolveInitialManualProviders = () => {
    if (!Array.isArray(props.paymentSettings?.manual_providers)) {
        return [];
    }

    const normalized = props.paymentSettings.manual_providers
        .map((provider) => ({
            id: Number(provider?.id) || null,
            provider_name: String(provider?.provider_name || ''),
            account_name: String(provider?.account_name || ''),
            account_number: String(provider?.account_number || ''),
            notes: String(provider?.notes || ''),
            is_active: Boolean(provider?.is_active ?? true),
        }))
        .filter((provider) => provider.provider_name !== '' || provider.account_number !== '');

    return normalized;
};

const form = useForm({
    midtrans_enabled: Boolean(props.paymentSettings?.midtrans_enabled),
    midtrans_environment: String(props.paymentSettings?.midtrans_environment || 'sandbox'),
    midtrans_merchant_id: String(props.paymentSettings?.midtrans_merchant_id || ''),
    midtrans_server_key: String(props.paymentSettings?.midtrans_server_key || ''),
    midtrans_client_key: String(props.paymentSettings?.midtrans_client_key || ''),
    manual_payment_enabled: Boolean(props.paymentSettings?.manual_payment_enabled),
    manual_providers: resolveInitialManualProviders(),
});
const showMidtransClientKey = ref(false);
const showMidtransServerKey = ref(false);

const manualProviderEditorOpen = ref(false);
const manualProviderEditorIndex = ref(null);
const manualProviderEditor = ref(makeEmptyManualProvider());
const manualProviderEditorErrors = ref({});
const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflowY = ref('');

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const currentPath = computed(() => String(page.url || '').split('?')[0] || '');

const activeSettingsKey = computed(() => {
    const path = currentPath.value;
    if (path === permissionsPath) return 'permissions';
    if (path === menusPath) return 'menus';
    if (path === plansPath) return 'plans';
    if (path === applicationPath) return 'application';
    if (path === paymentsPath) return 'payments';
    if (path === vehicleMastersPath) return 'vehicle-masters';
    if (path === aiAgentPath) return 'ai-agent';
    return '';
});

const isSettingsActive = computed(() => ['permissions', 'menus', 'application', 'payments', 'vehicle-masters', 'ai-agent'].includes(activeSettingsKey.value));

const menuItems = computed(() => [
    { key: 'dashboard', label: 'Dasbor', icon: 'dashboard', href: dashboardPath, active: currentPath.value === dashboardPath },
    { key: 'tenants', label: 'Tenant', icon: 'users', href: tenantsPath, active: currentPath.value === tenantsPath },
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

const hasAnyMethodEnabled = computed(() => Boolean(form.midtrans_enabled || form.manual_payment_enabled));
const canManageMidtransConfig = computed(() => Boolean(form.midtrans_enabled));
const activeManualProviderCount = computed(() => (
    Array.isArray(form.manual_providers)
        ? form.manual_providers.filter((provider) => Boolean(provider?.is_active)).length
        : 0
));
const canManageManualProviders = computed(() => Boolean(form.manual_payment_enabled));
const hasManualProviders = computed(() => form.manual_providers.length > 0);
const isEditingManualProvider = computed(() => Number.isInteger(manualProviderEditorIndex.value) && manualProviderEditorIndex.value >= 0);
const hasMidtransServerKeyValue = computed(() => (
    String(form.midtrans_server_key || '').trim() !== ''
    || Boolean(props.paymentSettings?.midtrans_has_server_key)
));
const hasMidtransClientKeyValue = computed(() => (
    String(form.midtrans_client_key || '').trim() !== ''
    || Boolean(props.paymentSettings?.midtrans_has_client_key)
));
const isMidtransFormValid = computed(() => {
    if (!form.midtrans_enabled) {
        return true;
    }

    const environment = String(form.midtrans_environment || '').trim().toLowerCase();
    return ['sandbox', 'production'].includes(environment)
        && String(form.midtrans_merchant_id || '').trim() !== ''
        && hasMidtransServerKeyValue.value
        && hasMidtransClientKeyValue.value;
});
const isManualFormValid = computed(() => (
    !form.manual_payment_enabled || activeManualProviderCount.value > 0
));
const canSubmitPaymentSettings = computed(() => (
    isMidtransFormValid.value && isManualFormValid.value
));
const submitDisabledReason = computed(() => {
    if (!isMidtransFormValid.value) {
        return 'Lengkapi konfigurasi Midtrans sebelum menyimpan.';
    }

    if (!isManualFormValid.value) {
        return 'Aktifkan minimal satu provider manual sebelum menyimpan.';
    }

    return '';
});
const midtransEnvironmentOptions = computed(() => ([
    {
        value: 'sandbox',
        label: 'Sandbox (Testing)',
        raw: {
            label: 'Sandbox (Testing)',
            subtitle: 'Gunakan untuk percobaan transaksi.',
        },
    },
    {
        value: 'production',
        label: 'Production (Live)',
        raw: {
            label: 'Production (Live)',
            subtitle: 'Gunakan untuk transaksi real.',
        },
    },
]));

const resolvePageScrollContainer = () => {
    if (!pageContentRef.value || typeof pageContentRef.value.closest !== 'function') {
        return null;
    }

    const container = pageContentRef.value.closest('.dashboard-scroll');
    return container instanceof HTMLElement ? container : null;
};

const setPageScrollLock = (isLocked) => {
    const container = lockedScrollContainer.value ?? resolvePageScrollContainer();
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (isLocked) {
        lockedScrollContainer.value = container;
        previousOverflowY.value = container.style.overflowY;
        container.style.overflowY = 'hidden';
        return;
    }

    container.style.overflowY = previousOverflowY.value;
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;
};

const normalizeManualProviderDraft = (payload = {}) => ({
    id: Number(payload?.id) || null,
    provider_name: String(payload?.provider_name || '').trim(),
    account_name: String(payload?.account_name || '').trim(),
    account_number: String(payload?.account_number || '').trim(),
    notes: String(payload?.notes || '').trim(),
    is_active: Boolean(payload?.is_active),
});

const openManualProviderCreate = () => {
    if (!canManageManualProviders.value) {
        return;
    }

    manualProviderEditorOpen.value = true;
    manualProviderEditorIndex.value = null;
    manualProviderEditor.value = makeEmptyManualProvider();
    manualProviderEditorErrors.value = {};
};

const openManualProviderEdit = (index) => {
    if (!canManageManualProviders.value) {
        return;
    }

    if (!Array.isArray(form.manual_providers) || index < 0 || index >= form.manual_providers.length) {
        return;
    }

    manualProviderEditorOpen.value = true;
    manualProviderEditorIndex.value = index;
    manualProviderEditor.value = normalizeManualProviderDraft(form.manual_providers[index]);
    manualProviderEditorErrors.value = {};
};

const cancelManualProviderEditor = () => {
    manualProviderEditorOpen.value = false;
    manualProviderEditorIndex.value = null;
    manualProviderEditor.value = makeEmptyManualProvider();
    manualProviderEditorErrors.value = {};
};

const focusManualProviderFirstInput = () => {
    nextTick(() => {
        const firstInput = document.getElementById('manual-provider-form-name');
        if (!(firstInput instanceof HTMLInputElement)) {
            return;
        }

        firstInput.focus();
        firstInput.select();
    });
};

const handleManualProviderEscKey = (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    event.preventDefault();
    cancelManualProviderEditor();
};

const handleManualProviderEnterKey = (event) => {
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

    if (tagName === 'input') {
        const inputType = String(target.getAttribute('type') || 'text').toLowerCase();
        if (['checkbox', 'radio', 'file', 'submit', 'button'].includes(inputType)) {
            return;
        }
    }

    event.preventDefault();
    saveManualProvider();
};

const saveManualProvider = () => {
    const draft = normalizeManualProviderDraft(manualProviderEditor.value);
    const errors = {};

    if (draft.provider_name === '') {
        errors.provider_name = 'Nama provider wajib diisi.';
    }
    if (draft.account_name === '') {
        errors.account_name = 'Atas nama wajib diisi.';
    }
    if (draft.account_number === '') {
        errors.account_number = 'Nomor rekening/e-wallet wajib diisi.';
    }

    manualProviderEditorErrors.value = errors;
    if (Object.keys(errors).length > 0) {
        return;
    }

    if (isEditingManualProvider.value) {
        form.manual_providers.splice(manualProviderEditorIndex.value, 1, draft);
    } else {
        form.manual_providers.push(draft);
    }

    cancelManualProviderEditor();
};

const removeManualProvider = (index) => {
    if (!Array.isArray(form.manual_providers) || index < 0 || index >= form.manual_providers.length) {
        return;
    }

    form.manual_providers.splice(index, 1);

    if (isEditingManualProvider.value && manualProviderEditorIndex.value === index) {
        cancelManualProviderEditor();
    }
};

const manualProviderErrorSummary = computed(() => (
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('manual_providers.'))
        .map(([, message]) => String(message || '').trim())
        .filter((message) => message !== '')
));

watch(
    () => form.manual_payment_enabled,
    (isEnabled) => {
        if (!isEnabled && manualProviderEditorOpen.value) {
            cancelManualProviderEditor();
        }
    },
);

watch(
    manualProviderEditorOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));

        if (isOpen) {
            focusManualProviderFirstInput();
        }
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    setPageScrollLock(false);
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            midtrans_enabled: Boolean(data.midtrans_enabled),
            midtrans_environment: String(data.midtrans_environment || 'sandbox'),
            midtrans_merchant_id: String(data.midtrans_merchant_id || '').trim(),
            midtrans_server_key: String(data.midtrans_server_key || '').trim(),
            midtrans_client_key: String(data.midtrans_client_key || '').trim(),
            manual_payment_enabled: Boolean(data.manual_payment_enabled),
            manual_providers: Array.isArray(data.manual_providers)
                ? data.manual_providers.map((provider) => ({
                    id: Number(provider?.id) || null,
                    provider_name: String(provider?.provider_name || '').trim(),
                    account_name: String(provider?.account_name || '').trim(),
                    account_number: String(provider?.account_number || '').trim(),
                    notes: String(provider?.notes || '').trim(),
                    is_active: Boolean(provider?.is_active),
                }))
                : [],
        }))
        .post(paymentUpdatePath, {
            preserveScroll: true,
            onSuccess: () => {
                showMidtransClientKey.value = false;
                showMidtransServerKey.value = false;
            },
        });
};

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Pengaturan Pembayaran" />

    <AppDashboardLayout
        title="Pengaturan Pembayaran"
        subtitle="Atur channel pembayaran tenant: Midtrans dan transfer manual multi-provider"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <section ref="pageContentRef" class="w-full space-y-4">
            <p
                v-if="flashStatus"
                class="rounded-xl border border-emerald-300/70 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300"
            >
                {{ flashStatus }}
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Simpan pengaturan setelah konfigurasi selesai
                        </p>
                        <p
                            v-if="submitDisabledReason"
                            class="text-xs font-medium text-amber-700 dark:text-amber-300"
                        >
                            {{ submitDisabledReason }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        :disabled="form.processing || !canSubmitPaymentSettings"
                        :title="!canSubmitPaymentSettings ? submitDisabledReason : ''"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                    </button>
                </div>

                <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Payment Gateway (Midtrans)</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Aktifkan checkout otomatis untuk upgrade plan.</p>
                        </div>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            <input
                                v-model="form.midtrans_enabled"
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                            >
                            Aktifkan Midtrans
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Environment</label>
                            <AsyncSelect
                                id="midtrans-environment"
                                v-model="form.midtrans_environment"
                                :options="midtransEnvironmentOptions"
                                :disabled="!canManageMidtransConfig"
                                :clearable="false"
                                placeholder="Pilih environment"
                                search-placeholder="Cari environment..."
                                trigger-class="h-11 rounded-xl border-slate-300/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/80"
                                menu-max-height-class="max-h-44"
                                :class="form.errors.midtrans_environment ? '[&>button]:border-rose-400/80 [&>button]:bg-rose-50/40 [&>button]:dark:border-rose-400/40 [&>button]:dark:bg-slate-900/80' : ''"
                            >
                                <template #option="{ option }">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ option.label }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ option.subtitle }}</p>
                                    </div>
                                </template>
                            </AsyncSelect>
                            <p v-if="form.errors.midtrans_environment" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ form.errors.midtrans_environment }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="midtrans-merchant-id">
                                Merchant ID
                            </label>
                            <div
                                class="flex items-center gap-2.5 rounded-xl border px-3 transition"
                                :class="form.errors.midtrans_merchant_id
                                    ? 'border-rose-400/80 bg-rose-50/40 dark:bg-slate-900/80'
                                    : 'border-slate-300/80 bg-white/80 focus-within:border-emerald-500/70 focus-within:ring-2 focus-within:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:focus-within:border-emerald-300/70 dark:focus-within:ring-emerald-400/20'"
                            >
                                <input
                                    id="midtrans-merchant-id"
                                    v-model="form.midtrans_merchant_id"
                                    type="text"
                                    class="h-11 w-full border-0 bg-transparent text-sm tracking-wide text-slate-900 caret-emerald-600 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:opacity-60 dark:text-slate-100 dark:caret-emerald-300 dark:placeholder:text-slate-500"
                                    placeholder="Contoh: G123456789"
                                    :disabled="!canManageMidtransConfig"
                                >
                            </div>
                            <p v-if="form.errors.midtrans_merchant_id" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ form.errors.midtrans_merchant_id }}
                            </p>
                        </div>

                        <p
                            v-if="!canManageMidtransConfig"
                            class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            Aktifkan Midtrans dulu untuk mengisi Environment, Merchant ID, dan key.
                        </p>

                        <div class="space-y-2 md:col-span-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="midtrans-client-key">
                                Client Key
                            </label>
                            <div class="relative">
                                <input
                                    id="midtrans-client-key"
                                    v-model="form.midtrans_client_key"
                                    :type="showMidtransClientKey ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 pr-11 text-sm text-slate-700 outline-none transition focus:border-emerald-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                    :disabled="!canManageMidtransConfig"
                                    placeholder="Isi jika baru / ingin ganti client key"
                                >
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex w-11 cursor-pointer items-center justify-center text-slate-500 transition hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-400 dark:hover:text-slate-200"
                                    :disabled="!canManageMidtransConfig"
                                    :aria-label="showMidtransClientKey ? 'Sembunyikan Client Key' : 'Tampilkan Client Key'"
                                    @click="showMidtransClientKey = !showMidtransClientKey"
                                >
                                    <svg v-if="showMidtransClientKey" viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        <path d="M10.58 10.58A2 2 0 0013.41 13.41" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        <path d="M9.88 5.09A10.94 10.94 0 0112 4c5 0 8.27 3.11 9.5 8-0.4 1.58-1.13 2.97-2.12 4.09M6.1 6.1C4.32 7.41 2.99 9.44 2.5 12c1.23 4.89 4.5 8 9.5 8 2.07 0 3.9-.53 5.42-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    </svg>
                                    <svg v-else viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M2.5 12C3.73 7.11 7 4 12 4s8.27 3.11 9.5 8c-1.23 4.89-4.5 8-9.5 8S3.73 16.89 2.5 12Z" stroke="currentColor" stroke-width="1.8" />
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.midtrans_client_key" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ form.errors.midtrans_client_key }}
                            </p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="midtrans-server-key">
                                Server Key
                            </label>
                            <div class="relative">
                                <input
                                    id="midtrans-server-key"
                                    v-model="form.midtrans_server_key"
                                    :type="showMidtransServerKey ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 pr-11 text-sm text-slate-700 outline-none transition focus:border-emerald-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                    :disabled="!canManageMidtransConfig"
                                    placeholder="Isi jika baru / ingin ganti server key"
                                >
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex w-11 cursor-pointer items-center justify-center text-slate-500 transition hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-400 dark:hover:text-slate-200"
                                    :disabled="!canManageMidtransConfig"
                                    :aria-label="showMidtransServerKey ? 'Sembunyikan Server Key' : 'Tampilkan Server Key'"
                                    @click="showMidtransServerKey = !showMidtransServerKey"
                                >
                                    <svg v-if="showMidtransServerKey" viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        <path d="M10.58 10.58A2 2 0 0013.41 13.41" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        <path d="M9.88 5.09A10.94 10.94 0 0112 4c5 0 8.27 3.11 9.5 8-0.4 1.58-1.13 2.97-2.12 4.09M6.1 6.1C4.32 7.41 2.99 9.44 2.5 12c1.23 4.89 4.5 8 9.5 8 2.07 0 3.9-.53 5.42-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    </svg>
                                    <svg v-else viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M2.5 12C3.73 7.11 7 4 12 4s8.27 3.11 9.5 8c-1.23 4.89-4.5 8-9.5 8S3.73 16.89 2.5 12Z" stroke="currentColor" stroke-width="1.8" />
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.midtrans_server_key" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ form.errors.midtrans_server_key }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Pembayaran Manual</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola banyak provider manual (bank/e-wallet).</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <input
                                    v-model="form.manual_payment_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                >
                                Aktifkan Manual
                            </label>

                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                                :disabled="!canManageManualProviders"
                                :title="!canManageManualProviders ? 'Aktifkan metode Manual terlebih dahulu.' : ''"
                                @click="openManualProviderCreate"
                            >
                                Tambah Provider
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <p
                            v-if="!canManageManualProviders"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            Aktifkan metode pembayaran Manual terlebih dahulu sebelum menambah data provider pembayaran.
                        </p>

                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-[820px] w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800/70">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                        <th class="px-3 py-2">No</th>
                                        <th class="px-3 py-2">Nama Provider</th>
                                        <th class="px-3 py-2">Atas Nama</th>
                                        <th class="px-3 py-2">Nomor Rek / E-Wallet</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                    <tr
                                        v-for="(provider, index) in form.manual_providers"
                                        :key="provider.id ? `provider-${provider.id}` : `provider-new-${index}`"
                                        class="align-top text-slate-700 dark:text-slate-200"
                                    >
                                        <td class="px-3 py-3">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold">{{ provider.provider_name || '-' }}</p>
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ provider.account_name || '-' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ provider.account_number || '-' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <span
                                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold"
                                                :class="provider.is_active
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                    : 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-600 dark:bg-slate-700/70 dark:text-slate-300'"
                                            >
                                                {{ provider.is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex cursor-pointer items-center rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                                                    :disabled="!canManageManualProviders"
                                                    :title="!canManageManualProviders ? 'Aktifkan metode Manual terlebih dahulu.' : ''"
                                                    @click="openManualProviderEdit(index)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex cursor-pointer items-center rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                                                    :disabled="!canManageManualProviders"
                                                    :title="!canManageManualProviders ? 'Aktifkan metode Manual terlebih dahulu.' : ''"
                                                    @click="removeManualProvider(index)"
                                                >
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!hasManualProviders">
                                        <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                                            Tidak ada data provider manual.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 px-1">
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Total provider: {{ form.manual_providers.length }} | Aktif: {{ activeManualProviderCount }}
                            </p>
                        </div>

                        <p v-if="form.errors.manual_providers" class="text-sm font-semibold text-rose-600 dark:text-rose-300">
                            {{ form.errors.manual_providers }}
                        </p>
                        <p
                            v-for="(providerErrorMessage, providerErrorIndex) in manualProviderErrorSummary"
                            :key="`provider-error-${providerErrorIndex}`"
                            class="text-xs text-rose-600 dark:text-rose-300"
                        >
                            {{ providerErrorMessage }}
                        </p>
                    </div>
                </article>

                <p
                    v-if="!hasAnyMethodEnabled"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300"
                >
                    Tidak ada metode pembayaran aktif. Owner tidak bisa upgrade plan sampai minimal satu metode diaktifkan.
                </p>

                <p
                    v-if="form.manual_payment_enabled && activeManualProviderCount === 0"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300"
                >
                    Minimal satu provider manual harus aktif.
                </p>

            </form>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="manualProviderEditorOpen"
                    class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                    role="dialog"
                    aria-modal="true"
                >
                    <button
                        type="button"
                        class="absolute inset-0 cursor-pointer"
                        aria-label="Tutup modal provider manual"
                        @click="cancelManualProviderEditor"
                    />

                    <article class="relative z-20 flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-slate-700 dark:bg-slate-900">
                        <header class="flex items-start justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                    {{ isEditingManualProvider ? 'Edit Provider Manual' : 'Tambah Provider Manual' }}
                                </h4>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Lengkapi data provider manual untuk ditampilkan ke owner saat checkout.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                                aria-label="Tutup modal"
                                @click="cancelManualProviderEditor"
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                            </button>
                        </header>

                        <div class="modal-scroll-green min-h-0 overflow-y-auto p-5">
                            <form class="space-y-4" @submit.prevent="saveManualProvider" @keydown.esc="handleManualProviderEscKey" @keydown.enter="handleManualProviderEnterKey">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <InputField
                                        id="manual-provider-form-name"
                                        v-model="manualProviderEditor.provider_name"
                                        label="Nama Provider"
                                        placeholder="Contoh: BCA / OVO / DANA"
                                        :disabled="!canManageManualProviders"
                                        :error="manualProviderEditorErrors.provider_name"
                                    />
                                    <InputField
                                        id="manual-provider-form-account-name"
                                        v-model="manualProviderEditor.account_name"
                                        label="Atas Nama"
                                        placeholder="Contoh: PT AutoServ Nusantara"
                                        :disabled="!canManageManualProviders"
                                        :error="manualProviderEditorErrors.account_name"
                                    />
                                    <InputField
                                        id="manual-provider-form-account-number"
                                        v-model="manualProviderEditor.account_number"
                                        label="Nomor Rek / E-Wallet"
                                        placeholder="Contoh: 1234567890"
                                        :disabled="!canManageManualProviders"
                                        :error="manualProviderEditorErrors.account_number"
                                    />
                                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        <input
                                            v-model="manualProviderEditor.is_active"
                                            type="checkbox"
                                            class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                            :disabled="!canManageManualProviders"
                                        >
                                        Provider aktif
                                    </label>
                                </div>

                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        :disabled="!canManageManualProviders"
                                        @click="cancelManualProviderEditor"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex cursor-pointer items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                                        :disabled="!canManageManualProviders"
                                    >
                                        {{ isEditingManualProvider ? 'Simpan Perubahan' : 'Tambah Provider' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </article>
                </div>
            </Transition>
        </section>
    </AppDashboardLayout>
</template>

