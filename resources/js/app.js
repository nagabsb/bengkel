import '../css/app.css';
import './bootstrap';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import VueSignaturePad from 'vue-signature-pad';
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css';
import { useAppStore } from './Stores/useAppStore';

const fallbackAppName = import.meta.env.VITE_APP_NAME || 'Laravel';

const normalizeAppName = (value) => {
    const parsed = String(value ?? '').trim();

    return parsed !== '' ? parsed : fallbackAppName;
};

const normalizeLogoUrl = (value) => {
    const parsed = String(value ?? '').trim();

    return parsed !== '' ? parsed : '';
};

const normalizeLogoBackgroundColor = (value) => {
    const parsed = String(value ?? '').trim();

    if (/^#[0-9a-fA-F]{6}$/.test(parsed)) {
        return parsed.toUpperCase();
    }

    return '#10B981';
};

const resolveAppInitials = (appName) => {
    const normalizedName = String(appName ?? '').trim();
    if (normalizedName === '') {
        return 'APP';
    }

    const words = normalizedName
        .split(/\s+/)
        .map((word) => word.trim())
        .filter((word) => word !== '');

    if (words.length >= 2) {
        return `${words[0][0]}${words[1][0]}`.toUpperCase();
    }

    return words[0].slice(0, 2).toUpperCase();
};

const buildFallbackFavicon = (appName, logoBackgroundColor, logoBackgroundEnabled) => {
    const initials = resolveAppInitials(appName);
    const background = logoBackgroundEnabled
        ? normalizeLogoBackgroundColor(logoBackgroundColor)
        : '#FFFFFF';
    const foreground = logoBackgroundEnabled ? '#FFFFFF' : '#0F172A';

    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
    <rect x="0" y="0" width="64" height="64" rx="14" fill="${background}" />
    <text
        x="50%"
        y="50%"
        dominant-baseline="central"
        text-anchor="middle"
        font-family="Outfit, Arial, sans-serif"
        font-size="24"
        font-weight="700"
        fill="${foreground}"
    >
        ${initials}
    </text>
</svg>`.trim();

    return `data:image/svg+xml,${encodeURIComponent(svg)}`;
};

const syncFavicon = ({
    logoUrl,
    appName,
    logoBackgroundColor,
    logoBackgroundEnabled,
}) => {
    if (typeof document === 'undefined') {
        return;
    }

    const normalizedLogoUrl = normalizeLogoUrl(logoUrl);
    const href = normalizedLogoUrl !== ''
        ? normalizedLogoUrl
        : buildFallbackFavicon(appName, logoBackgroundColor, logoBackgroundEnabled);

    let iconElement = document.querySelector('link[data-platform-favicon]');
    if (!iconElement) {
        iconElement = document.createElement('link');
        iconElement.setAttribute('rel', 'icon');
        iconElement.setAttribute('data-platform-favicon', 'true');
        document.head.appendChild(iconElement);
    }

    if (iconElement.getAttribute('href') !== href) {
        iconElement.setAttribute('href', href);
    }
};

const syncDocumentTitleWithAppName = (appName) => {
    if (typeof document === 'undefined') {
        return;
    }

    const normalizedAppName = normalizeAppName(appName);
    const separator = ' - ';
    const currentTitle = String(document.title ?? '').trim();

    if (currentTitle === '') {
        document.title = normalizedAppName;
        return;
    }

    const suffixIndex = currentTitle.lastIndexOf(separator);
    if (suffixIndex > -1) {
        const pageTitle = currentTitle.slice(0, suffixIndex).trim();
        document.title = pageTitle !== ''
            ? `${pageTitle}${separator}${normalizedAppName}`
            : normalizedAppName;
        return;
    }

    document.title = `${currentTitle}${separator}${normalizedAppName}`;
};

const initialAppNameFromMeta = typeof document !== 'undefined'
    ? document.querySelector('meta[name="platform-app-name"]')?.getAttribute('content')
    : null;

if (initialAppNameFromMeta) {
    window.__PLATFORM_APP_NAME__ = normalizeAppName(initialAppNameFromMeta);
}
const syncBrandingFromPageProps = (pageProps) => {
    const appName = normalizeAppName(pageProps?.appName);

    window.__PLATFORM_APP_NAME__ = appName;

    syncFavicon({
        logoUrl: pageProps?.appLogoUrl,
        appName,
        logoBackgroundColor: pageProps?.logoBackgroundColor,
        logoBackgroundEnabled: Boolean(pageProps?.logoBackgroundEnabled ?? true),
    });
    syncDocumentTitleWithAppName(appName);
};

createInertiaApp({
    title: (title) => {
        const appName = normalizeAppName(window.__PLATFORM_APP_NAME__);

        return title ? `${title} - ${appName}` : appName;
    },
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        syncBrandingFromPageProps(props?.initialPage?.props ?? {});
        const pinia = createPinia();
        const appStore = useAppStore(pinia);
        appStore.syncFromPageProps(props?.initialPage?.props ?? {});

        router.on('success', (event) => {
            const pageProps = event?.detail?.page?.props ?? {};
            syncBrandingFromPageProps(pageProps);
            appStore.syncFromPageProps(pageProps);
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(VueSignaturePad)
            .mount(el);
    },
    progress: {
        color: '#12d7a6',
    },
});

