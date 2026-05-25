<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { format } from 'date-fns';
import DatePicker from '../../Components/UI/DatePicker.vue';

const props = defineProps({
    isAvailable: {
        type: Boolean,
        default: false,
    },
    availabilityMessage: {
        type: String,
        default: '',
    },
    tenantId: {
        type: String,
        default: '',
    },
    tenantHint: {
        type: String,
        default: '',
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
            headline: 'Booking Servis Cepat & Mudah',
            subheadline: 'Atur jadwal servis bengkel Anda tanpa antre panjang.',
            cta_label: 'Booking Sekarang',
            cta_size: 'medium',
            gallery_images: [],
            is_active: true,
        }),
    },
    publicBooking: {
        type: Object,
        default: () => ({
            submit_path: '/booking',
            workshop_id: '',
            workshop_name: '',
        }),
    },
});

const page = usePage();
const bookingDate = ref(null);
const form = useForm({
    tenant: String(props.tenantHint || ''),
    customer_name: '',
    customer_phone: '',
    vehicle_plate_number: '',
    booking_date: '',
    booking_time: '',
    complaint: '',
    notes: '',
});

watch(
    () => props.tenantHint,
    (nextValue) => {
        const normalizedNextValue = String(nextValue || '').trim();
        if (normalizedNextValue !== '' && String(form.tenant || '').trim() === '') {
            form.tenant = normalizedNextValue;
        }
    },
    { immediate: true },
);

watch(bookingDate, (nextDate) => {
    if (nextDate instanceof Date && !Number.isNaN(nextDate.getTime())) {
        form.booking_date = format(nextDate, 'yyyy-MM-dd');
        return;
    }

    form.booking_date = '';
});

const isDarkMode = computed(() => String(props.builderConfig?.mode || 'tech').trim().toLowerCase() === 'dark');
const headline = computed(() => String(props.tenantProfile?.name || props.builderConfig?.headline || 'Booking Servis Cepat & Mudah').trim());
const subheadline = computed(() => String(props.builderConfig?.subheadline || 'Atur jadwal servis bengkel Anda tanpa antre panjang.').trim());
const ctaLabel = computed(() => String(props.builderConfig?.cta_label || 'Booking Sekarang').trim());
const flashStatus = computed(() => String(page.props?.flash?.status || '').trim());
const formLevelError = computed(() => String(form.errors?.create_booking || page.props?.errors?.create_booking || '').trim());
const submitPath = computed(() => String(props.publicBooking?.submit_path || '/booking').trim() || '/booking');
const workshopName = computed(() => String(props.publicBooking?.workshop_name || '').trim());
const galleryImages = computed(() => (
    Array.isArray(props.builderConfig?.gallery_images)
        ? props.builderConfig.gallery_images
            .map((imageUrl) => String(imageUrl || '').trim())
            .filter((imageUrl) => imageUrl !== '')
        : []
));
const hasGallery = computed(() => galleryImages.value.length > 0);
const isImagePreviewOpen = ref(false);
const selectedGalleryImageIndex = ref(0);
const previousBodyOverflow = ref('');

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

const normalizeHexColor = (value, fallback = '#0F766E') => {
    const normalized = String(value || '').trim().toUpperCase();

    return /^#[A-F0-9]{6}$/.test(normalized) ? normalized : fallback;
};

const mixHexColor = (hexColor, targetHex = '#FFFFFF', ratio = 0.2) => {
    const safeHex = normalizeHexColor(hexColor, '#0F766E');
    const safeTargetHex = normalizeHexColor(targetHex, '#FFFFFF');
    const clampedRatio = Math.min(1, Math.max(0, Number(ratio) || 0));

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
        sourceChannel + ((targetChannel - sourceChannel) * clampedRatio),
    );
    const toHex = (channel) => channel.toString(16).padStart(2, '0').toUpperCase();

    return `#${toHex(mix(sourceChannels[0], targetChannels[0]))}${toHex(mix(sourceChannels[1], targetChannels[1]))}${toHex(mix(sourceChannels[2], targetChannels[2]))}`;
};

