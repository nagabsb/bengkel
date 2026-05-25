<script setup>
import Card from '../../../Components/UI/Card.vue';
import LoginHeader from './LoginHeader.vue';
import LoginForm from './LoginForm.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    flashStatus: {
        type: String,
        default: '',
    },
    showPassword: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['submit', 'update:showPassword']);
</script>

<template>
    <Card aria-labelledby="login-title" class="ring-1 ring-slate-200/70 dark:ring-emerald-300/10">
        <LoginHeader />

        <p
            v-if="flashStatus"
            class="mb-5 rounded-xl border border-emerald-500/45 bg-emerald-100/80 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-300/45 dark:bg-emerald-400/15 dark:text-emerald-100"
        >
            {{ flashStatus }}
        </p>

        <LoginForm
            :form="form"
            :show-password="showPassword"
            @submit="$emit('submit')"
            @update:show-password="$emit('update:showPassword', $event)"
        />
    </Card>
</template>
