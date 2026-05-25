<script setup>
import { Head } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useLoginForm } from '../../Composables/useLoginForm';
import LoginCard from './Components/LoginCard.vue';

const { form, flashStatus, showPassword, submit } = useLoginForm();

const updateShowPassword = (value) => {
    showPassword.value = value;
};
</script>

<template>
    <Head title="Masuk" />

    <AuthLayout>
        <LoginCard
            :form="form"
            :flash-status="flashStatus"
            :show-password="showPassword"
            @submit="submit"
            @update:show-password="updateShowPassword"
        />
    </AuthLayout>
</template>
