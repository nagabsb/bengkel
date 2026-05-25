<script setup>
defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    name: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const onInput = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <div class="grid min-w-0 gap-2">
        <div class="flex items-baseline justify-between gap-3">
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" :for="id">
                {{ label }}
                <span v-if="required" class="ml-1 text-rose-500">*</span>
            </label>
            <slot name="label-action" />
        </div>

        <div
            class="flex items-center gap-2.5 rounded-xl border px-3 transition"
            :class="error
                ? 'border-rose-400/80 bg-rose-50/40 dark:bg-slate-900/80'
                : 'border-slate-300/80 bg-white/80 focus-within:border-emerald-500/70 focus-within:ring-2 focus-within:ring-emerald-500/20 dark:border-blue-200/20 dark:bg-slate-900/80 dark:focus-within:border-emerald-300/70 dark:focus-within:ring-emerald-400/20'"
        >
            <span v-if="$slots.prefix" class="shrink-0 text-slate-500 dark:text-slate-400 [&>svg]:h-4 [&>svg]:w-4">
                <slot name="prefix" />
            </span>

            <input
                :id="id"
                :value="modelValue"
                :name="name || id"
                :type="type"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :readonly="readonly"
                :required="required"
                class="h-11 w-full cursor-text border-0 bg-transparent text-sm tracking-wide text-slate-900 caret-emerald-600 outline-none placeholder:text-slate-400 dark:text-slate-100 dark:caret-emerald-300 dark:placeholder:text-slate-500"
                @input="onInput"
            >

            <span v-if="$slots.suffix" class="shrink-0 text-slate-500 dark:text-slate-400 [&>svg]:h-4 [&>svg]:w-4">
                <slot name="suffix" />
            </span>
        </div>

        <p v-if="error" class="text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
    </div>
</template>
