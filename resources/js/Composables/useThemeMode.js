import { computed, onMounted, ref } from 'vue';

const STORAGE_KEY = 'theme_mode';
const themeMode = ref('light');
let initialized = false;

const applyTheme = (mode) => {
    const nextMode = mode === 'dark' ? 'dark' : 'light';
    themeMode.value = nextMode;

    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle('dark', nextMode === 'dark');
    }

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY, nextMode);
    }
};

const initTheme = () => {
    if (initialized || typeof window === 'undefined') {
        return;
    }

    initialized = true;

    const storedTheme = window.localStorage.getItem(STORAGE_KEY);
    applyTheme(storedTheme === 'dark' ? 'dark' : 'light');
};

export const useThemeMode = () => {
    onMounted(() => {
        initTheme();
    });

    const isDark = computed(() => themeMode.value === 'dark');

    const toggleTheme = () => {
        applyTheme(isDark.value ? 'light' : 'dark');
    };

    return {
        isDark,
        toggleTheme,
        setTheme: applyTheme,
    };
};
