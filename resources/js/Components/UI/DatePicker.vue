<script setup>
import { computed } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

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
        type: [Date, String, Object, null],
        default: null,
    },
    placeholder: {
        type: String,
        default: 'Pilih tanggal',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    hideInputIcon: {
        type: Boolean,
        default: false,
    },
    teleport: {
        type: Boolean,
        default: true,
    },
    appearance: {
        type: String,
        default: 'default',
    },
    format: {
        type: [String, Function],
        default: 'dd/MM/yyyy',
    },
    enableTimePicker: {
        type: Boolean,
        default: false,
    },
    timePicker: {
        type: Boolean,
        default: false,
    },
    minuteIncrement: {
        type: Number,
        default: 5,
    },
    dark: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const defaultInputClassName = 'h-11 w-full !cursor-pointer rounded-xl border border-slate-300/80 bg-white/80 px-3 text-sm tracking-wide text-slate-900 caret-emerald-600 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 disabled:!cursor-not-allowed dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:caret-emerald-300 dark:placeholder:text-slate-500 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20';
const fieldInputClassName = 'h-11 w-full !cursor-pointer border-0 bg-transparent text-sm tracking-wide text-slate-900 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:text-slate-400 dark:text-slate-100 dark:placeholder:text-slate-500 dark:disabled:text-slate-500';

const isFieldAppearance = computed(() => String(props.appearance || 'default') === 'field');

const isTimeObject = (value) => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return false;
    }

    const hasHours = Object.prototype.hasOwnProperty.call(value, 'hours');
    const hasMinutes = Object.prototype.hasOwnProperty.call(value, 'minutes');

    if (!hasHours || !hasMinutes) {
        return false;
    }

    const hours = Number(value.hours);
    const minutes = Number(value.minutes);

    return Number.isFinite(hours) && Number.isFinite(minutes);
};

const normalizeTimeObject = (value) => {
    const hours = Math.min(Math.max(Number(value?.hours ?? 0), 0), 23);
    const minutes = Math.min(Math.max(Number(value?.minutes ?? 0), 0), 59);
    const seconds = Math.min(Math.max(Number(value?.seconds ?? 0), 0), 59);

    return {
        hours,
        minutes,
        seconds,
    };
};

const fieldContainerClassName = computed(() => {
    if (props.disabled) {
        return 'flex cursor-not-allowed items-center gap-2.5 rounded-xl border border-slate-200/80 bg-slate-100/80 px-3 transition dark:border-slate-700 dark:bg-slate-800/70';
    }

    return 'flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-300/80 bg-white/80 px-3 transition focus-within:border-emerald-500/70 focus-within:ring-2 focus-within:ring-emerald-500/20 dark:border-blue-200/20 dark:bg-slate-900/80 dark:focus-within:border-emerald-300/70 dark:focus-within:ring-emerald-400/20';
});
const inputClassName = computed(() => (isFieldAppearance.value ? undefined : defaultInputClassName));

const normalizedValue = computed(() => {
    if (props.timePicker) {
        if (isTimeObject(props.modelValue)) {
            return normalizeTimeObject(props.modelValue);
        }

        if (typeof props.modelValue === 'string' && props.modelValue.trim() !== '') {
            const matches = props.modelValue.trim().match(/^(\d{1,2}):(\d{2})$/);
            if (matches) {
                return normalizeTimeObject({
                    hours: Number(matches[1]),
                    minutes: Number(matches[2]),
                    seconds: 0,
                });
            }
        }

        if (props.modelValue instanceof Date && !Number.isNaN(props.modelValue.getTime())) {
            return normalizeTimeObject({
                hours: props.modelValue.getHours(),
                minutes: props.modelValue.getMinutes(),
                seconds: props.modelValue.getSeconds(),
            });
        }

        return null;
    }

    if (props.modelValue instanceof Date) {
        return Number.isNaN(props.modelValue.getTime()) ? null : props.modelValue;
    }

    if (typeof props.modelValue === 'string' && props.modelValue.trim() !== '') {
        const parsedDate = new Date(props.modelValue);
        return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
    }

    return null;
});