const resolvedPrimaryColor = computed(() => normalizeHexColor(props.builderConfig?.primary_color, '#0F766E'));
const primaryStrongColor = computed(() => mixHexColor(resolvedPrimaryColor.value, '#000000', 0.16));
const primarySoftColor = computed(() => mixHexColor(resolvedPrimaryColor.value, '#FFFFFF', 0.1));
const primaryButtonColor = computed(() => mixHexColor(resolvedPrimaryColor.value, '#000000', 0.06));

const palette = computed(() => {
    if (isDarkMode.value) {
        return {
            panel: '#0F172A',
            section: '#111827',
            card: '#1E293B',
            border: '#334155',
            text: '#F8FAFC',
            muted: '#94A3B8',
            fieldBackground: '#1E293B',
            pageBackground: 'linear-gradient(145deg, #020617 0%, #111827 45%, #1E293B 100%)',
        };
    }

    return {
        panel: '#FFFFFF',
        section: '#F8FAFC',
        card: '#FFFFFF',
        border: '#E2E8F0',
        text: '#0F172A',
        muted: '#64748B',
        fieldBackground: '#FFFFFF',
        pageBackground: 'linear-gradient(145deg, #E2E8F0 0%, #CBD5E1 50%, #94A3B8 100%)',
    };
});

const radiusValue = computed(() => radiusValueMap[String(props.builderConfig?.radius_preset || 'medium').trim()] || radiusValueMap.medium);
const fontFamily = computed(() => fontFamilyMap[String(props.builderConfig?.font_preset || 'modern').trim()] || fontFamilyMap.modern);

const pageBackgroundStyle = computed(() => ({
    background: palette.value.pageBackground,
}));

const panelStyle = computed(() => ({
    backgroundColor: palette.value.panel,
    borderColor: palette.value.border,
    color: palette.value.text,
    borderRadius: radiusValue.value,
    fontFamily: fontFamily.value,
}));

const heroStyle = computed(() => ({
    background: `linear-gradient(145deg, ${primaryStrongColor.value}, ${primarySoftColor.value})`,
}));

const cardStyle = computed(() => ({
    backgroundColor: palette.value.card,
    borderColor: palette.value.border,
    borderRadius: radiusValue.value,
}));

const formCardStyle = computed(() => ({
    backgroundColor: palette.value.section,
    borderColor: palette.value.border,
    borderRadius: radiusValue.value,
}));

const fieldStyle = computed(() => ({
    backgroundColor: palette.value.fieldBackground,
    borderColor: palette.value.border,
    color: palette.value.text,
    borderRadius: radiusValue.value,
}));

const ctaStyle = computed(() => ({
    backgroundColor: '#FFFFFF',
    color: '#0F172A',
    borderRadius: radiusValue.value,
}));

const submitButtonStyle = computed(() => ({
    backgroundColor: primaryButtonColor.value,
    color: '#FFFFFF',
    borderRadius: radiusValue.value,
}));

const ctaSizeClass = computed(() => ({
    small: 'min-w-[160px] px-4 py-2.5 text-base sm:min-w-0 sm:px-4 sm:py-2 sm:text-sm',
    medium: 'min-w-[190px] px-5 py-3 text-lg sm:min-w-0 sm:px-6 sm:py-3 sm:text-base',
    large: 'min-w-[220px] px-6 py-3.5 text-xl sm:min-w-0 sm:px-7 sm:py-3.5 sm:text-lg',
}[String(props.builderConfig?.cta_size || 'medium').trim()] || 'min-w-[190px] px-5 py-3 text-lg sm:min-w-0 sm:px-6 sm:py-3 sm:text-base'));

const mutedTextStyle = computed(() => ({
    color: palette.value.muted,
}));

const headingTextStyle = computed(() => ({
    color: palette.value.text,
}));

const selectedGalleryImage = computed(() => (
    galleryImages.value[selectedGalleryImageIndex.value] || ''
));

