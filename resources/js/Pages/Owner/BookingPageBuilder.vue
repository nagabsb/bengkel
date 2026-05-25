<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import FileUpload from '../../Components/UI/FileUpload.vue';
import InputField from '../../Components/UI/InputField.vue';
import { updateOwnerBookingPageBuilder } from './Services/ownerBookingPageBuilderService';

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
    tenantProfile: {
        type: Object,
        default: () => ({
            name: 'Tenant',
            subdomain: '',
            public_booking_url: '',
        }),
    },
    builderConfig: {
        type: Object,
        default: () => ({
            mode: 'tech',
            primary_color: '#0F766E',
            font_preset: 'modern',
            radius_preset: 'medium',
            headline: 'Rental Tenda Sejahtera',
            subheadline: 'Sewa tenda & perlengkapan pesta',
            cta_label: 'Booking Sekarang',
            cta_size: 'medium',
            trust_badge: 'baru saja booking 5 menit lalu',
            gallery_image_paths: [],
            gallery_images: [],
            is_active: true,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});

const normalizeStringArray = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => String(item || '').trim())
        .filter((item) => item !== '');
};

const mapBuilderConfig = (config = {}) => ({
    mode: ['tech', 'dark'].includes(String(config?.mode || '').trim().toLowerCase())
        ? String(config.mode).trim().toLowerCase()
        : 'tech',
    primary_color: /^#[A-F0-9]{6}$/.test(String(config?.primary_color || '').trim().toUpperCase())
        ? String(config.primary_color).trim().toUpperCase()
        : '#0F766E',
    font_preset: ['modern', 'elegant', 'playful', 'minimal', 'bold'].includes(String(config?.font_preset || '').trim().toLowerCase())
        ? String(config.font_preset).trim().toLowerCase()
        : 'modern',
    radius_preset: ['sharp', 'subtle', 'medium', 'rounded', 'pill'].includes(String(config?.radius_preset || '').trim().toLowerCase())
        ? String(config.radius_preset).trim().toLowerCase()
        : 'medium',
    subheadline: String(config?.subheadline || '').trim() || 'Sewa tenda & perlengkapan pesta',
    cta_label: String(config?.cta_label || '').trim() || 'Booking Sekarang',
    cta_size: ['small', 'medium', 'large'].includes(String(config?.cta_size || '').trim().toLowerCase())
        ? String(config.cta_size).trim().toLowerCase()
        : 'medium',
    trust_badge: String(config?.trust_badge || '').trim() || 'baru saja booking 5 menit lalu',
    existing_gallery_paths: normalizeStringArray(config?.gallery_image_paths).slice(0, 4),
    gallery_images: [],
    is_active: Boolean(config?.is_active ?? true),
});

const builderForm = useForm(mapBuilderConfig(props.builderConfig));
const galleryExistingUrls = ref(normalizeStringArray(props.builderConfig?.gallery_images).slice(0, 4));
const galleryFilePreviewUrls = ref([]);

const revokeGalleryFilePreviewUrls = () => {
    if (typeof URL === 'undefined') {
        galleryFilePreviewUrls.value = [];
        return;
    }

    galleryFilePreviewUrls.value.forEach((previewUrl) => {
        try {
            URL.revokeObjectURL(previewUrl);
        } catch (_error) {
            // Ignore invalid object URL cleanup errors.
        }
    });

    galleryFilePreviewUrls.value = [];
};

const rebuildGalleryFilePreviewUrls = (files = []) => {
    revokeGalleryFilePreviewUrls();

    if (typeof URL === 'undefined') {
        return;
    }

    galleryFilePreviewUrls.value = files
        .filter((file) => typeof File !== 'undefined' && file instanceof File)
        .map((file) => URL.createObjectURL(file));
};

const availableGallerySlots = computed(() => Math.max(0, 4 - builderForm.existing_gallery_paths.length));
const hasReachedGalleryLimit = computed(() => availableGallerySlots.value <= 0);

const galleryPreviewItems = computed(() => {
    const existingItems = galleryExistingUrls.value.map((url, index) => ({
        id: `existing-gallery-${index}`,
        url,
        source: 'saved',
    }));
    const newItems = galleryFilePreviewUrls.value.map((url, index) => ({
        id: `new-gallery-${index}`,
        url,
        source: 'new',
    }));

    return [...existingItems, ...newItems].slice(0, 4);
});
const hasGalleryPreview = computed(() => galleryPreviewItems.value.length > 0);

const galleryCardError = computed(() => String(
    builderForm.errors?.gallery_images
    || page.props?.errors?.gallery_images
    || ''
).trim());

const galleryCardHint = computed(() => {
    if (galleryCardError.value !== '') {
        return galleryCardError.value;
    }

    if (hasReachedGalleryLimit.value) {
        return 'Maksimal 4 gambar tercapai. Hapus gambar dulu jika ingin ganti.';
    }

    return `Sisa slot galeri: ${availableGallerySlots.value} gambar.`;
});

const normalizeGalleryFiles = (files) => (
    Array.isArray(files)
        ? files.filter((file) => typeof File !== 'undefined' && file instanceof File)
        : []
);

