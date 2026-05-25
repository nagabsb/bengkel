<script setup>
defineProps({
    isDark: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['toggle']);
</script>

<template>
    <button
        type="button"
        class="grid h-10 w-10 cursor-pointer place-items-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-slate-700 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-slate-100"
        :aria-label="isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
        @click="$emit('toggle')"
    >
        <svg v-if="isDark" class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 1 0 9.8 9.8Z"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
            <path d="M12 2V4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M12 20V22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M4.9 4.9L6.3 6.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M17.7 17.7L19.1 19.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M2 12H4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M20 12H22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M4.9 19.1L6.3 17.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M17.7 6.3L19.1 4.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
    </button>
</template>
