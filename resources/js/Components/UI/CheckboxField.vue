<script setup>
defineProps({
    id: {
        type: String,
        required: true,
    },
    modelValue: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        required: true,
    },
    name: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const onChange = (event) => {
    emit('update:modelValue', event.target.checked);
};
</script>

<template>
    <label
        :for="id"
        class="group inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600 dark:text-slate-300"
    >
        <input
            :id="id"
            :name="name || id"
            :checked="modelValue"
            type="checkbox"
            class="peer sr-only"
            @change="onChange"
        >

        <span
            class="relative grid h-5 w-5 shrink-0 place-items-center rounded-md border border-slate-300 bg-white transition group-hover:border-emerald-400 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500/35 peer-checked:border-emerald-500 peer-checked:bg-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:group-hover:border-emerald-400 dark:peer-focus-visible:ring-emerald-400/35 dark:peer-checked:border-emerald-400 dark:peer-checked:bg-emerald-400"
            aria-hidden="true"
        >
            <svg
                class="h-3 w-3 text-white opacity-0 transition peer-checked:opacity-100 dark:text-slate-950"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M5 12L10 17L19 8"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </span>

        <span class="transition group-hover:text-slate-800 dark:group-hover:text-slate-100">{{ label }}</span>
    </label>
</template>