const dedupeGalleryFiles = (files) => {
    const seen = new Set();

    return files.filter((file) => {
        const fingerprint = `${file.name}:${file.size}:${file.lastModified}`;
        if (seen.has(fingerprint)) {
            return false;
        }

        seen.add(fingerprint);
        return true;
    });
};

const handleGalleryImagesUpdate = (nextFiles) => {
    const currentFiles = normalizeGalleryFiles(builderForm.gallery_images);
    const normalizedFiles = normalizeGalleryFiles(nextFiles);

    const isRemovalPayload = normalizedFiles.every((file) => currentFiles.includes(file));
    const candidateFiles = isRemovalPayload
        ? normalizedFiles
        : dedupeGalleryFiles([...currentFiles, ...normalizedFiles]);
    const limitedFiles = candidateFiles.slice(0, availableGallerySlots.value);

    builderForm.gallery_images = limitedFiles;

    if (candidateFiles.length > limitedFiles.length) {
        builderForm.setError('gallery_images', 'Total gambar galeri maksimal 4 file.');
        return;
    }

    if (builderForm.errors.gallery_images) {
        builderForm.clearErrors('gallery_images');
    }
};

const handleGalleryUploadInvalid = (payload) => {
    const message = String(payload?.message || 'Upload gambar galeri tidak valid.').trim();
    builderForm.setError('gallery_images', message || 'Upload gambar galeri tidak valid.');
};

const removeExistingGalleryImage = (index) => {
    builderForm.existing_gallery_paths = builderForm.existing_gallery_paths
        .filter((_, imageIndex) => imageIndex !== index);
    galleryExistingUrls.value = galleryExistingUrls.value
        .filter((_, imageIndex) => imageIndex !== index);

    if (builderForm.errors.gallery_images) {
        builderForm.clearErrors('gallery_images');
    }
};

watch(
    () => props.builderConfig,
    (nextConfig) => {
        builderForm.defaults(mapBuilderConfig(nextConfig));
        builderForm.reset();
        galleryExistingUrls.value = normalizeStringArray(nextConfig?.gallery_images).slice(0, 4);
        revokeGalleryFilePreviewUrls();
    },
    { deep: true },
);

watch(
    () => builderForm.gallery_images,
    (nextFiles) => {
        rebuildGalleryFilePreviewUrls(Array.isArray(nextFiles) ? nextFiles : []);
    },
    { deep: true },
);

onBeforeUnmount(() => {
    revokeGalleryFilePreviewUrls();
});

const viewport = ref('mobile');
const copyFeedback = ref('');
const copyFeedbackLevel = ref('success');

const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const savePath = computed(() => `${baseOwnerPath.value}/bookings/builder`);

const flashStatus = computed(() => String(page.props?.flash?.status || '').trim());
const pageErrors = computed(() => page.props?.errors || {});
const formLevelError = computed(() => String(
    builderForm.errors?.update_builder
    || pageErrors.value?.update_builder
    || ''
).trim());

const statusMessage = computed(() => (
    copyFeedback.value !== '' ? copyFeedback.value : flashStatus.value
));
const statusClass = computed(() => (
    copyFeedbackLevel.value === 'error'
        ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300'
        : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300'
));

const publicBookingUrl = computed(() => String(props.tenantProfile?.public_booking_url || '').trim());

const tenantHostLabel = computed(() => {
    if (publicBookingUrl.value !== '') {
        try {
            return new URL(publicBookingUrl.value).host;
        } catch (_error) {
            return publicBookingUrl.value;
        }
    }

    const subdomain = String(props.tenantProfile?.subdomain || '').trim();
    return subdomain || String(props.tenantProfile?.name || 'Tenant').trim() || 'Tenant';
});

const automaticHeadline = computed(() => {
    const workshopName = String(props.tenantProfile?.name || '').trim();
    if (workshopName !== '') {
        return workshopName;
    }

    return String(props.builderConfig?.headline || '').trim() || 'Booking Servis Cepat & Mudah';
});

const colorOptions = [
    { value: '#0F766E', label: 'Teal' },
    { value: '#1D4ED8', label: 'Biru' },
    { value: '#B45309', label: 'Oranye' },
    { value: '#B91C1C', label: 'Merah' },
    { value: '#14532D', label: 'Hijau Tua' },
    { value: '#334155', label: 'Grafit' },
    { value: '#7C2D12', label: 'Tembaga' },
];

const fontOptions = [
    { value: 'modern', label: 'Modern' },
    { value: 'elegant', label: 'Elegant' },
    { value: 'playful', label: 'Playful' },
    { value: 'minimal', label: 'Minimal' },
    { value: 'bold', label: 'Bold' },
];

const radiusOptions = [
    { value: 'sharp', label: 'Sharp' },
    { value: 'subtle', label: 'Subtle' },
    { value: 'medium', label: 'Medium' },
    { value: 'rounded', label: 'Rounded' },
    { value: 'pill', label: 'Pill' },
];

const ctaSizeOptions = [
    { value: 'small', label: 'Kecil' },
    { value: 'medium', label: 'Sedang' },
    { value: 'large', label: 'Besar' },
];

const modeOptions = [
    { value: 'tech', label: 'Tech' },
    { value: 'dark', label: 'Dark' },
];

