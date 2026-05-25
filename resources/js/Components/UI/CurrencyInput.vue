<script setup>
import { computed } from 'vue';
const props = defineProps({
    id: {
        type: String,
        default: '',
    },
    modelValue: {
        type: [Number, String, null],
        default: null,
    },
    placeholder: {
        type: String,
        default: '0',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    inputClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const formatNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
};

const normalizeToNumericString = (value) => String(value ?? '').replace(/\D/g, '');

const displayValue = computed(() => formatNumber(props.modelValue));

const onInput = (event) => {
    const rawValue = normalizeToNumericString(event?.target?.value || '');

    if (rawValue === '') {
        emit('update:modelValue', null);
        return;
    }

    emit('update:modelValue', Number(rawValue));
};

const handleKeydown = (event) => {
    if (!(event instanceof KeyboardEvent)) {
        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const allowedControlKeys = [
        'Backspace',
        'Delete',
        'ArrowLeft',
        'ArrowRight',
        'ArrowUp',
        'ArrowDown',
        'Tab',
        'Home',
        'End',
        'Enter',
    ];

    if (allowedControlKeys.includes(event.key)) {
        return;
    }

    if (!/^\d$/.test(event.key)) {
        event.preventDefault();
    }
};

const handlePaste = (event) => {
    if (!(event instanceof ClipboardEvent)) {
        return;
    }

    event.preventDefault();
    const pastedText = event.clipboardData?.getData('text') || '';
    const rawValue = normalizeToNumericString(pastedText);

    if (rawValue === '') {
        emit('update:modelValue', null);
        return;
    }

    emit('update:modelValue', Number(rawValue));
};
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500 dark:text-slate-400">
            Rp
        </span>
        <input
            :id="id || undefined"
            :value="displayValue"
            type="text"
            inputmode="numeric"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
            :class="inputClass"
            @input="onInput"
            @keydown="handleKeydown"
            @paste="handlePaste"
        >
    </div>
</template>