const selectedGalleryImageAlt = computed(() => (
    `Preview galeri bengkel ${selectedGalleryImageIndex.value + 1}`
));

const hasMultipleGalleryImages = computed(() => galleryImages.value.length > 1);

const scrollToForm = () => {
    if (typeof document === 'undefined') {
        return;
    }

    document.getElementById('public-booking-form')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
};

const submitBooking = () => {
    form.post(submitPath.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset('customer_name', 'customer_phone', 'vehicle_plate_number', 'booking_date', 'booking_time', 'complaint', 'notes');
            bookingDate.value = null;
        },
    });
};

const openGalleryPreview = (index) => {
    if (!hasGallery.value) {
        return;
    }

    const safeIndex = Math.min(
        Math.max(Number(index) || 0, 0),
        galleryImages.value.length - 1,
    );

    selectedGalleryImageIndex.value = safeIndex;
    isImagePreviewOpen.value = true;
};

const closeGalleryPreview = () => {
    isImagePreviewOpen.value = false;
};

const showPreviousGalleryImage = () => {
    if (!hasMultipleGalleryImages.value) {
        return;
    }

    selectedGalleryImageIndex.value = selectedGalleryImageIndex.value === 0
        ? galleryImages.value.length - 1
        : selectedGalleryImageIndex.value - 1;
};

const showNextGalleryImage = () => {
    if (!hasMultipleGalleryImages.value) {
        return;
    }

    selectedGalleryImageIndex.value = selectedGalleryImageIndex.value === galleryImages.value.length - 1
        ? 0
        : selectedGalleryImageIndex.value + 1;
};

const handlePreviewKeyboardNavigation = (event) => {
    if (!isImagePreviewOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        closeGalleryPreview();
        return;
    }

    if (event.key === 'ArrowLeft') {
        showPreviousGalleryImage();
        return;
    }

    if (event.key === 'ArrowRight') {
        showNextGalleryImage();
    }
};

watch(isImagePreviewOpen, (isOpen) => {
    if (typeof document === 'undefined') {
        return;
    }

    if (isOpen) {
        previousBodyOverflow.value = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return;
    }

    document.body.style.overflow = previousBodyOverflow.value;
});

watch(galleryImages, (nextImages) => {
    if (!Array.isArray(nextImages) || nextImages.length === 0) {
        closeGalleryPreview();
        selectedGalleryImageIndex.value = 0;
        return;
    }

    if (selectedGalleryImageIndex.value > nextImages.length - 1) {
        selectedGalleryImageIndex.value = nextImages.length - 1;
    }
});

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handlePreviewKeyboardNavigation);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handlePreviewKeyboardNavigation);
    }

    if (typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow.value;
    }
});
</script>