const fontFamilyMap = {
    modern: "'Outfit', 'Poppins', 'Segoe UI', sans-serif",
    elegant: "'Merriweather', 'Georgia', serif",
    playful: "'Nunito', 'Trebuchet MS', sans-serif",
    minimal: "'DM Sans', 'Segoe UI', sans-serif",
    bold: "'Montserrat', 'Segoe UI', sans-serif",
};

const radiusValueMap = {
    sharp: '0.5rem',
    subtle: '0.75rem',
    medium: '1rem',
    rounded: '1.5rem',
    pill: '9999px',
};

const isDarkPreview = computed(() => builderForm.mode === 'dark');
const resolvedPrimaryColor = computed(() => (
    /^#[A-F0-9]{6}$/.test(String(builderForm.primary_color || '').toUpperCase())
        ? String(builderForm.primary_color).toUpperCase()
        : '#0F766E'
));

const mixHexColor = (hexColor, targetHex = '#FFFFFF', ratio = 0.2) => {
    const safeHex = String(hexColor || '').trim().toUpperCase();
    const safeTargetHex = String(targetHex || '').trim().toUpperCase();

    if (!/^#[A-F0-9]{6}$/.test(safeHex) || !/^#[A-F0-9]{6}$/.test(safeTargetHex)) {
        return '#0F766E';
    }

    const clampedRatio = Math.min(1, Math.max(0, ratio));
    const sourceChannels = [
        Number.parseInt(safeHex.slice(1, 3), 16),
        Number.parseInt(safeHex.slice(3, 5), 16),
        Number.parseInt(safeHex.slice(5, 7), 16),
    ];
    const targetChannels = [
        Number.parseInt(safeTargetHex.slice(1, 3), 16),
        Number.parseInt(safeTargetHex.slice(3, 5), 16),
        Number.parseInt(safeTargetHex.slice(5, 7), 16),
    ];

    const mix = (sourceChannel, targetChannel) => Math.round(
        sourceChannel + ((targetChannel - sourceChannel) * clampedRatio)
    );
    const toHex = (value) => value.toString(16).padStart(2, '0').toUpperCase();

    return `#${toHex(mix(sourceChannels[0], targetChannels[0]))}${toHex(mix(sourceChannels[1], targetChannels[1]))}${toHex(mix(sourceChannels[2], targetChannels[2]))}`;
};

const previewPrimaryStrongColor = computed(() => mixHexColor(resolvedPrimaryColor.value, '#000000', 0.16));
const previewPrimaryColor = computed(() => mixHexColor(resolvedPrimaryColor.value, '#000000', 0.08));
const previewPrimarySoftColor = computed(() => mixHexColor(resolvedPrimaryColor.value, '#FFFFFF', 0.08));

const previewPalette = computed(() => {
    if (isDarkPreview.value) {
        return {
            panel: '#0F172A',
            section: '#111827',
            card: '#1E293B',
            border: '#334155',
            text: '#F8FAFC',
            muted: '#94A3B8',
            ctaText: '#0F172A',
        };
    }

    return {
        panel: '#FFFFFF',
        section: '#F8FAFC',
        card: '#FFFFFF',
        border: '#E2E8F0',
        text: '#0F172A',
        muted: '#64748B',
        ctaText: '#0F172A',
    };
});

