<script setup>
import ThemeToggle from '../Components/UI/ThemeToggle.vue';
import { useThemeMode } from '../Composables/useThemeMode';

const { isDark, toggleTheme } = useThemeMode();
</script>

<template>
    <main
        class="relative grid min-h-dvh place-items-center overflow-hidden bg-gradient-to-br from-slate-100 via-white to-cyan-50 px-4 py-8 transition-colors dark:from-blue-950 dark:via-slate-950 dark:to-black sm:py-10"
    >
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-16 top-1/4 h-72 w-72 rounded-full bg-cyan-300/45 blur-3xl dark:bg-cyan-400/25" />
            <div class="absolute -right-20 bottom-12 h-80 w-80 rounded-full bg-blue-300/35 blur-3xl dark:bg-blue-500/20" />
        </div>

        <div class="absolute right-4 top-4 z-20 sm:right-6 sm:top-6">
            <ThemeToggle :is-dark="isDark" @toggle="toggleTheme" />
        </div>

        <div class="relative z-10 w-full max-w-md">
            <slot />
        </div>
    </main>
</template>