const onUpdateModelValue = (nextValue) => {
    if (props.timePicker) {
        if (isTimeObject(nextValue)) {
            emit('update:modelValue', normalizeTimeObject(nextValue));
            return;
        }

        emit('update:modelValue', null);
        return;
    }

    if (nextValue instanceof Date && !Number.isNaN(nextValue.getTime())) {
        emit('update:modelValue', nextValue);
        return;
    }

    emit('update:modelValue', null);
};
</script>

<template>
    <div class="w-full">
        <VueDatePicker
            class="w-full"
            :id="!isFieldAppearance ? (id || undefined) : undefined"
            :name="!isFieldAppearance ? (name || id || undefined) : undefined"
            :model-value="normalizedValue"
            locale="id"
            :format="format"
            :enable-time-picker="timePicker ? false : enableTimePicker"
            :time-picker="timePicker"
            :minutes-increment="minuteIncrement"
            :placeholder="placeholder"
            :disabled="disabled"
            :clearable="clearable"
            :hide-input-icon="hideInputIcon"
            :teleport="teleport"
            :dark="dark"
            :input-class-name="inputClassName"
            :open-menu="isFieldAppearance ? false : 'open'"
            auto-apply
            @update:model-value="onUpdateModelValue"
        >
            <template
                v-if="isFieldAppearance"
                #dp-input="{
                    value,
                    onInput,
                    onEnter,
                    onTab,
                    onClear,
                    onBlur,
                    onFocus,
                    onKeypress,
                    onPaste,
                    openMenu,
                }"
            >
                <div
                    :class="fieldContainerClassName"
                    @mousedown.prevent.stop="!disabled && openMenu()"
                >
                    <input
                        :id="id || undefined"
                        :name="name || id || undefined"
                        type="text"
                        :value="value"
                        :placeholder="placeholder"
                        :disabled="disabled"
                        readonly
                        :class="fieldInputClassName"
                        @input="onInput"
                        @focus="onFocus"
                        @blur="onBlur"
                        @mousedown.stop
                        @click.stop="!disabled && openMenu()"
                        @keydown.enter="onEnter"
                        @keydown.tab="onTab"
                        @keypress="onKeypress"
                        @paste="onPaste"
                    >

                    <button
                        v-if="clearable && value && !disabled"
                        type="button"
                        class="shrink-0 cursor-pointer text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                        aria-label="Hapus tanggal"
                        @mousedown.stop
                        @click.stop="onClear"
                    >
                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                            <path d="M5 5L15 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            <path d="M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </template>
        </VueDatePicker>
    </div>
</template>

<style scoped>
:deep(.dp__input_wrap) {
    width: 100%;
    position: relative;
}

:deep(.dp__input) {
    line-height: 1.25rem;
}

:deep(.dp__input_icon_pad) {
    padding-left: 2.5rem;
}
</style>

<style>
.dp__theme_light {
    --dp-primary-color: #10b981;
    --dp-primary-disabled-color: #6ee7b7;
    --dp-primary-text-color: #ffffff;
    --dp-border-color-focus: #10b981;
    --dp-highlight-color: rgb(16 185 129 / 0.16);
    --dp-range-between-dates-background-color: rgb(16 185 129 / 0.12);
    --dp-range-between-border-color: rgb(16 185 129 / 0.24);
    --dp-loader: 5px solid #10b981;
}

.dp__theme_dark {
    --dp-primary-color: #34d399;
    --dp-primary-disabled-color: #6ee7b7;
    --dp-primary-text-color: #ffffff;
    --dp-border-color-focus: #34d399;
    --dp-highlight-color: rgb(52 211 153 / 0.22);
    --dp-range-between-dates-background-color: rgb(52 211 153 / 0.16);
    --dp-range-between-border-color: rgb(52 211 153 / 0.26);
    --dp-loader: 5px solid #34d399;
}
</style>
