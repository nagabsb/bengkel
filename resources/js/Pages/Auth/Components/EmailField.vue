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
});

const emit = defineEmits(['update:modelValue']);

const onUpdate = (value) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <InputField
        id="email"
        :model-value="modelValue"
        label="Alamat Email"
        name="email"
        type="email"
        placeholder="anda@contoh.com"
        autocomplete="email"
        :error="error"
        @update:model-value="onUpdate"
    >
        <template #prefix>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                    d="M4 7H20V17H4V7Z"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M4 8L12 13L20 8"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </template>
    </InputField>
</template>
