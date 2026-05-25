<script setup>
import InputField from '../../../Components/UI/InputField.vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    showPassword: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'toggle-password']);

const onUpdate = (value) => {
    emit('update:modelValue', value);
};

const togglePassword = () => {
    emit('toggle-password');
};
</script>

<template>
    <InputField
        id="password"
        :model-value="modelValue"
        label="Kata Sandi"
        name="password"
        :type="showPassword ? 'text' : 'password'"
        placeholder="********"
        autocomplete="current-password"
        :error="error"
        @update:model-value="onUpdate"
    >
        <template #label-action>
            <button
                type="button"
                class="cursor-pointer text-xs font-semibold text-emerald-600 transition hover:text-emerald-500 hover:underline dark:text-emerald-300 dark:hover:text-emerald-200"
            >
                Lupa kata sandi?
            </button>
        </template>

        <template #prefix>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                    d="M7 10V7.5C7 4.9 9 3 12 3C15 3 17 4.9 17 7.5V10"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <rect x="5" y="10" width="14" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5" />
            </svg>
        </template>

        <template #suffix>
            <button
                type="button"
                class="grid h-6 w-6 cursor-pointer place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                @click="togglePassword"
            >
                <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path
                        d="M2 12C4.4 8 7.8 6 12 6C16.2 6 19.6 8 22 12C19.6 16 16.2 18 12 18C7.8 18 4.4 16 2 12Z"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" />
                </svg>

                <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    <path
                        d="M10.6 10.9C10.2 11.3 10 11.9 10 12.5C10 13.9 11.1 15 12.5 15C13.1 15 13.7 14.8 14.1 14.4"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M17.6 17.6C15.9 18.5 14.2 19 12 19C7.8 19 4.4 17 2 13C3 11.3 4.2 10 5.6 9"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <path d="M22 12C20.9 13.9 19.6 15.3 18 16.4" stroke="currentColor" stroke-width="1.5" />
                    <path
                        d="M7.3 5.3C8.8 4.5 10.2 4 12 4C16.2 4 19.6 6 22 10"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
        </template>
    </InputField>
</template>