<template>
    <Head :title="`Booking Servis - ${headline}`" />

    <main class="min-h-screen" :class="isDarkMode ? 'dark' : ''" :style="pageBackgroundStyle">
        <div class="mx-auto w-full max-w-5xl space-y-5 px-4 py-8 sm:px-6">
            <section
                v-if="flashStatus"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                {{ flashStatus }}
            </section>

            <section
                v-if="formLevelError"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300"
            >
                {{ formLevelError }}
            </section>

            <section
                v-if="!isAvailable && availabilityMessage"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
            >
                {{ availabilityMessage }}
            </section>

            <article class="overflow-hidden border shadow-xl" :style="panelStyle">
                <div class="grid lg:grid-cols-[1.1fr_1fr]">
                    <header class="px-6 pb-9 pt-20 text-center text-white sm:px-8 sm:pb-8 sm:pt-10 sm:text-left" :style="heroStyle">
                        <div class="mx-auto mb-6 grid h-14 w-14 place-items-center rounded-2xl bg-white/20 text-white/90 sm:hidden">
                            <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7" aria-hidden="true">
                                <circle cx="9" cy="20" r="1.5" fill="currentColor" />
                                <circle cx="17" cy="20" r="1.5" fill="currentColor" />
                                <path d="M5 5H7L9.3 14.2C9.43 14.72 9.89 15.08 10.42 15.08H16.77C17.24 15.08 17.67 14.79 17.85 14.35L20 9H8.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/80">Booking Online</p>
                        <h1 class="mx-auto mt-5 max-w-[220px] text-[2rem] font-bold leading-[1.08] tracking-tight sm:mx-0 sm:max-w-none sm:text-4xl sm:leading-tight">
                            {{ headline }}
                        </h1>
                        <p class="mt-3 text-[1rem] text-white/90 sm:mt-4 sm:max-w-xl sm:text-base">{{ subheadline }}</p>
                        <p v-if="workshopName" class="mt-3 text-xs font-semibold uppercase tracking-wide text-white/80">
                            {{ workshopName }}
                        </p>
                        <button
                            type="button"
                            class="mt-7 inline-flex cursor-pointer items-center justify-center font-bold shadow-sm transition hover:opacity-90 sm:mt-8 sm:font-semibold"
                            :class="ctaSizeClass"
                            :style="ctaStyle"
                            @click="scrollToForm"
                        >
                            {{ ctaLabel }}
                        </button>
                    </header>

                    <section class="space-y-4 px-5 py-6 sm:px-6">
                        <section v-if="hasGallery" class="space-y-3">
                            <h2 class="flex items-center gap-3 text-[2rem] font-bold leading-tight sm:text-sm sm:font-semibold sm:uppercase sm:tracking-wide" :style="headingTextStyle">
                                <span class="inline-flex h-8 w-1.5 rounded-full sm:hidden" :style="{ backgroundColor: primaryStrongColor }" />
                                <span>Galeri</span>
                            </h2>
                            <div class="grid grid-cols-2 gap-3">
                                <article
                                    v-for="(imageUrl, imageIndex) in galleryImages"
                                    :key="`public-gallery-${imageIndex}`"
                                    class="relative aspect-[4/3] overflow-hidden border"
                                    :style="cardStyle"
                                >
                                    <button
                                        type="button"
                                        class="group block h-full w-full cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 dark:focus-visible:ring-slate-600"
                                        :aria-label="`Lihat preview gambar ${imageIndex + 1}`"
                                        @click="openGalleryPreview(imageIndex)"
                                    >
                                        <img
                                            :src="imageUrl"
                                            :alt="`Galeri bengkel ${imageIndex + 1}`"
                                            class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]"
                                        >
                                        <span
                                            class="pointer-events-none absolute bottom-2 left-2 rounded-md bg-slate-900/70 px-2 py-1 text-[11px] font-semibold text-white"
                                        >
                                            Klik untuk preview
                                        </span>
                                    </button>
                                </article>
                            </div>
                        </section>

                        <form
                            id="public-booking-form"
                            class="space-y-3 border p-4"
                            :class="hasGallery ? 'mt-5' : 'mt-3'"
                            :style="formCardStyle"
                            @submit.prevent="submitBooking"
                        >
                            <h2 class="text-[1.35rem] font-bold leading-tight sm:text-sm sm:font-semibold sm:uppercase sm:tracking-wide" :style="headingTextStyle">
                                Form Booking
                            </h2>

                            <input v-model="form.tenant" type="hidden" name="tenant">

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                    Nama Pelanggan <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.customer_name"
                                    type="text"
                                    class="h-10 w-full border px-3 text-sm outline-none placeholder:text-slate-400"
                                    placeholder="Contoh: Budi Santoso"
                                    :style="fieldStyle"
                                >
                                <p v-if="form.errors.customer_name" class="text-xs text-rose-600 dark:text-rose-300">
                                    {{ form.errors.customer_name }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                        Nomor WhatsApp
                                    </label>
                                    <input
                                        v-model="form.customer_phone"
                                        type="text"
                                        class="h-10 w-full border px-3 text-sm outline-none placeholder:text-slate-400"
                                        placeholder="08xxxxxxxxxx"
                                        :style="fieldStyle"
                                    >
                                    <p v-if="form.errors.customer_phone" class="text-xs text-rose-600 dark:text-rose-300">
                                        {{ form.errors.customer_phone }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                        Nomor Polisi
                                    </label>
                                    <input
                                        v-model="form.vehicle_plate_number"
                                        type="text"
                                        class="h-10 w-full border px-3 text-sm uppercase outline-none placeholder:text-slate-400"
                                        placeholder="L1234AB"
                                        :style="fieldStyle"
                                    >
                                    <p v-if="form.errors.vehicle_plate_number" class="text-xs text-rose-600 dark:text-rose-300">
                                        {{ form.errors.vehicle_plate_number }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                        Tanggal Booking <span class="text-rose-500">*</span>
                                    </label>
                                    <DatePicker
                                        v-model="bookingDate"
                                        appearance="field"
                                        placeholder="Pilih tanggal servis"
                                        :clearable="true"
                                        :teleport="false"
                                        :dark="isDarkMode"
                                    />
                                    <p v-if="form.errors.booking_date" class="text-xs text-rose-600 dark:text-rose-300">
                                        {{ form.errors.booking_date }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                        Jam Booking
                                    </label>
                                    <input
                                        v-model="form.booking_time"
                                        type="time"
                                        class="h-10 w-full border px-3 text-sm outline-none"
                                        :style="fieldStyle"
                                    >
                                    <p v-if="form.errors.booking_time" class="text-xs text-rose-600 dark:text-rose-300">
                                        {{ form.errors.booking_time }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                    Keluhan <span class="text-rose-500">*</span>
                                </label>
                                <textarea
                                    v-model="form.complaint"
                                    rows="3"
                                    class="w-full resize-none border px-3 py-2 text-sm outline-none placeholder:text-slate-400"
                                    placeholder="Contoh: Mesin bergetar saat jalan."
                                    :style="fieldStyle"
                                ></textarea>
                                <p v-if="form.errors.complaint" class="text-xs text-rose-600 dark:text-rose-300">
                                    {{ form.errors.complaint }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide" :style="mutedTextStyle">
                                    Catatan Tambahan
                                </label>
                                <textarea
                                    v-model="form.notes"
                                    rows="2"
                                    class="w-full resize-none border px-3 py-2 text-sm outline-none placeholder:text-slate-400"
                                    placeholder="Opsional"
                                    :style="fieldStyle"
                                ></textarea>
                                <p v-if="form.errors.notes" class="text-xs text-rose-600 dark:text-rose-300">
                                    {{ form.errors.notes }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex h-10 w-full cursor-pointer items-center justify-center text-sm font-semibold shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="form.processing"
                                :style="submitButtonStyle"
                            >
                                {{ form.processing ? 'Mengirim...' : 'Kirim Booking' }}
                            </button>
                        </form>
                    </section>
                </div>
            </article>
        </div>

        <div
            v-if="isImagePreviewOpen && selectedGalleryImage !== ''"
            class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto bg-slate-900/80 p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
            @click.self="closeGalleryPreview"
        >
            <div class="relative w-full max-w-5xl">
                <button
                    type="button"
                    class="absolute right-2 top-2 z-10 inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-white/30 bg-slate-950/70 text-white transition hover:bg-slate-900"
                    aria-label="Tutup preview gambar"
                    @click="closeGalleryPreview"
                >
                    ✕
                </button>

                <img
                    :src="selectedGalleryImage"
                    :alt="selectedGalleryImageAlt"
                    class="max-h-[82vh] w-full rounded-2xl border border-white/20 bg-black/30 object-contain"
                >

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs font-semibold text-slate-200">
                        Gambar {{ selectedGalleryImageIndex + 1 }} dari {{ galleryImages.length }}
                    </p>

                    <div v-if="hasMultipleGalleryImages" class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-white/30 bg-slate-950/70 px-3 text-sm font-semibold text-white transition hover:bg-slate-900"
                            @click="showPreviousGalleryImage"
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-white/30 bg-slate-950/70 px-3 text-sm font-semibold text-white transition hover:bg-slate-900"
                            @click="showNextGalleryImage"
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
