import { defineStore } from 'pinia';

const normalizeText = (value) => String(value ?? '').trim();

export const useAppStore = defineStore('app', {
    state: () => ({
        appName: '',
        appLogoUrl: '',
        logoBackgroundColor: '#10B981',
        logoBackgroundEnabled: true,
        tenantId: '',
        permissions: [],
    }),
    getters: {
        hasPermission: (state) => (permissionName) => {
            const normalizedPermissionName = normalizeText(permissionName);
            if (normalizedPermissionName === '') {
                return false;
            }

            return state.permissions.includes(normalizedPermissionName);
        },
    },
    actions: {
        syncFromPageProps(pageProps = {}) {
            this.appName = normalizeText(pageProps?.appName);
            this.appLogoUrl = normalizeText(pageProps?.appLogoUrl);
            this.logoBackgroundColor = normalizeText(pageProps?.logoBackgroundColor) || '#10B981';
            this.logoBackgroundEnabled = Boolean(pageProps?.logoBackgroundEnabled ?? true);
            this.tenantId = normalizeText(pageProps?.auth?.user?.tenant_id);

            this.permissions = Array.isArray(pageProps?.permissions)
                ? pageProps.permissions
                    .map((permissionName) => normalizeText(permissionName))
                    .filter((permissionName) => permissionName !== '')
                : [];
        },
    },
});