const desktopPreviewStyle = computed(() => ({
    backgroundColor: previewPalette.value.panel,
    borderColor: previewPalette.value.border,
    color: previewPalette.value.text,
    fontFamily: fontFamilyMap[builderForm.font_preset] || fontFamilyMap.modern,
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const phonePreviewStyle = computed(() => ({
    backgroundColor: previewPalette.value.panel,
    borderColor: previewPalette.value.border,
    color: previewPalette.value.text,
    fontFamily: fontFamilyMap[builderForm.font_preset] || fontFamilyMap.modern,
}));

const previewHeroStyle = computed(() => ({
    background: `linear-gradient(145deg, ${previewPrimaryStrongColor.value}, ${previewPrimarySoftColor.value})`,
}));

const previewCtaStyle = computed(() => ({
    backgroundColor: '#FFFFFF',
    color: previewPalette.value.ctaText,
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const previewMobileCtaSizeClass = computed(() => ({
    small: 'min-w-[160px] px-4 py-2.5 text-base',
    medium: 'min-w-[190px] px-5 py-3 text-lg',
    large: 'min-w-[220px] px-6 py-3.5 text-xl',
}[builderForm.cta_size] || 'min-w-[190px] px-5 py-3 text-lg'));

const previewDesktopCtaSizeClass = computed(() => ({
    small: 'px-4 py-2 text-sm',
    medium: 'px-6 py-3 text-base',
    large: 'px-7 py-3.5 text-lg',
}[builderForm.cta_size] || 'px-6 py-3 text-base'));

const previewSurfaceStyle = computed(() => ({
    backgroundColor: previewPalette.value.section,
    borderColor: previewPalette.value.border,
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const previewItemStyle = computed(() => ({
    backgroundColor: previewPalette.value.card,
    borderColor: previewPalette.value.border,
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const previewMutedTextStyle = computed(() => ({
    color: previewPalette.value.muted,
}));

const previewHeadingTextStyle = computed(() => ({
    color: previewPalette.value.text,
}));

const previewBookingFormCardStyle = computed(() => ({
    backgroundColor: previewPalette.value.section,
    borderColor: previewPalette.value.border,
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const previewBookingFieldStyle = computed(() => ({
    backgroundColor: previewPalette.value.card,
    borderColor: previewPalette.value.border,
    color: previewPalette.value.text,
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const previewBookingButtonStyle = computed(() => ({
    backgroundColor: previewPrimaryColor.value,
    color: '#FFFFFF',
    borderRadius: radiusValueMap[builderForm.radius_preset] || radiusValueMap.medium,
}));

const previewWorkspaceStyle = computed(() => (
    isDarkPreview.value
        ? {
            background: 'linear-gradient(145deg, #020617 0%, #111827 45%, #1E293B 100%)',
        }
        : {
            background: 'linear-gradient(145deg, #E2E8F0 0%, #CBD5E1 50%, #94A3B8 100%)',
        }
));

const handleCopyPublicUrl = async () => {
    if (publicBookingUrl.value === '') {
        copyFeedbackLevel.value = 'error';
        copyFeedback.value = 'Link booking publik belum tersedia.';
        return;
    }

    if (typeof navigator === 'undefined' || !navigator.clipboard?.writeText) {
        copyFeedbackLevel.value = 'error';
        copyFeedback.value = 'Clipboard browser tidak tersedia di perangkat ini.';
        return;
    }

    try {
        await navigator.clipboard.writeText(publicBookingUrl.value);
        copyFeedbackLevel.value = 'success';
        copyFeedback.value = 'Link booking publik berhasil disalin.';
    } catch (_error) {
        copyFeedbackLevel.value = 'error';
        copyFeedback.value = 'Gagal menyalin link booking publik.';
    }
};

const submitBuilderForm = () => {
    copyFeedback.value = '';
    updateOwnerBookingPageBuilder(builderForm, savePath.value);
};

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Page Builder Owner" />

    <AppDashboardLayout
        title="Page Builder"
        subtitle="Kustomisasi booking page publik"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div class="space-y-4">
            <p
                v-if="statusMessage"
                class="rounded-xl border px-4 py-3 text-sm font-medium"
                :class="statusClass"
            >
                {{ statusMessage }}
            </p>

            <p
                v-if="formLevelError"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300"
            >
                {{ formLevelError }}
            </p>

            <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_16px_40px_rgba(15,23,42,0.08)] dark:border-slate-700 dark:bg-slate-900 dark:shadow-none">
                <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-500 text-sm font-bold text-white">
                            B
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-[2rem] font-bold leading-tight text-slate-900 dark:text-slate-100">
                                Page Builder
                            </h2>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">
                                {{ tenantHostLabel }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:bg-slate-100 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            :disabled="publicBookingUrl === ''"
                            @click="handleCopyPublicUrl"
                        >
                            Salin
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-emerald-500 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="builderForm.processing"
                            @click="submitBuilderForm"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M4 5A2 2 0 0 1 6 3H16L20 7V19A2 2 0 0 1 18 21H6A2 2 0 0 1 4 19V5Z" stroke="currentColor" stroke-width="1.8" />
                                <path d="M8 3V9H15V3" stroke="currentColor" stroke-width="1.8" />
                                <path d="M8 14H16" stroke="currentColor" stroke-width="1.8" />
                            </svg>
                            <span>{{ builderForm.processing ? 'Menyimpan...' : 'Simpan' }}</span>
                        </button>
                    </div>
                </header>

                <div class="border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="flex items-center gap-2 text-[15px] text-slate-600 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8" />
                                    <path d="M9.5 12L11.5 14L15 10.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span class="font-medium">Live Preview</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ hasGalleryPreview ? '7 section aktif' : '6 section aktif' }}
                                </span>
                            </div>

                        <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                                :class="viewport === 'desktop'
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                                    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-slate-100'"
                                @click="viewport = 'desktop'"
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                    <rect x="3.5" y="4.5" width="17" height="11" rx="2" stroke="currentColor" stroke-width="1.7" />
                                    <path d="M9 19.5H15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                                Desktop
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                                :class="viewport === 'mobile'
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                                    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-slate-100'"
                                @click="viewport = 'mobile'"
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                    <rect x="7.5" y="3.5" width="9" height="17" rx="2.5" stroke="currentColor" stroke-width="1.7" />
                                    <circle cx="12" cy="17.5" r="0.9" fill="currentColor" />
                                </svg>
                                Mobile
                            </button>
                        </div>
                    </div>
                </div>

                <div class="booking-builder-body">

                    <div class="booking-builder-preview min-w-0 border-r border-slate-200 dark:border-slate-700">

                        <div class="relative flex min-h-[700px] items-start justify-center overflow-hidden px-4 py-6" :style="previewWorkspaceStyle">
                            <div class="absolute bottom-0 right-0 top-0 w-3 bg-gradient-to-l from-slate-900/50 to-transparent" />

                            <div v-if="viewport === 'mobile'" class="iphone-stage">
                                <div class="iphone-shell">
                                    <div class="iphone-device">
                                        <div class="iphone-screen border" :style="phonePreviewStyle">
                                            <div class="iphone-dynamic-island" />

                                            <div class="iphone-scroll" @wheel.stop @touchmove.stop>
                                                <header class="px-6 pb-9 pt-20 text-center text-white" :style="previewHeroStyle">
                                                    <div class="mx-auto mb-6 grid h-14 w-14 place-items-center rounded-2xl bg-white/20 text-white/90">
                                                        <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7" aria-hidden="true">
                                                            <circle cx="9" cy="20" r="1.5" fill="currentColor" />
                                                            <circle cx="17" cy="20" r="1.5" fill="currentColor" />
                                                            <path d="M5 5H7L9.3 14.2C9.43 14.72 9.89 15.08 10.42 15.08H16.77C17.24 15.08 17.67 14.79 17.85 14.35L20 9H8.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    </div>
                                                    <h3 class="mx-auto max-w-[220px] text-[2rem] font-bold leading-[1.08] tracking-tight">{{ automaticHeadline }}</h3>
                                                    <p class="mt-3 text-[1rem] text-white/90">{{ builderForm.subheadline }}</p>
                                                    <button
                                                        type="button"
                                                        class="mt-7 inline-flex cursor-pointer items-center justify-center font-bold shadow-sm"
                                                        :class="previewMobileCtaSizeClass"
                                                        :style="previewCtaStyle"
                                                    >
                                                        {{ builderForm.cta_label }}
                                                    </button>
                                                </header>

                                                <section v-if="hasGalleryPreview" class="space-y-4 px-4 py-4">
                                                    <div class="space-y-3">
                                                        <h4 class="flex items-center gap-3 text-[2rem] font-bold leading-tight">
                                                            <span class="inline-flex h-8 w-1.5 rounded-full" :style="{ backgroundColor: previewPrimaryStrongColor }" />
                                                            Galeri
                                                        </h4>

                                                        <div class="grid grid-cols-2 gap-3">
                                                            <article
                                                                v-for="image in galleryPreviewItems"
                                                                :key="image.id"
                                                                class="group relative aspect-[4/3] overflow-hidden border"
                                                                :style="previewItemStyle"
                                                            >
                                                                <img
                                                                    :src="image.url"
                                                                    alt="Preview gambar galeri"
                                                                    class="h-full w-full object-cover"
                                                                >
                                                                <span
                                                                    class="absolute right-2 top-2 rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                                                    :style="{
                                                                        color: previewPalette.text,
                                                                        backgroundColor: `${previewPalette.panel}CC`,
                                                                        borderColor: `${previewPalette.border}DD`,
                                                                    }"
                                                                >
                                                                    {{ image.source === 'new' ? 'Baru' : 'Tersimpan' }}
                                                                </span>
                                                            </article>
                                                        </div>
                                                    </div>
                                                </section>

                                                <section class="space-y-3 px-4 pb-6 pt-4">
                                                    <h4 class="text-[1.35rem] font-bold leading-tight" :style="previewHeadingTextStyle">
                                                        Form Booking
                                                    </h4>
                                                    <div class="space-y-3 border p-3.5" :style="previewBookingFormCardStyle">
                                                        <div class="space-y-1.5">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                                Nama Pelanggan
                                                            </p>
                                                            <input
                                                                type="text"
                                                                readonly
                                                                placeholder="Contoh: Budi Santoso"
                                                                class="h-10 w-full border px-3 text-sm outline-none placeholder:text-slate-400"
                                                                :style="previewBookingFieldStyle"
                                                            >
                                                        </div>

                                                        <div class="space-y-1.5">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                                Nomor WhatsApp
                                                            </p>
                                                            <input
                                                                type="text"
                                                                readonly
                                                                placeholder="08xxxxxxxxxx"
                                                                class="h-10 w-full border px-3 text-sm outline-none placeholder:text-slate-400"
                                                                :style="previewBookingFieldStyle"
                                                            >
                                                        </div>

                                                        <div class="space-y-1.5">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                                Nomor Polisi
                                                            </p>
                                                            <input
                                                                type="text"
                                                                readonly
                                                                placeholder="L 1234 AB"
                                                                class="h-10 w-full border px-3 text-sm uppercase outline-none placeholder:text-slate-400"
                                                                :style="previewBookingFieldStyle"
                                                            >
                                                        </div>

                                                        <div class="space-y-1.5">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                                Tanggal Booking
                                                            </p>
                                                            <input
                                                                type="text"
                                                                readonly
                                                                placeholder="Pilih tanggal servis"
                                                                class="h-10 w-full border px-3 text-sm outline-none placeholder:text-slate-400"
                                                                :style="previewBookingFieldStyle"
                                                            >
                                                        </div>

                                                        <div class="space-y-1.5">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                                Keluhan
                                                            </p>
                                                            <textarea
                                                                readonly
                                                                rows="3"
                                                                placeholder="Contoh: Mesin bergetar saat jalan."
                                                                class="w-full resize-none border px-3 py-2 text-sm outline-none placeholder:text-slate-400"
                                                                :style="previewBookingFieldStyle"
                                                            ></textarea>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            class="inline-flex h-10 w-full cursor-pointer items-center justify-center text-sm font-semibold shadow-sm"
                                                            :style="previewBookingButtonStyle"
                                                        >
                                                            Kirim Booking
                                                        </button>
                                                    </div>
                                                </section>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <article
                                v-else
                                class="w-full max-w-[940px] overflow-hidden border shadow-xl"
                                :style="desktopPreviewStyle"
                            >
                                <div class="grid lg:grid-cols-[1.2fr_1fr]">
                                    <header class="px-8 pb-9 pt-10 text-white" :style="previewHeroStyle">
                                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/80">Booking Online</p>
                                        <h3 class="mt-5 text-4xl font-bold leading-tight">{{ automaticHeadline }}</h3>
                                        <p class="mt-4 max-w-lg text-base text-white/90">{{ builderForm.subheadline }}</p>
                                        <button
                                            type="button"
                                            class="mt-8 inline-flex cursor-pointer items-center font-semibold shadow-sm"
                                            :class="previewDesktopCtaSizeClass"
                                            :style="previewCtaStyle"
                                        >
                                            {{ builderForm.cta_label }}
                                        </button>
                                    </header>

                                    <section class="space-y-3 border-l px-5 py-6" :style="{ borderColor: previewPalette.border }">
                                        <div v-if="hasGalleryPreview" class="space-y-3">
                                            <h4 class="text-sm font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                Galeri
                                            </h4>

                                            <div class="grid grid-cols-2 gap-3">
                                                <article
                                                    v-for="image in galleryPreviewItems"
                                                    :key="image.id"
                                                    class="group relative aspect-[4/3] overflow-hidden border"
                                                    :style="previewItemStyle"
                                                >
                                                    <img
                                                        :src="image.url"
                                                        alt="Preview gambar galeri"
                                                        class="h-full w-full object-cover"
                                                    >
                                                    <span
                                                        class="absolute right-2 top-2 rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                                        :style="{
                                                            color: previewPalette.text,
                                                            backgroundColor: `${previewPalette.panel}CC`,
                                                            borderColor: `${previewPalette.border}DD`,
                                                        }"
                                                    >
                                                        {{ image.source === 'new' ? 'Baru' : 'Tersimpan' }}
                                                    </span>
                                                </article>
                                            </div>
                                        </div>

                                        <div
                                            class="space-y-3 pt-4"
                                            :class="hasGalleryPreview ? 'border-t' : ''"
                                            :style="hasGalleryPreview ? { borderColor: previewPalette.border } : undefined"
                                        >
                                            <h4 class="text-sm font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                Form Booking
                                            </h4>

                                            <div class="space-y-3 border p-3" :style="previewBookingFormCardStyle">
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div class="space-y-1.5">
                                                        <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                            Nama
                                                        </p>
                                                        <input
                                                            type="text"
                                                            readonly
                                                            placeholder="Nama pelanggan"
                                                            class="h-9 w-full border px-3 text-xs outline-none placeholder:text-slate-400"
                                                            :style="previewBookingFieldStyle"
                                                        >
                                                    </div>
                                                    <div class="space-y-1.5">
                                                        <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                            WhatsApp
                                                        </p>
                                                        <input
                                                            type="text"
                                                            readonly
                                                            placeholder="08xxxxxxxxxx"
                                                            class="h-9 w-full border px-3 text-xs outline-none placeholder:text-slate-400"
                                                            :style="previewBookingFieldStyle"
                                                        >
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-2">
                                                    <div class="space-y-1.5">
                                                        <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                            No. Polisi
                                                        </p>
                                                        <input
                                                            type="text"
                                                            readonly
                                                            placeholder="L 1234 AB"
                                                            class="h-9 w-full border px-3 text-xs uppercase outline-none placeholder:text-slate-400"
                                                            :style="previewBookingFieldStyle"
                                                        >
                                                    </div>
                                                    <div class="space-y-1.5">
                                                        <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                            Tanggal
                                                        </p>
                                                        <input
                                                            type="text"
                                                            readonly
                                                            placeholder="Pilih tanggal"
                                                            class="h-9 w-full border px-3 text-xs outline-none placeholder:text-slate-400"
                                                            :style="previewBookingFieldStyle"
                                                        >
                                                    </div>
                                                </div>

                                                <div class="space-y-1.5">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide" :style="previewMutedTextStyle">
                                                        Keluhan
                                                    </p>
                                                    <textarea
                                                        readonly
                                                        rows="3"
                                                        placeholder="Tulis keluhan kendaraan..."
                                                        class="w-full resize-none border px-3 py-2 text-xs outline-none placeholder:text-slate-400"
                                                        :style="previewBookingFieldStyle"
                                                    ></textarea>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 w-full cursor-pointer items-center justify-center text-xs font-semibold shadow-sm"
                                                    :style="previewBookingButtonStyle"
                                                >
                                                    Kirim Booking
                                                </button>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </article>
                        </div>
                    </div>

                    <aside class="booking-builder-sidebar w-full bg-white dark:bg-slate-900/60">
                        <div class="builder-settings-panel px-4 py-5" @wheel.stop @touchmove.stop>
                            <div class="builder-settings-stack">
                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <path d="M4 7.5C4 6.67 4.67 6 5.5 6H18.5C19.33 6 20 6.67 20 7.5V16.5C20 17.33 19.33 18 18.5 18H5.5C4.67 18 4 17.33 4 16.5V7.5Z" stroke="currentColor" stroke-width="1.8" />
                                    <path d="M9 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <path d="M9 14H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                                Mode Tampilan
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="mode in modeOptions"
                                    :key="`mode-${mode.value}`"
                                    type="button"
                                    class="h-10 rounded-lg border text-sm font-semibold transition"
                                    :class="builderForm.mode === mode.value
                                        ? 'border-transparent bg-emerald-500 text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-700'"
                                    @click="builderForm.mode = mode.value"
                                >
                                    {{ mode.label }}
                                </button>
                            </div>
                            <p v-if="builderForm.errors.mode" class="text-sm text-rose-600">
                                {{ builderForm.errors.mode }}
                            </p>
                            </section>

                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <path d="M4 6.5C4 5.67 4.67 5 5.5 5H18.5C19.33 5 20 5.67 20 6.5V17.5C20 18.33 19.33 19 18.5 19H5.5C4.67 19 4 18.33 4 17.5V6.5Z" stroke="currentColor" stroke-width="1.8" />
                                    <path d="M8 9H16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <path d="M8 13H16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                                Konten Teks
                            </h3>
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-builder-subheadline">
                                        Subjudul
                                        <span class="ml-1 text-rose-500">*</span>
                                    </label>
                                    <textarea
                                        id="owner-booking-builder-subheadline"
                                        v-model="builderForm.subheadline"
                                        rows="3"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                                        placeholder="Contoh: Atur jadwal servis bengkel Anda tanpa antre panjang."
                                    />
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Jelaskan manfaat booking dalam 1 kalimat singkat.
                                    </p>
                                    <p v-if="builderForm.errors.subheadline" class="text-sm text-rose-600">
                                        {{ builderForm.errors.subheadline }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <InputField
                                        id="owner-booking-builder-cta-label"
                                        v-model="builderForm.cta_label"
                                        label="Label Tombol CTA"
                                        placeholder="Contoh: Booking Sekarang"
                                        :error="builderForm.errors.cta_label"
                                        required
                                    />
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Teks pada tombol ajakan aksi di hero.
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        Ukuran Tombol CTA
                                        <span class="ml-1 text-rose-500">*</span>
                                    </p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button
                                            v-for="ctaSize in ctaSizeOptions"
                                            :key="`cta-size-${ctaSize.value}`"
                                            type="button"
                                            class="h-10 rounded-lg border text-sm font-semibold transition"
                                            :class="builderForm.cta_size === ctaSize.value
                                                ? 'border-transparent bg-emerald-500 text-white'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-700'"
                                            @click="builderForm.cta_size = ctaSize.value"
                                        >
                                            {{ ctaSize.label }}
                                        </button>
                                    </div>
                                    <p v-if="builderForm.errors.cta_size" class="text-sm text-rose-600">
                                        {{ builderForm.errors.cta_size }}
                                    </p>
                                </div>

                            </div>
                            </section>

                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <path d="M4.5 7.5C4.5 6.67 5.17 6 6 6H18C18.83 6 19.5 6.67 19.5 7.5V16.5C19.5 17.33 18.83 18 18 18H6C5.17 18 4.5 17.33 4.5 16.5V7.5Z" stroke="currentColor" stroke-width="1.8" />
                                    <circle cx="9" cy="10" r="1.2" fill="currentColor" />
                                    <path d="M6.8 15L10.4 11.4C10.68 11.12 11.12 11.12 11.4 11.4L13.2 13.2L15.5 10.9C15.78 10.62 16.22 10.62 16.5 10.9L18 12.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Galeri
                            </h3>
                            <FileUpload
                                id="owner-booking-builder-gallery-images"
                                :model-value="builderForm.gallery_images"
                                accept="image/jpeg,image/png,image/webp"
                                :multiple="true"
                                :max-size-mb="10"
                                :disabled="builderForm.processing || hasReachedGalleryLimit"
                                placeholder="Pilih gambar galeri"
                                helper-text="Format: JPG, PNG, WEBP. Maks 10 MB per gambar."
                                @update:model-value="handleGalleryImagesUpdate"
                                @invalid="handleGalleryUploadInvalid"
                            />

                            <p
                                class="text-xs"
                                :class="galleryCardError !== '' ? 'text-rose-600' : 'text-slate-500 dark:text-slate-400'"
                            >
                                {{ galleryCardHint }}
                            </p>

                            <div v-if="galleryExistingUrls.length > 0" class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    Gambar Tersimpan
                                </p>
                                <div class="grid grid-cols-3 gap-2">
                                    <div
                                        v-for="(imageUrl, imageIndex) in galleryExistingUrls"
                                        :key="`existing-gallery-${imageIndex}`"
                                        class="group relative overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700"
                                    >
                                        <img
                                            :src="imageUrl"
                                            alt="Gambar galeri tersimpan"
                                            class="h-20 w-full object-cover"
                                        >
                                        <button
                                            type="button"
                                            class="absolute right-1.5 top-1.5 inline-flex h-6 w-6 items-center justify-center rounded-full border border-white/80 bg-black/55 text-xs font-bold text-white transition hover:bg-rose-500"
                                            @click="removeExistingGalleryImage(imageIndex)"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </section>

                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8" />
                                    <path d="M11 7.5H13V12.6L16 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Warna
                            </h3>
                            <div class="space-y-3">
                                <button
                                    v-for="color in colorOptions"
                                    :key="`color-${color.value}`"
                                    type="button"
                                    class="relative block h-12 w-full overflow-hidden rounded-xl border-2 p-1 shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 dark:focus-visible:ring-slate-600"
                                    :class="builderForm.primary_color === color.value
                                        ? 'border-slate-900 dark:border-emerald-400'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'"
                                    :title="color.label"
                                    :aria-label="`Pilih warna ${color.label}`"
                                    @click="builderForm.primary_color = color.value"
                                >
                                    <span class="block h-full w-full rounded-lg" :style="{ backgroundColor: color.value }" />
                                </button>
                            </div>
                            <p v-if="builderForm.errors.primary_color" class="text-sm text-rose-600">{{ builderForm.errors.primary_color }}</p>
                            </section>

                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <path d="M6.7 19.2L12 4.8L17.3 19.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M8.6 14.1H15.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                                Font
                            </h3>
                            <div class="space-y-2">
                                <button
                                    v-for="font in fontOptions"
                                    :key="`font-${font.value}`"
                                    type="button"
                                    class="flex h-12 w-full items-center justify-between rounded-xl border px-3 text-left transition"
                                    :class="builderForm.font_preset === font.value
                                        ? 'border-emerald-400 bg-emerald-100 dark:bg-emerald-500/15'
                                        : 'border-transparent bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700'"
                                    @click="builderForm.font_preset = font.value"
                                >
                                    <span class="text-[1rem] font-semibold text-slate-800 dark:text-slate-100">{{ font.label }}</span>
                                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">Aa</span>
                                </button>
                            </div>
                            <p v-if="builderForm.errors.font_preset" class="text-sm text-rose-600">{{ builderForm.errors.font_preset }}</p>
                            </section>

                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <path d="M5 9V7C5 5.9 5.9 5 7 5H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <path d="M15 5H17C18.1 5 19 5.9 19 7V9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <path d="M19 15V17C19 18.1 18.1 19 17 19H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <path d="M9 19H7C5.9 19 5 18.1 5 17V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    <rect x="8" y="8" width="8" height="8" rx="2.5" stroke="currentColor" stroke-width="1.8" />
                                </svg>
                                Border Radius
                            </h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="radius in radiusOptions"
                                    :key="`radius-${radius.value}`"
                                    type="button"
                                    class="h-9 rounded-full border text-xs font-semibold transition"
                                    :class="builderForm.radius_preset === radius.value
                                        ? 'border-transparent bg-emerald-500 text-white'
                                        : 'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'"
                                    @click="builderForm.radius_preset = radius.value"
                                >
                                    {{ radius.label }}
                                </button>
                            </div>
                            <p v-if="builderForm.errors.radius_preset" class="text-sm text-rose-600">{{ builderForm.errors.radius_preset }}</p>
                            <p v-if="builderForm.errors.is_active" class="text-sm text-rose-600">{{ builderForm.errors.is_active }}</p>
                            </section>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>
        </div>
    </AppDashboardLayout>
</template>

<style scoped>
.booking-builder-body {
    min-height: 700px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 420px;
}

.builder-settings-panel {
    max-height: 700px;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y;
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.9) transparent;
}

.builder-settings-panel::-webkit-scrollbar {
    width: 6px;
}

.builder-settings-panel::-webkit-scrollbar-track {
    background: transparent;
}

.builder-settings-panel::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.9);
}

:global(.dark) .builder-settings-panel {
    scrollbar-color: rgba(71, 85, 105, 0.95) transparent;
}

:global(.dark) .builder-settings-panel::-webkit-scrollbar-thumb {
    background: rgba(71, 85, 105, 0.95);
}

.builder-settings-stack {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.booking-builder-body :deep(button:not(:disabled)) {
    cursor: pointer;
}

.booking-builder-body :deep(button:disabled) {
    cursor: not-allowed;
}

.iphone-stage {
    width: 100%;
    display: flex;
    justify-content: center;
}

.iphone-shell {
    position: relative;
    padding: 10px 20px;
}

.iphone-device {
    position: relative;
    width: 322px;
    border-radius: 50px;
    padding: 7px;
    background: linear-gradient(160deg, #0a142f 0%, #1d2744 48%, #0f172a 100%);
    box-shadow:
        0 26px 52px rgba(15, 23, 42, 0.32),
        0 7px 20px rgba(15, 23, 42, 0.35);
}

.iphone-device::after {
    content: '';
    position: absolute;
    inset: 5px;
    border-radius: 44px;
    border: 1px solid rgba(248, 250, 252, 0.24);
    pointer-events: none;
}

.iphone-screen {
    position: relative;
    height: 634px;
    border-radius: 42px;
    overflow: hidden;
}

.iphone-dynamic-island {
    position: absolute;
    top: 12px;
    left: 50%;
    z-index: 30;
    height: 32px;
    width: 126px;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: #05070f;
    box-shadow: inset 0 -1px 2px rgba(148, 163, 184, 0.32);
}

.iphone-scroll {
    height: 100%;
    overflow-y: auto;
    scroll-behavior: smooth;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y;
    scrollbar-width: thin;
    scrollbar-color: rgba(51, 65, 85, 0.82) transparent;
}

.iphone-scroll::-webkit-scrollbar {
    width: 6px;
}

.iphone-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.iphone-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(51, 65, 85, 0.82);
}

@media (max-width: 860px) {
    .builder-settings-panel {
        max-height: none;
    }

    .iphone-device {
        width: 300px;
    }

    .iphone-screen {
        height: 588px;
    }
}
</style>
