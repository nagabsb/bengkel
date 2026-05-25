<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    width: {
        type: String,
        default: '100%',
    },
    height: {
        type: String,
        default: '220px',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    penColor: {
        type: String,
        default: '#0f172a',
    },
    saveLabel: {
        type: String,
        default: 'Simpan',
    },
    clearLabel: {
        type: String,
        default: 'Hapus',
    },
    showSaveButton: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'signed', 'error']);

const signaturePadRef = ref(null);

const signaturePadOptions = computed(() => ({
    penColor: props.penColor,
}));

const getSignatureData = ({
    requireFilled = false,
    syncModelValue = true,
    emitErrors = true,
} = {}) => {
    if (!signaturePadRef.value || typeof signaturePadRef.value.saveSignature !== 'function') {
        if (emitErrors) {
            emit('error', 'Signature pad belum siap.');
        }

        return '';
    }

    const { isEmpty, data } = signaturePadRef.value.saveSignature();
    const normalizedData = typeof data === 'string' ? data.trim() : '';
    if (isEmpty || normalizedData === '') {
        if (syncModelValue) {
            emit('update:modelValue', '');
        }

        if (requireFilled && emitErrors) {
            emit('error', 'Tanda tangan tidak boleh kosong.');
        }

        return '';
    }

    if (syncModelValue) {
        emit('update:modelValue', normalizedData);
    }

    return normalizedData;
};

const clearSignature = () => {
    if (!signaturePadRef.value || typeof signaturePadRef.value.clearSignature !== 'function') {
        return;
    }

    signaturePadRef.value.clearSignature();
    emit('update:modelValue', '');
};

const saveSignature = () => {
    const data = getSignatureData({
        requireFilled: true,
        syncModelValue: true,
        emitErrors: true,
    });
    if (data === '') {
        return;
    }

    emit('signed', data);
};

const syncModelValueToCanvas = async (dataUrl) => {
    if (!signaturePadRef.value || typeof signaturePadRef.value.fromDataURL !== 'function') {
        return;
    }

    const normalizedDataUrl = String(dataUrl || '').trim();

    await nextTick();

    if (normalizedDataUrl === '') {
        signaturePadRef.value.clearSignature?.();
        return;
    }

    signaturePadRef.value.fromDataURL(normalizedDataUrl);
};

watch(
    () => props.modelValue,
    (nextValue) => {
        syncModelValueToCanvas(nextValue);
    },
    { immediate: true },
);

watch(
    () => props.disabled,
    (disabled) => {
        if (!signaturePadRef.value) {
            return;
        }

        if (disabled && typeof signaturePadRef.value.lockSignaturePad === 'function') {
            signaturePadRef.value.lockSignaturePad();
            return;
        }

        if (!disabled && typeof signaturePadRef.value.openSignaturePad === 'function') {
            signaturePadRef.value.openSignaturePad();
        }
    },
    { immediate: true },
);

defineExpose({
    clearSignature,
    saveSignature,
    getSignatureData,
});
</script>

<template>
    <div class="space-y-2">
        <div class="overflow-hidden rounded-xl border border-slate-300/80 bg-white/80 dark:border-blue-200/20 dark:bg-slate-900/80">
            <VueSignaturePad
                ref="signaturePadRef"
                :width="width"
                :height="height"
                :options="signaturePadOptions"
            />
        </div>

        <div class="flex items-center justify-end gap-2">
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                :disabled="disabled"
                @click="clearSignature"
            >
                {{ clearLabel }}
            </button>
            <button
                v-if="showSaveButton"
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition hover:border-emerald-400 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                :disabled="disabled"
                @click="saveSignature"
            >
                {{ saveLabel }}
            </button>
        </div>
    </div>
</template>

<style scoped>
:deep(canvas) {
    width: 100% !important;
}
</style>
