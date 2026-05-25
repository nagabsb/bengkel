<script setup>
import PrimaryButton from '../../../Components/UI/PrimaryButton.vue';
import CheckboxField from '../../../Components/UI/CheckboxField.vue';
import EmailField from './EmailField.vue';
import PasswordField from './PasswordField.vue';
import SocialLoginButton from './SocialLoginButton.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    showPassword: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit', 'update:showPassword']);
</script>

<template>
    <form class="grid gap-4" @submit.prevent="emit('submit')">
        <EmailField v-model="form.email" :error="form.errors.email" />

        <PasswordField
            v-model="form.password"
            :error="form.errors.password"
            :show-password="showPassword"
            @toggle-password="emit('update:showPassword', !showPassword)"
        />

        <CheckboxField
            id="remember"
            v-model="form.remember"
            name="remember"
            label="Ingat saya selama 30 hari"
        />

        <PrimaryButton :disabled="form.processing">
            {{ form.processing ? 'Memproses...' : 'Masuk' }}
        </PrimaryButton>
    </form>

    <div class="my-5 flex items-center gap-3 text-xs font-semibold tracking-widest text-slate-500 dark:text-slate-500">
        <span class="h-px flex-1 bg-gradient-to-r from-transparent to-slate-300 dark:to-slate-600/70" />
        <span>ATAU LANJUTKAN DENGAN</span>
        <span class="h-px flex-1 rotate-180 bg-gradient-to-r from-transparent to-slate-300 dark:to-slate-600/70" />
    </div>

    <SocialLoginButton />
</template>

