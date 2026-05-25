<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import InputField from '../../../../Components/UI/InputField.vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';

const MODEL_STRATEGY_CONFIG = Object.freeze([
    {
        key: 'fast',
        label: 'Cepat',
        description: 'Respons tercepat untuk tugas harian ringan.',
    },
    {
        key: 'balanced',
        label: 'Seimbang',
        description: 'Kualitas bagus dengan biaya tetap terkontrol.',
    },
    {
        key: 'accurate',
        label: 'Akurat',
        description: 'Jawaban lebih dalam untuk analisis kompleks.',
    },
    {
        key: 'coding',
        label: 'Coding',
        description: 'Dioptimalkan untuk debugging dan penulisan kode.',
    },
]);

const PROVIDER_MODEL_STRATEGY = Object.freeze({
    openai: {
        fast: 'gpt-5-nano',
        balanced: 'gpt-5-mini',
        accurate: 'gpt-5',
        coding: 'gpt-5',
    },
    anthropic: {
        fast: 'claude-haiku-4.5',
        balanced: 'claude-sonnet-4.6',
        accurate: 'claude-opus-4.6',
        coding: 'claude-sonnet-4.6',
    },
    gemini: {
        fast: 'gemini-2.5-flash-lite',
        balanced: 'gemini-3.1-flash',
        accurate: 'gemini-3.1-pro',
        coding: 'gemini-3.1-pro',
    },
    groq: {
        fast: 'llama-3.1-8b-instant',
        balanced: 'llama-3.3-70b-versatile',
        accurate: 'llama-3.3-70b-versatile',
        coding: 'mixtral-8x7b-32768',
    },
    mistral: {
        fast: 'mistral-small-latest',
        balanced: 'mistral-medium-latest',
        accurate: 'mistral-large-latest',
        coding: 'mistral-large-latest',
    },
    deepseek: {
        fast: 'deepseek-chat',
        balanced: 'deepseek-chat',
        accurate: 'deepseek-reasoner',
        coding: 'deepseek-reasoner',
    },
    kimi: {
        fast: 'kimi-k2-turbo-preview',
        balanced: 'kimi-k2.5',
        accurate: 'kimi-k2-thinking',
        coding: 'kimi-k2.5',
    },
});

