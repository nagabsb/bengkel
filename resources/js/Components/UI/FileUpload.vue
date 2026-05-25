<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    id: {
        type: String,
        default: '',
    },
    name: {
        type: String,
        default: '',
    },
    modelValue: {
        type: [Object, Array, String, null],
        default: null,
    },
    accept: {
        type: String,
        default: '',
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    maxSizeMb: {
        type: Number,
        default: 10,
    },
    placeholder: {
        type: String,
        default: 'Pilih file',
    },
    helperText: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'invalid']);

const inputRef = ref(null);

const normalizedFiles = computed(() => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue
            .map((fileItem) => normalizeFileItem(fileItem))
            .filter((fileItem) => fileItem !== null);
    }

    const singleFile = normalizeFileItem(props.modelValue);
    return singleFile ? [singleFile] : [];
});

const triggerLabel = computed(() => {
    if (normalizedFiles.value.length === 0) {
        return props.placeholder;
    }

    return props.multiple ? 'Tambah file' : 'Ganti file';
});

const openFilePicker = () => {
    if (props.disabled) {
        return;
    }

    inputRef.value?.click();
};

const resetInput = () => {
    if (!inputRef.value) {
        return;
    }

    inputRef.value.value = '';
};

const onFileChange = (event) => {
    const isFileSupported = typeof File !== 'undefined';
    const pickedFiles = Array.from(event?.target?.files || [])
        .filter((file) => isFileSupported && file instanceof File);

    if (pickedFiles.length === 0) {
        return;
    }

    const maxSizeInBytes = Math.max(Number(props.maxSizeMb) || 0, 0) * 1024 * 1024;
    const validFiles = [];
    const invalidFiles = [];

    pickedFiles.forEach((file) => {
        if (maxSizeInBytes > 0 && file.size > maxSizeInBytes) {
            invalidFiles.push(file);
            return;
        }

        validFiles.push(file);
    });

    if (invalidFiles.length > 0) {
        emit('invalid', {
            files: invalidFiles,
            message: `Ukuran file maksimal ${Math.max(Number(props.maxSizeMb) || 0, 0)} MB.`,
        });
    }

    if (validFiles.length === 0) {
        resetInput();
        return;
    }

    if (props.multiple) {
        emit('update:modelValue', validFiles);
    } else {
        emit('update:modelValue', validFiles[0] || null);
    }

    resetInput();
};

const removeFile = (index) => {
    if (props.disabled) {
        return;
    }

    if (!props.multiple) {
        emit('update:modelValue', null);
        resetInput();
        return;
    }

    const nextFiles = normalizedFiles.value
        .filter((_, fileIndex) => fileIndex !== index)
        .map((fileItem) => fileItem.raw)
        .filter((fileItem) => fileItem !== null);

    emit('update:modelValue', nextFiles);
    resetInput();
};

const formatFileSize = (size) => {
    if (!Number.isFinite(size) || size <= 0) {
        return '-';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let nextSize = size;
    let unitIndex = 0;

    while (nextSize >= 1024 && unitIndex < units.length - 1) {
        nextSize /= 1024;
        unitIndex += 1;
    }

    const formattedSize = unitIndex === 0 ? String(Math.round(nextSize)) : nextSize.toFixed(1);

    return `${formattedSize} ${units[unitIndex]}`;
};

function normalizeFileItem(fileItem) {
    const isFileSupported = typeof File !== 'undefined';
    if (isFileSupported && fileItem instanceof File) {
        return {
            raw: fileItem,
            name: String(fileItem.name || 'file'),
            size: Number(fileItem.size || 0),
            isRemote: false,
        };
    }

    if (typeof fileItem === 'string' && fileItem.trim() !== '') {
        const normalizedValue = fileItem.trim();
        const chunks = normalizedValue.split('/');
        const inferredName = chunks[chunks.length - 1] || normalizedValue;

        return {
            raw: normalizedValue,
            name: inferredName,
            size: null,
            isRemote: true,
        };
    }

    return null;
}
</script>

<template>
    <div class="space-y-2">
        <input
            :id="id || undefined"
            ref="inputRef"
            :name="name || id || undefined"
            type="file"
            class="hidden"
            :accept="accept || undefined"
            :multiple="multiple"
            :disabled="disabled"
            @change="onFileChange"
        >

        <button
            type="button"
            class="inline-flex h-11 w-full cursor-pointer items-center justify-between rounded-xl border border-slate-300/80 bg-white/80 px-3 text-left text-sm text-slate-700 outline-none transition hover:border-emerald-300 focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:focus-visible:ring-emerald-400/30"
            :disabled="disabled"
            @click="openFilePicker"
        >
            <span class="truncate">{{ triggerLabel }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">
                {{ maxSizeMb }} MB
            </span>
        </button>

        <p
            v-if="helperText"
            class="text-xs text-slate-500 dark:text-slate-400"
        >
            {{ helperText }}
        </p>

        <ul
            v-if="normalizedFiles.length > 0"
            class="space-y-1.5"
        >
            <li
                v-for="(fileItem, fileIndex) in normalizedFiles"
                :key="`file-upload-item-${fileIndex}-${fileItem.name}`"
                class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/70"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-700 dark:text-slate-200">
                        {{ fileItem.name }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ fileItem.isRemote ? 'File tersimpan' : formatFileSize(fileItem.size) }}
                    </p>
                </div>

                <button
                    type="button"
                    class="shrink-0 rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 transition hover:border-rose-300 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-rose-400/40 dark:hover:text-rose-300"
                    :disabled="disabled"
                    @click="removeFile(fileIndex)"
                >
                    Hapus
                </button>
            </li>
        </ul>
    </div>
</template>
