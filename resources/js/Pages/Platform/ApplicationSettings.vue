<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import InputField from '../../Components/UI/InputField.vue';

const DEFAULT_LOGO_BACKGROUND_COLOR = '#10B981';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    branding: {
        type: Object,
        default: () => ({
            app_name: 'AutoServ',
            app_logo_url: null,
            logo_background_enabled: true,
            logo_background_color: DEFAULT_LOGO_BACKGROUND_COLOR,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});
const logoInputRef = ref(null);
const selectedLogoPreviewUrl = ref(null);

const normalizeHexColor = (value) => {
    const normalized = String(value || '').trim().toUpperCase();
    return /^#[A-F0-9]{6}$/.test(normalized) ? normalized : DEFAULT_LOGO_BACKGROUND_COLOR;
};

const resolveContrastTextColor = (hexColor) => {
    const normalized = normalizeHexColor(hexColor).replace('#', '');
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const luma = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return luma >= 148 ? '#0F172A' : '#FFFFFF';
};

const dashboardPath = '/platform/dashboard';
const tenantsPath = '/platform/tenants';
const permissionsPath = '/platform/settings/permissions';
const menusPath = '/platform/settings/menus';
const plansPath = '/platform/settings/plans';
const applicationPath = '/platform/settings/application';
const paymentsPath = '/platform/settings/payments';
const vehicleMastersPath = '/platform/settings/vehicle-masters';
const aiAgentPath = '/platform/settings/ai-agent';
const applicationUpdatePath = '/platform/settings/application';

const form = useForm({
    app_name: String(props.branding?.app_name || 'AutoServ'),
    app_logo: null,
    remove_logo: false,
    logo_background_enabled: Boolean(props.branding?.logo_background_enabled ?? true),
    logo_background_color: normalizeHexColor(props.branding?.logo_background_color || DEFAULT_LOGO_BACKGROUND_COLOR),
});

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

const currentLogoUrl = computed(() => String(props.branding?.app_logo_url || ''));

const effectiveLogoPreviewUrl = computed(() => {
    if (selectedLogoPreviewUrl.value) {
        return selectedLogoPreviewUrl.value;
    }

    if (form.remove_logo) {
        return '';
    }

    return currentLogoUrl.value;
});

const appInitials = computed(() => {
    const segments = String(form.app_name || props.branding?.app_name || 'AS')
        .split(' ')
        .map((segment) => segment.trim())
        .filter((segment) => segment !== '');

    if (segments.length === 0) {
        return 'AS';
    }

    return segments
        .slice(0, 2)
        .map((segment) => segment[0])
        .join('')
        .toUpperCase();
});

const effectiveLogoBackgroundColor = computed(() => normalizeHexColor(form.logo_background_color));

const logoAvatarStyle = computed(() => {
    if (!form.logo_background_enabled) {
        return {
            backgroundColor: 'transparent',
            color: '#0F172A',
        };
    }

    return {
        backgroundColor: effectiveLogoBackgroundColor.value,
        color: resolveContrastTextColor(effectiveLogoBackgroundColor.value),
    };
});

const clearObjectPreview = () => {
    if (!selectedLogoPreviewUrl.value) {
        return;
    }

    URL.revokeObjectURL(selectedLogoPreviewUrl.value);
    selectedLogoPreviewUrl.value = null;
};

const onLogoChange = (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const [file] = input.files || [];
    form.app_logo = file || null;
    form.remove_logo = false;

    clearObjectPreview();
    if (file) {
        selectedLogoPreviewUrl.value = URL.createObjectURL(file);
    }
};

const removeLogo = () => {
    clearObjectPreview();
    form.app_logo = null;
    form.remove_logo = true;

    if (logoInputRef.value instanceof HTMLInputElement) {
        logoInputRef.value.value = '';
    }
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            app_name: String(data.app_name || '').trim(),
            remove_logo: Boolean(data.remove_logo),
            logo_background_enabled: Boolean(data.logo_background_enabled),
            logo_background_color: normalizeHexColor(data.logo_background_color),
        }))
        .post(applicationUpdatePath, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('app_logo', 'remove_logo');
                clearObjectPreview();

                if (logoInputRef.value instanceof HTMLInputElement) {
                    logoInputRef.value.value = '';
                }
            },
        });
};

const logout = () => {
    logoutForm.post('/logout');
};

onBeforeUnmount(() => {
    clearObjectPreview();
});
</script>

<template>
    <Head title="Pengaturan Aplikasi" />

    <AppDashboardLayout
        title="Pengaturan Aplikasi"
        subtitle="Atur nama, logo, dan style logo aplikasi SaaS"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <section class="mx-auto w-full max-w-3xl space-y-4">
            <p
                v-if="flashStatus"
                class="rounded-xl border border-emerald-300/70 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300"
            >
                {{ flashStatus }}
            </p>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <form class="space-y-5" @submit.prevent="submit">
                    <InputField
                        id="platform-app-name"
                        v-model="form.app_name"
                        label="Nama Aplikasi"
                        placeholder="Contoh: AutoServ"
                        :error="form.errors.app_name"
                    />

                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="platform-app-logo">
                            Logo Aplikasi
                        </label>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                            <div class="flex flex-wrap items-center gap-4">
                                <div
                                    class="grid h-16 w-16 place-items-center overflow-hidden rounded-2xl text-base font-bold dark:border-slate-700 dark:text-slate-200"
                                    :class="form.logo_background_enabled ? 'border border-transparent' : 'border border-slate-300 bg-transparent dark:border-slate-600'"
                                    :style="logoAvatarStyle"
                                >
                                    <img
                                        v-if="effectiveLogoPreviewUrl"
                                        :src="effectiveLogoPreviewUrl"
                                        :alt="`Logo ${form.app_name || 'Aplikasi'}`"
                                        class="h-full w-full object-contain p-1"
                                    >
                                    <span v-else>{{ appInitials }}</span>
                                </div>

                                <div class="min-w-0 flex-1 space-y-2">
                                    <input
                                        id="platform-app-logo"
                                        ref="logoInputRef"
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg,image/webp"
                                        class="block w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-emerald-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:border-emerald-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:file:bg-emerald-500/20 dark:file:text-emerald-300"
                                        @change="onLogoChange"
                                    >
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Gunakan PNG/JPG/WEBP maksimal 2MB.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.app_logo" class="text-xs text-rose-600 dark:text-rose-300">
                            {{ form.errors.app_logo }}
                        </p>
                    </div>

                    <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                        <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            <input
                                v-model="form.logo_background_enabled"
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                            >
                            Pakai background logo
                        </label>

                        <div v-if="form.logo_background_enabled" class="grid gap-2 sm:max-w-xs">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="platform-logo-bg-color">
                                Warna Background
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="platform-logo-bg-color"
                                    v-model="form.logo_background_color"
                                    type="color"
                                    class="h-10 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-600 dark:bg-slate-900"
                                >
                                <input
                                    v-model="form.logo_background_color"
                                    type="text"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm uppercase text-slate-700 outline-none focus:border-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                    placeholder="#10B981"
                                >
                            </div>
                            <p v-if="form.errors.logo_background_color" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ form.errors.logo_background_color }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-rose-300 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:border-rose-400/50 dark:hover:text-rose-300"
                            :disabled="form.processing || (!effectiveLogoPreviewUrl && !form.app_logo)"
                            @click="removeLogo"
                        >
                            Hapus Logo
                        </button>

                        <button
                            type="submit"
                            class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                        </button>
                    </div>
                </form>
            </article>
        </section>
    </AppDashboardLayout>
</template>