const props = defineProps({
    isEditMode: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
    providerOptions: {
        type: Array,
        default: () => [],
    },
    agentOptions: {
        type: Array,
        default: () => [],
    },
    selectedProviderGuide: {
        type: Object,
        default: null,
    },
    showApiKey: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit', 'toggle-api-key']);

const modelPickerMode = ref('simple');
const selectedStrategyKey = ref('balanced');
const showPreviewModels = ref(false);

const providerValue = computed(() => String(props.form?.provider || '').trim().toLowerCase());

const availableModelOptions = computed(() => {
    if (!Array.isArray(props.agentOptions)) {
        return [];
    }

    return props.agentOptions
        .map((option) => ({
            value: String(option?.value || '').trim(),
            label: String(option?.label || option?.value || '').trim(),
        }))
        .filter((option) => option.value !== '' && option.label !== '');
});

const availableModelLookup = computed(() => {
    const lookup = new Map();

    availableModelOptions.value.forEach((option) => {
        lookup.set(option.value.toLowerCase(), option.value);
    });

    return lookup;
});

const isPreviewModel = (model) => {
    const normalized = String(model || '').trim().toLowerCase();

    if (normalized === '') {
        return false;
    }

    return normalized.includes('preview') || normalized.includes('experimental');
};

const resolveModelProfile = (model) => {
    const normalized = String(model || '').trim().toLowerCase();

    if (normalized === '') {
        return {
            stage: 'Stabil',
            speed: 'Seimbang',
            cost: 'Sedang',
        };
    }

    const stage = isPreviewModel(normalized) ? 'Preview' : 'Stabil';

    let speed = 'Mendalam';
    if (/(nano|flash-lite|haiku|8b|turbo)/.test(normalized)) {
        speed = 'Cepat';
    } else if (/(mini|flash|small|chat)/.test(normalized)) {
        speed = 'Seimbang';
    }

    let cost = 'Premium';
    if (/(nano|flash-lite|haiku|8b|mini|small)/.test(normalized)) {
        cost = 'Hemat';
    } else if (/(flash|turbo|sonnet|medium|70b|chat)/.test(normalized)) {
        cost = 'Sedang';
    }

    return {
        stage,
        speed,
        cost,
    };
};

const resolveModelForStrategy = (strategyKey) => {
    const providerStrategy = PROVIDER_MODEL_STRATEGY[providerValue.value] || {};
    const candidateModels = [
        providerStrategy[strategyKey],
        providerStrategy.balanced,
        availableModelOptions.value[0]?.value,
    ];

    for (const candidate of candidateModels) {
        const normalizedCandidate = String(candidate || '').trim().toLowerCase();
        if (normalizedCandidate === '') {
            continue;
        }

        const matched = availableModelLookup.value.get(normalizedCandidate);
        if (matched) {
            return matched;
        }
    }

    return '';
};

const strategyCards = computed(() => {
    return MODEL_STRATEGY_CONFIG.map((strategy) => {
        const modelValue = resolveModelForStrategy(strategy.key);
        const profile = resolveModelProfile(modelValue);

        return {
            ...strategy,
            modelValue,
            profile,
        };
    });
});

const advancedAgentOptions = computed(() => {
    const currentModel = String(props.form?.agent_model || '').trim().toLowerCase();

    return availableModelOptions.value.filter((option) => {
        const isCurrentValue = option.value.toLowerCase() === currentModel;
        if (showPreviewModels.value || isCurrentValue) {
            return true;
        }

        return !isPreviewModel(option.value);
    });
});

const selectedModelProfile = computed(() => resolveModelProfile(props.form?.agent_model));

const automaticAgentName = computed(() => {
    const matchedProvider = Array.isArray(props.providerOptions)
        ? props.providerOptions.find((providerOption) => String(providerOption?.value || '').trim().toLowerCase() === providerValue.value)
        : null;

    const providerLabel = String(matchedProvider?.label || providerValue.value || 'Provider').trim();
    const modelLabel = String(props.form?.agent_model || '').trim();

    if (modelLabel === '') {
        return providerLabel;
    }

    return `${providerLabel} - ${modelLabel.toUpperCase()}`;
});

const syncStrategyFromModel = () => {
    const currentModel = String(props.form?.agent_model || '').trim().toLowerCase();
    if (currentModel === '') {
        selectedStrategyKey.value = 'balanced';
        return;
    }

    const matchedCard = strategyCards.value.find((card) => String(card.modelValue || '').trim().toLowerCase() === currentModel);
    if (matchedCard) {
        selectedStrategyKey.value = matchedCard.key;
    }
};

const applyStrategyModel = (strategyKey) => {
    selectedStrategyKey.value = strategyKey;

    const selectedModel = resolveModelForStrategy(strategyKey);
    if (selectedModel !== '') {
        props.form.agent_model = selectedModel;
    }
};

const setModelPickerMode = (nextMode) => {
    if (nextMode !== 'simple' && nextMode !== 'advanced') {
        return;
    }

    modelPickerMode.value = nextMode;

    if (nextMode === 'simple') {
        applyStrategyModel(selectedStrategyKey.value);
    }
};

watch(
    () => props.form?.provider,
    () => {
        if (modelPickerMode.value !== 'simple') {
            return;
        }

        applyStrategyModel(selectedStrategyKey.value);
    },
);

watch(
    () => props.form?.agent_model,
    () => {
        syncStrategyFromModel();
    },
    { immediate: true },
);

onMounted(() => {
    syncStrategyFromModel();

    if (props.isEditMode) {
        const currentModel = String(props.form?.agent_model || '').trim().toLowerCase();
        const isPresetModel = strategyCards.value.some((card) => String(card.modelValue || '').trim().toLowerCase() === currentModel);
        modelPickerMode.value = isPresetModel ? 'simple' : 'advanced';
        return;
    }

    applyStrategyModel('balanced');
});
</script>

<template>
    <article class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-xl sm:max-h-[calc(100dvh-3rem)] dark:border-emerald-400/20 dark:bg-slate-900">
        <header class="flex shrink-0 items-start justify-between gap-3 rounded-t-2xl border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/95">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ isEditMode ? 'Edit Agent AI' : 'Tambah Agent AI' }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Atur provider, model, API key, limit token, dan prioritas failover.</p>
                <p class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">Nama agent otomatis: {{ automaticAgentName }}</p>
            </div>

            <button
                type="button"
                class="grid h-8 w-8 cursor-pointer place-items-center rounded-full border border-slate-200 text-slate-500 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-400 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                aria-label="Tutup modal"
                @click="emit('close')"
            >
                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </button>
        </header>

        <div class="modal-scroll-green min-h-0 overflow-y-auto px-5 pb-5 pt-4">
            <form class="space-y-4" @submit.prevent="emit('submit')">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2" data-enter-ignore="true">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="agent-provider">Provider</label>
                    <AsyncSelect
                        id="agent-provider"
                        v-model="form.provider"
                        :options="providerOptions"
                        placeholder="Pilih provider"
                        search-placeholder="Cari provider..."
                        :clearable="false"
                        trigger-class="h-11"
                        fixed-menu
                    />
                    <p v-if="form.errors.provider" class="min-h-[1.25rem] text-xs text-rose-600 dark:text-rose-300">{{ form.errors.provider }}</p>
                    <p v-else class="min-h-[1.25rem] text-xs text-transparent select-none">placeholder</p>
                </div>

                <div class="grid gap-2" data-enter-ignore="true">
                    <div class="flex items-center justify-between gap-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="agent-model">Model Agent</label>
                        <div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/60">
                            <button
                                type="button"
                                class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold transition"
                                :class="modelPickerMode === 'simple'
                                    ? 'bg-emerald-500 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300'"
                                @click="setModelPickerMode('simple')"
                            >
                                Sederhana
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold transition"
                                :class="modelPickerMode === 'advanced'
                                    ? 'bg-emerald-500 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300'"
                                @click="setModelPickerMode('advanced')"
                            >
                                Lanjutan
                            </button>
                        </div>
                    </div>

                    <div v-if="modelPickerMode === 'simple'" class="grid gap-2">
                        <button
                            v-for="strategy in strategyCards"
                            :key="strategy.key"
                            type="button"
                            class="cursor-pointer rounded-xl border px-3 py-2 text-left transition"
                            :class="selectedStrategyKey === strategy.key
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-500/10'"
                            @click="applyStrategyModel(strategy.key)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold">{{ strategy.label }}</span>
                                <span class="text-xs font-medium uppercase tracking-wide">{{ strategy.profile.stage }}</span>
                            </div>
                            <p class="mt-0.5 text-xs opacity-80">{{ strategy.description }}</p>
                            <p class="mt-1 text-xs font-semibold">{{ strategy.modelValue ? strategy.modelValue.toUpperCase() : '-' }}</p>
                        </button>
                    </div>

                    <div v-else class="grid gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input
                                v-model="showPreviewModels"
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                            >
                            Tampilkan model preview / eksperimen
                        </label>

                        <AsyncSelect
                            id="agent-model"
                            v-model="form.agent_model"
                            :options="advancedAgentOptions"
                            placeholder="Pilih model"
                            search-placeholder="Cari model..."
                            :clearable="false"
                            trigger-class="h-11"
                            fixed-menu
                        />
                    </div>

                    <p v-if="form.errors.agent_model" class="min-h-[1.25rem] text-xs text-rose-600 dark:text-rose-300">{{ form.errors.agent_model }}</p>
                    <div v-else class="min-h-[1.25rem] space-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <p>
                            Model aktif:
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ String(form.agent_model || '-').toUpperCase() }}</span>
                            <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ selectedModelProfile.stage }}</span>
                            <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">{{ selectedModelProfile.speed }}</span>
                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">{{ selectedModelProfile.cost }}</span>
                        </p>
                        <p v-if="selectedProviderGuide">
                            Ambil API key di
                            <a
                                :href="selectedProviderGuide.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-semibold text-emerald-700 underline-offset-2 transition hover:underline dark:text-emerald-300"
                            >
                                {{ selectedProviderGuide.label }}
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <InputField
                id="agent-api-key"
                v-model="form.api_key"
                :type="showApiKey ? 'text' : 'password'"
                label="API Key"
                placeholder="Masukkan API key"
                autocomplete="off"
                :error="form.errors.api_key"
            >
                <template #suffix>
                    <button
                        type="button"
                        class="grid h-6 w-6 cursor-pointer place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        :aria-label="showApiKey ? 'Sembunyikan API key' : 'Tampilkan API key'"
                        @click="emit('toggle-api-key')"
                    >
                        <svg v-if="!showApiKey" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path
                                d="M2 12C4.4 8 7.8 6 12 6C16.2 6 19.6 8 22 12C19.6 16 16.2 18 12 18C7.8 18 4.4 16 2 12Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" />
                        </svg>

                        <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <path
                                d="M10.6 10.9C10.2 11.3 10 11.9 10 12.5C10 13.9 11.1 15 12.5 15C13.1 15 13.7 14.8 14.1 14.4"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M17.6 17.6C15.9 18.5 14.2 19 12 19C7.8 19 4.4 17 2 13C3 11.3 4.2 10 5.6 9"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path d="M22 12C20.9 13.9 19.6 15.3 18 16.4" stroke="currentColor" stroke-width="1.5" />
                            <path
                                d="M7.3 5.3C8.8 4.5 10.2 4 12 4C16.2 4 19.6 6 22 10"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>
                </template>
            </InputField>

            <div class="grid gap-4 sm:grid-cols-2">
                <InputField
                    id="agent-priority"
                    v-model="form.priority_order"
                    type="number"
                    label="Prioritas"
                    placeholder="1"
                    :error="form.errors.priority_order"
                />

                <InputField
                    id="agent-monthly-limit"
                    v-model="form.monthly_token_limit"
                    type="number"
                    label="Limit Token / Bulan"
                    placeholder="Contoh: 100000"
                    :error="form.errors.monthly_token_limit"
                />
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">Default prioritas otomatis dari urutan terakhir, tetap bisa diubah bila perlu.</p>

            <div class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Agent aktif
                </label>

                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input
                        v-model="form.is_default"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Jadikan default agent
                </label>

                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input
                        v-model="form.is_failover_enabled"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Ikut antrean failover
                </label>

                <p class="text-xs text-slate-500 dark:text-slate-400">Urutan failover menggunakan prioritas kecil ke besar (1 paling tinggi).</p>
            </div>

            <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-600 dark:text-slate-200 dark:hover:border-emerald-400/50 dark:hover:text-emerald-300"
                    @click="emit('close')"
                >
                    Tutup
                </button>

                <button
                    type="submit"
                    class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Agent') }}
                </button>
            </footer>
        </form>
        </div>
    </article>
</template>



