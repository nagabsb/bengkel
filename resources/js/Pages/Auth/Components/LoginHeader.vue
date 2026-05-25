<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const DEFAULT_LOGO_BACKGROUND_COLOR = '#10B981';

const page = usePage();
const appName = computed(() => page.props.appName || 'AutoServ');
const appLogoUrl = computed(() => page.props.appLogoUrl || '');
const logoBackgroundEnabled = computed(() => Boolean(page.props.logoBackgroundEnabled ?? true));
const logoBackgroundColor = computed(() => {
    const normalized = String(page.props.logoBackgroundColor || DEFAULT_LOGO_BACKGROUND_COLOR).trim().toUpperCase();
    return /^#[A-F0-9]{6}$/.test(normalized) ? normalized : DEFAULT_LOGO_BACKGROUND_COLOR;
});

const appInitials = computed(() => {
    const words = String(appName.value || '')
        .split(' ')
        .map((word) => word.trim())
        .filter((word) => word !== '');

    if (words.length === 0) {
        return 'AS';
    }

    return words
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
});

const avatarTextColor = computed(() => {
    const normalized = logoBackgroundColor.value.replace('#', '');
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const luma = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return luma >= 148 ? '#0F172A' : '#FFFFFF';
});

const avatarStyle = computed(() => {
    if (!logoBackgroundEnabled.value) {
        return {};
    }

    return {
        backgroundColor: logoBackgroundColor.value,
        color: avatarTextColor.value,
    };
});
</script>

<template>
    <div class="mb-7">
        <div
            class="mx-auto mb-6 grid h-14 w-14 place-items-center overflow-hidden rounded-2xl text-emerald-600 transition-colors"
            :class="logoBackgroundEnabled ? 'border border-transparent shadow-lg shadow-emerald-200/70 dark:shadow-emerald-400/20' : 'border border-slate-300 bg-transparent text-slate-700 dark:border-slate-600 dark:text-slate-200'"
            :style="avatarStyle"
            aria-hidden="true"
        >
            <img
                v-if="appLogoUrl"
                :src="appLogoUrl"
                :alt="`Logo ${appName}`"
                class="h-full w-full rounded-2xl object-contain p-1"
            >
            <span v-else class="text-lg font-bold tracking-wide">{{ appInitials }}</span>
        </div>

        <header class="space-y-2 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600 dark:text-emerald-300">{{ appName }}</p>
            <h1 id="login-title" class="font-['Sora'] text-4xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                Selamat Datang
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Masuk ke akun Anda untuk melanjutkan</p>
        </header>
    </div>
</template>
