<script setup>
import { computed } from 'vue';
import CurrencyInput from '../../../../Components/UI/CurrencyInput.vue';
import { formatNumber, formatRupiah } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia, formatDateTimeIndonesia } from '../../../../Utils/indonesiaDate';
import {
    buildFeaturePromptFromConfig,
    buildTestInputFromConfig,
    createTestInputListItem,
    getFeaturePromptSchema,
    getTestInputSchema,
    normalizeFeaturePromptConfig,
    normalizeTestInputConfig,
} from '../../Services/platformAiPromptBuilder';

const props = defineProps({
    promptSettings: {
        type: Array,
        default: () => [],
    },
    selectedFeatureKey: {
        type: String,
        default: '',
    },
    form: {
        type: Object,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    testForm: {
        type: Object,
        required: true,
    },
    testResult: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['select-feature', 'submit', 'test-output']);

const normalizedPromptSettings = computed(() => {
    if (!Array.isArray(props.promptSettings)) {
        return [];
    }

    return props.promptSettings
        .map((setting) => ({
            feature_key: String(setting?.feature_key || ''),
            name: String(setting?.name || '-'),
            description: String(setting?.description || ''),
            updated_at: setting?.updated_at || null,
            has_feature_prompt_config: Boolean(setting?.has_feature_prompt_config ?? true),
            is_active: Boolean(setting?.is_active ?? true),
        }))
        .filter((setting) => setting.feature_key !== '');
});

const activePromptSetting = computed(() => {
    const selected = normalizedPromptSettings.value.find(
        (setting) => setting.feature_key === String(props.selectedFeatureKey || ''),
    );

    if (selected) {
        return selected;
    }

    return normalizedPromptSettings.value[0] || null;
});

const featurePromptSchema = computed(() => {
    return getFeaturePromptSchema(activePromptSetting.value?.feature_key || props.form?.feature_key || '');
});

const hasStructuredFeaturePrompt = computed(() => {
    return Array.isArray(featurePromptSchema.value?.fields) && featurePromptSchema.value.fields.length > 0;
});

const generatedFeaturePrompt = computed(() => {
    if (!hasStructuredFeaturePrompt.value) {
        return String(props.form?.feature_prompt || '').trim();
    }

    return buildFeaturePromptFromConfig(
        props.form?.feature_key || activePromptSetting.value?.feature_key || '',
        normalizeFeaturePromptConfig(
            props.form?.feature_key || activePromptSetting.value?.feature_key || '',
            props.form?.feature_prompt_config || {},
        ),
    );
});

const featurePromptConfigError = computed(() => String(props.form?.errors?.feature_prompt_config || ''));

const testInputSchema = computed(() => {
    return getTestInputSchema(activePromptSetting.value?.feature_key || props.testForm?.feature_key || '');
});

const hasStructuredTestInput = computed(() => {
    return Boolean(testInputSchema.value?.title || testInputSchema.value?.description);
});

const generatedTestInput = computed(() => {
    if (!hasStructuredTestInput.value) {
        return String(props.testForm?.test_input || '').trim();
    }

    return buildTestInputFromConfig(
        props.testForm?.feature_key || activePromptSetting.value?.feature_key || '',
        normalizeTestInputConfig(
            props.testForm?.feature_key || activePromptSetting.value?.feature_key || '',
            props.testForm?.test_input_config || {},
        ),
    );
});

const addReorderTestItem = () => {
    const featureKey = String(props.testForm?.feature_key || activePromptSetting.value?.feature_key || '');
    const items = Array.isArray(props.testForm?.test_input_config?.items)
        ? props.testForm.test_input_config.items
        : [];

    props.testForm.test_input_config.items = [
        ...items,
        createTestInputListItem(featureKey, 'items', items.length),
    ];
};

const removeReorderTestItem = (index) => {
    const items = Array.isArray(props.testForm?.test_input_config?.items)
        ? props.testForm.test_input_config.items
        : [];

    if (items.length <= 1) {
        return;
    }

    props.testForm.test_input_config.items = items.filter((_, itemIndex) => itemIndex !== index);
};

const addDiagnosisSymptom = () => {
    const featureKey = String(props.testForm?.feature_key || activePromptSetting.value?.feature_key || '');
    const symptoms = Array.isArray(props.testForm?.test_input_config?.symptoms)
        ? props.testForm.test_input_config.symptoms
        : [];

    props.testForm.test_input_config.symptoms = [
        ...symptoms,
        createTestInputListItem(featureKey, 'symptoms', symptoms.length),
    ];
};

const removeDiagnosisSymptom = (index) => {
    const symptoms = Array.isArray(props.testForm?.test_input_config?.symptoms)
        ? props.testForm.test_input_config.symptoms
        : [];

    if (symptoms.length <= 1) {
        return;
    }

    props.testForm.test_input_config.symptoms = symptoms.filter((_, symptomIndex) => symptomIndex !== index);
};

const formatUpdatedAt = (value) => {
    if (!value) {
        return 'Belum pernah disimpan';
    }

    return formatDateIndonesia(value);
};

const activeResult = computed(() => {
    if (!props.testResult || typeof props.testResult !== 'object') {
        return null;
    }

    const selectedFeatureKey = String(activePromptSetting.value?.feature_key || '');
    const resultFeatureKey = String(props.testResult?.feature_key || '');

    return resultFeatureKey !== '' && resultFeatureKey === selectedFeatureKey
        ? props.testResult
        : null;
});

const prettyJson = (value) => {
    if (!value || typeof value !== 'object') {
        return '';
    }

    return JSON.stringify(value, null, 2);
};

const toSafeText = (value) => String(value || '').trim();

const toStringList = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => toSafeText(item))
        .filter((item) => item !== '');
};

const toBoundedPercent = (value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round(parsed)));
};

const serviceEstimatePreview = computed(() => {
    if (!activeResult.value?.output_json || activeResult.value.feature_key !== 'service_estimate_v1') {
        return null;
    }

    const payload = activeResult.value.output_json;
    const items = Array.isArray(payload?.items)
        ? payload.items
            .map((item, index) => {
                const qty = Math.max(1, Number(item?.qty) || 1);
                const unitPrice = Math.max(0, Number(item?.unit_price) || 0);
                const confidence = toBoundedPercent(item?.confidence);

                return {
                    id: `${index}-${toSafeText(item?.label)}`,
                    itemType: toSafeText(item?.item_type) === 'sparepart' ? 'sparepart' : 'service',
                    label: toSafeText(item?.label) || 'Tanpa nama item',
                    description: toSafeText(item?.description || item?.reason),
                    qty,
                    unitLabel: toSafeText(item?.unit_label),
                    unitPrice,
                    subtotal: qty * unitPrice,
                    confidence,
                };
            })
            .filter((item) => item.label !== '')
        : [];

    const computedServiceSubtotal = items
        .filter((item) => item.itemType === 'service')
        .reduce((total, item) => total + item.subtotal, 0);
    const computedSparepartSubtotal = items
        .filter((item) => item.itemType === 'sparepart')
        .reduce((total, item) => total + item.subtotal, 0);
    const subtotalService = Math.max(
        0,
        Number(payload?.totals?.subtotal_service ?? computedServiceSubtotal) || 0,
    );
    const subtotalSparepart = Math.max(
        0,
        Number(payload?.totals?.subtotal_sparepart ?? computedSparepartSubtotal) || 0,
    );
    const totalAmount = Math.max(
        0,
        Number(payload?.totals?.total_amount ?? (subtotalService + subtotalSparepart)) || 0,
    );
    const warnings = toStringList(payload?.warnings || payload?.risk_notes);
    const notes = toStringList(payload?.notes || payload?.advice);
    const confidenceLevel = toBoundedPercent(payload?.confidence_level ?? payload?.overall_confidence);

    return {
        items,
        subtotalService,
        subtotalSparepart,
        totalAmount,
        warnings,
        notes,
        confidenceLevel,
        disclaimer: toSafeText(payload?.disclaimer),
    };
});

const reorderPreview = computed(() => {
    if (!activeResult.value?.output_json || activeResult.value.feature_key !== 'sparepart_reorder_v1') {
        return null;
    }

    const payload = activeResult.value.output_json;
    const recommendations = Array.isArray(payload?.recommendations)
        ? payload.recommendations
            .map((item, index) => ({
                id: `${index}-${toSafeText(item?.spare_part_name)}`,
                sparePartName: toSafeText(item?.spare_part_name) || 'Tanpa nama sparepart',
                currentStock: Math.max(0, Number(item?.current_stock) || 0),
                suggestedReorderQty: Math.max(0, Number(item?.suggested_reorder_qty) || 0),
                priority: toSafeText(item?.priority).toLowerCase() || 'medium',
                reason: toSafeText(item?.reason),
                confidence: toBoundedPercent(item?.confidence),
            }))
            .filter((item) => item.sparePartName !== '')
        : [];

    return {
        recommendations,
        warnings: toStringList(payload?.warnings),
        summary: toSafeText(payload?.summary),
        highPriorityCount: recommendations.filter((item) => item.priority === 'high').length,
    };
});

const diagnosisPreview = computed(() => {
    if (!activeResult.value?.output_json || activeResult.value.feature_key !== 'symptom_diagnosis_v1') {
        return null;
    }

    const payload = activeResult.value.output_json;
    const possibleCauses = Array.isArray(payload?.possible_causes)
        ? payload.possible_causes
            .map((item, index) => ({
                id: `${index}-${toSafeText(item?.label)}`,
                label: toSafeText(item?.label) || 'Tanpa nama dugaan',
                confidence: toBoundedPercent(item?.confidence),
                severity: toSafeText(item?.severity).toLowerCase() || 'medium',
                reason: toSafeText(item?.reason),
                recommendedChecks: toStringList(item?.recommended_checks),
                recommendedActions: toStringList(item?.recommended_actions),
            }))
            .filter((item) => item.label !== '')
        : [];

    return {
        summary: toSafeText(payload?.summary),
        possibleCauses,
        warnings: toStringList(payload?.warnings),
        customerAdvice: toStringList(payload?.customer_advice),
        disclaimer: toSafeText(payload?.disclaimer),
        highSeverityCount: possibleCauses.filter((item) => item.severity === 'high').length,
        topConfidence: possibleCauses.reduce(
            (highest, item) => Math.max(highest, Number(item.confidence) || 0),
            0,
        ),
    };
});

const monthlyReportPreview = computed(() => {
    if (!activeResult.value?.output_json || activeResult.value.feature_key !== 'monthly_business_report_v1') {
        return null;
    }

    const payload = activeResult.value.output_json;
    const kpis = payload?.kpis && typeof payload.kpis === 'object' ? payload.kpis : {};
    const period = payload?.period;
    const periodLabel = typeof period === 'string'
        ? toSafeText(period)
        : `${String(Math.max(1, Number(period?.month) || 1)).padStart(2, '0')}/${Math.max(2020, Number(period?.year) || new Date().getFullYear())}`;

    return {
        periodLabel,
        executiveSummary: toSafeText(payload?.executive_summary || payload?.summary),
        highlights: toStringList(payload?.highlights),
        totalRevenue: Math.max(0, Number(kpis?.total_revenue ?? payload?.total_revenue) || 0),
        serviceRevenue: Math.max(0, Number(kpis?.service_revenue ?? payload?.service_revenue) || 0),
        sparepartRevenue: Math.max(0, Number(kpis?.sparepart_revenue ?? payload?.sparepart_revenue) || 0),
        grossProfitEstimate: Math.max(0, Number(kpis?.gross_profit_estimate ?? payload?.gross_profit_estimate) || 0),
        totalServiceOrders: Math.max(0, Number(kpis?.total_service_orders ?? payload?.total_service_orders) || 0),
        completedServiceOrders: Math.max(0, Number(kpis?.completed_service_orders ?? payload?.completed_service_orders) || 0),
        newCustomers: Math.max(0, Number(kpis?.new_customers ?? payload?.new_customers) || 0),
        risks: toStringList(payload?.risks || payload?.warnings),
        recommendations: toStringList(payload?.recommendations),
        nextMonthFocus: toStringList(payload?.next_month_focus),
        disclaimer: toSafeText(payload?.disclaimer),
    };
});

const hasBusinessPreview = computed(() => Boolean(
    serviceEstimatePreview.value
    || reorderPreview.value
    || diagnosisPreview.value
    || monthlyReportPreview.value,
));

const priorityBadgeClass = (priority) => {
    const normalized = toSafeText(priority).toLowerCase();

    if (normalized === 'high') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    if (normalized === 'low') {
        return 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="space-y-1 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Prompt AI per Kategori</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Area ini untuk admin internal menyusun aturan AI dan melihat simulasi hasil akhir yang akan diterima user operasional.
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Komposisi runtime: system global -> prompt fitur -> override tenant -> input user.
            </p>
        </header>

        <div class="space-y-4 px-5 py-4">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="setting in normalizedPromptSettings"
                    :key="setting.feature_key"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                    :class="setting.feature_key === activePromptSetting?.feature_key
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300'"
                    @click="emit('select-feature', setting.feature_key)"
                >
                    {{ setting.name }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="setting.is_active
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ setting.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </button>
            </div>

            <div
                v-if="activePromptSetting"
                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/40"
            >
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ activePromptSetting.name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ activePromptSetting.description }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Terakhir diperbarui: {{ formatUpdatedAt(activePromptSetting.updated_at) }}
                    </p>
                </div>

                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Prompt kategori aktif
                </label>

                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="platform-ai-system-prompt">
                        Aturan Global AI
                    </label>
                    <textarea
                        id="platform-ai-system-prompt"
                        v-model="form.system_prompt"
                        rows="7"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                        placeholder="Aturan dasar yang wajib diikuti AI untuk semua tenant"
                    />
                    <p v-if="form.errors.system_prompt" class="text-xs text-rose-600 dark:text-rose-300">
                        {{ form.errors.system_prompt }}
                    </p>
                    <p v-else class="text-xs text-slate-500 dark:text-slate-400">
                        Berisi guardrail utama seperti safety, transparansi biaya, dan larangan mengambil keputusan final.
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="space-y-1">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Pengaturan Hasil AI
                        </label>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Atur hasil akhir tanpa menulis prompt manual. Sistem akan membuat prompt otomatis dari setting di bawah.
                        </p>
                    </div>

                    <div
                        v-if="hasStructuredFeaturePrompt"
                        class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ featurePromptSchema.title }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ featurePromptSchema.description }}</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="field in featurePromptSchema.fields"
                                :key="field.key"
                                :class="field.type === 'textarea' ? 'md:col-span-2' : ''"
                            >
                                <label
                                    v-if="field.type === 'checkbox'"
                                    class="flex h-full cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200"
                                >
                                    <input
                                        v-model="form.feature_prompt_config[field.key]"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                    >
                                    <span class="space-y-1">
                                        <span class="block font-medium">{{ field.label }}</span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ field.helper }}</span>
                                    </span>
                                </label>

                                <div v-else class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        {{ field.label }}
                                    </label>
                                    <textarea
                                        v-if="field.type === 'textarea'"
                                        v-model="form.feature_prompt_config[field.key]"
                                        :rows="3"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                        :placeholder="field.placeholder || ''"
                                    />
                                    <input
                                        v-else
                                        v-model="form.feature_prompt_config[field.key]"
                                        :type="field.type === 'number' ? 'number' : 'text'"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                        :min="field.type === 'number' ? field.min : undefined"
                                        :max="field.type === 'number' ? field.max : undefined"
                                        :placeholder="field.placeholder || ''"
                                    >
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ field.helper }}</p>
                                </div>
                            </div>
                        </div>

                        <p v-if="featurePromptConfigError" class="text-xs text-rose-600 dark:text-rose-300">
                            {{ featurePromptConfigError }}
                        </p>

                        <details class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Lihat prompt otomatis
                            </summary>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                Preview ini dibuat otomatis dari setting yang dipilih di atas.
                            </p>
                            <pre class="mt-3 max-h-72 overflow-auto rounded-lg bg-white p-3 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ generatedFeaturePrompt }}</pre>
                        </details>
                    </div>

                    <div v-else class="space-y-2">
                        <textarea
                            id="platform-ai-feature-prompt"
                            v-model="form.feature_prompt"
                            rows="10"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                            placeholder="Instruksi domain sesuai hasil yang ingin dibentuk untuk fitur ini"
                        />
                        <p v-if="form.errors.feature_prompt" class="text-xs text-rose-600 dark:text-rose-300">
                            {{ form.errors.feature_prompt }}
                        </p>
                        <p v-else class="text-xs text-slate-500 dark:text-slate-400">
                            Kategori ini belum memakai builder, jadi masih menggunakan prompt manual.
                        </p>
                    </div>
                </div>

                <div class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="platform-ai-test-input">
                        Data Simulasi Uji
                    </label>

                    <div
                        v-if="hasStructuredTestInput"
                        class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ testInputSchema.title }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ testInputSchema.description }}</p>
                        </div>

                        <div
                            v-if="testForm.feature_key === 'service_estimate_v1'"
                            class="grid gap-3 md:grid-cols-2"
                        >
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Keluhan utama
                                </label>
                                <textarea
                                    v-model="testForm.test_input_config.order.complaint"
                                    rows="3"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    placeholder="Contoh: mesin bergetar saat langsam dan ada bunyi di rem depan"
                                />
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Odometer
                                </label>
                                <input
                                    v-model="testForm.test_input_config.order.odometer"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                >
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Merk kendaraan
                                </label>
                                <input
                                    v-model="testForm.test_input_config.vehicle.brand"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    placeholder="Contoh: Toyota"
                                >
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Model kendaraan
                                </label>
                                <input
                                    v-model="testForm.test_input_config.vehicle.model"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    placeholder="Contoh: Avanza"
                                >
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Tahun kendaraan
                                </label>
                                <input
                                    v-model="testForm.test_input_config.vehicle.year"
                                    type="number"
                                    min="1990"
                                    max="2100"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                >
                            </div>

                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                                <input
                                    v-model="testForm.test_input_config.include_note"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                >
                                <span class="space-y-1">
                                    <span class="block font-medium">Tambahkan catatan customer</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Cocok dipakai untuk menguji disclaimer atau gaya rekomendasi AI.
                                    </span>
                                </span>
                            </label>

                            <div
                                v-if="testForm.test_input_config.include_note"
                                class="space-y-2 md:col-span-2"
                            >
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Catatan tambahan
                                </label>
                                <textarea
                                    v-model="testForm.test_input_config.note"
                                    rows="2"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    placeholder="Contoh: customer minta estimasi sebelum pekerjaan dimulai"
                                />
                            </div>
                        </div>

                        <div
                            v-else-if="testForm.feature_key === 'sparepart_reorder_v1'"
                            class="space-y-4"
                        >
                            <div class="grid gap-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Periode analisis
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.period_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Jumlah hari histori yang dipakai untuk simulasi reorder.
                                    </p>
                                </div>

                                <div class="flex items-end justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                                    <div>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Daftar sparepart uji</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Isi beberapa item untuk melihat prioritas reorder yang dihasilkan AI.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                                        @click="addReorderTestItem"
                                    >
                                        Tambah Item
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(item, index) in testForm.test_input_config.items"
                                    :key="`${index}-${item.spare_part_name}`"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
                                >
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            Sparepart Uji {{ index + 1 }}
                                        </p>
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/40 dark:bg-rose-500/15 dark:text-rose-300 dark:hover:bg-rose-500/25"
                                            :disabled="(testForm.test_input_config.items || []).length <= 1"
                                            @click="removeReorderTestItem(index)"
                                        >
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="space-y-2 md:col-span-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                                Nama sparepart
                                            </label>
                                            <input
                                                v-model="item.spare_part_name"
                                                type="text"
                                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                                placeholder="Contoh: Kampas Rem Depan"
                                            >
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                                Stok saat ini
                                            </label>
                                            <input
                                                v-model="item.current_stock"
                                                type="number"
                                                min="0"
                                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                            >
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                                Rata-rata pemakaian / hari
                                            </label>
                                            <input
                                                v-model="item.avg_daily_usage"
                                                type="number"
                                                min="0"
                                                step="0.1"
                                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                            >
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                                Lead time
                                            </label>
                                            <input
                                                v-model="item.lead_time_days"
                                                type="number"
                                                min="0"
                                                max="365"
                                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                            >
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Hari tunggu barang sampai ke bengkel.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="testForm.feature_key === 'symptom_diagnosis_v1'"
                            class="space-y-4"
                        >
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Keluhan utama customer
                                    </label>
                                    <textarea
                                        v-model="testForm.test_input_config.order.complaint"
                                        rows="3"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                        placeholder="Contoh: mesin brebet saat langsam dan tarikan terasa berat"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Merk kendaraan
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.vehicle.brand"
                                        type="text"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                        placeholder="Contoh: Honda"
                                    >
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Model kendaraan
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.vehicle.model"
                                        type="text"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                        placeholder="Contoh: Beat"
                                    >
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Tahun kendaraan
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.vehicle.year"
                                        type="number"
                                        min="1990"
                                        max="2100"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Odometer
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.vehicle.odometer"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-end justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                                    <div>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Daftar gejala uji</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Tambahkan beberapa gejala yang disampaikan customer agar hasil diagnosa lebih realistis.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                                        @click="addDiagnosisSymptom"
                                    >
                                        Tambah Gejala
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="(symptom, index) in testForm.test_input_config.symptoms"
                                        :key="`${index}-${symptom}`"
                                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:flex-row md:items-start dark:border-slate-700 dark:bg-slate-800/60"
                                    >
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                                Gejala {{ index + 1 }}
                                            </label>
                                            <input
                                                v-model="testForm.test_input_config.symptoms[index]"
                                                type="text"
                                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                                placeholder="Contoh: lampu indikator mesin menyala"
                                            >
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/40 dark:bg-rose-500/15 dark:text-rose-300 dark:hover:bg-rose-500/25"
                                            :disabled="(testForm.test_input_config.symptoms || []).length <= 1"
                                            @click="removeDiagnosisSymptom(index)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                                <input
                                    v-model="testForm.test_input_config.include_note"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                >
                                <span class="space-y-1">
                                    <span class="block font-medium">Tambahkan catatan tambahan</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Misalnya keluhan muncul saat mesin panas atau saat motor dipakai jarak jauh.
                                    </span>
                                </span>
                            </label>

                            <div
                                v-if="testForm.test_input_config.include_note"
                                class="space-y-2"
                            >
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Catatan tambahan
                                </label>
                                <textarea
                                    v-model="testForm.test_input_config.note"
                                    rows="2"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    placeholder="Contoh: keluhan terasa lebih parah saat mesin sudah panas"
                                />
                            </div>
                        </div>

                        <div
                            v-else-if="testForm.feature_key === 'monthly_business_report_v1'"
                            class="space-y-4"
                        >
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Bulan laporan
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.period.month"
                                        type="number"
                                        min="1"
                                        max="12"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Tahun laporan
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.period.year"
                                        type="number"
                                        min="2020"
                                        max="2100"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Omzet total
                                    </label>
                                    <CurrencyInput
                                        v-model="testForm.test_input_config.revenue.total"
                                        placeholder="0"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Omzet jasa
                                    </label>
                                    <CurrencyInput
                                        v-model="testForm.test_input_config.revenue.service"
                                        placeholder="0"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Omzet sparepart
                                    </label>
                                    <CurrencyInput
                                        v-model="testForm.test_input_config.revenue.sparepart"
                                        placeholder="0"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Estimasi laba kotor
                                    </label>
                                    <CurrencyInput
                                        v-model="testForm.test_input_config.revenue.gross_profit_estimate"
                                        placeholder="0"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Total order servis
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.orders.total"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Order selesai
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.orders.completed"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Order pending
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.orders.pending"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Customer baru
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.customers.new"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Customer returning
                                    </label>
                                    <input
                                        v-model="testForm.test_input_config.customers.returning"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    >
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                                <input
                                    v-model="testForm.test_input_config.include_note"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                >
                                <span class="space-y-1">
                                    <span class="block font-medium">Tambahkan konteks bulan berjalan</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Misalnya info promo, gangguan suplai, atau event khusus yang memengaruhi performa.
                                    </span>
                                </span>
                            </label>

                            <div
                                v-if="testForm.test_input_config.include_note"
                                class="space-y-2"
                            >
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Catatan tambahan
                                </label>
                                <textarea
                                    v-model="testForm.test_input_config.note"
                                    rows="2"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                                    placeholder="Contoh: bulan ini ada promo tune up dan keterlambatan suplai kampas rem"
                                />
                            </div>
                        </div>

                        <details class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Lihat data uji otomatis
                            </summary>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                JSON ini akan dikirim saat tombol test output dijalankan.
                            </p>
                            <pre class="mt-3 max-h-72 overflow-auto rounded-lg bg-white p-3 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ generatedTestInput }}</pre>
                        </details>
                    </div>

                    <div v-else class="space-y-2">
                        <textarea
                            id="platform-ai-test-input"
                            v-model="testForm.test_input"
                            rows="7"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/20"
                            placeholder="Isi skenario contoh agar bisa melihat simulasi hasil akhir"
                        />
                    </div>

                    <p v-if="testForm.errors.test_input" class="text-xs text-rose-600 dark:text-rose-300">
                        {{ testForm.errors.test_input }}
                    </p>
                    <p v-else class="text-xs text-slate-500 dark:text-slate-400">
                        Gunakan contoh kasus nyata bengkel agar preview hasil lebih mudah divalidasi oleh tim bisnis.
                    </p>
                </div>

                <div
                    v-if="activeResult"
                    class="space-y-4 rounded-xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-400/30 dark:bg-sky-500/10"
                >
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <span class="rounded-full bg-white px-2 py-0.5 font-semibold dark:bg-slate-900">
                            {{ activeResult.provider || '-' }} / {{ activeResult.agent_model || '-' }}
                        </span>
                        <span class="rounded-full bg-white px-2 py-0.5 font-semibold dark:bg-slate-900">
                            Token: {{ activeResult.token_usage?.total_tokens ?? 0 }}
                        </span>
                        <span class="rounded-full bg-white px-2 py-0.5 font-semibold dark:bg-slate-900">
                            Diuji: {{ formatDateTimeIndonesia(activeResult.tested_at) }}
                        </span>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Preview hasil untuk tim operasional</p>
                    </div>

                    <div
                        v-if="serviceEstimatePreview"
                        class="space-y-4 rounded-xl border border-white/80 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/80"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Estimasi Total</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ formatRupiah(serviceEstimatePreview.totalAmount) }}</p>
                            </div>
                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Confidence</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ serviceEstimatePreview.confidenceLevel }}%</p>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Total Item</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ formatNumber(serviceEstimatePreview.items.length) }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Draft Estimasi</p>
                            <div class="space-y-2">
                                <div
                                    v-for="item in serviceEstimatePreview.items"
                                    :key="item.id"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                    :class="item.itemType === 'sparepart'
                                                        ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'
                                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'"
                                                >
                                                    {{ item.itemType === 'sparepart' ? 'Sparepart' : 'Jasa' }}
                                                </span>
                                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.label }}</span>
                                            </div>
                                            <p v-if="item.description" class="text-sm text-slate-600 dark:text-slate-300">{{ item.description }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Qty {{ formatNumber(item.qty) }}{{ item.unitLabel ? ` ${item.unitLabel}` : '' }} | Confidence {{ item.confidence }}%
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Subtotal</p>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(item.subtotal) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Subtotal Jasa</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(serviceEstimatePreview.subtotalService) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Subtotal Sparepart</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(serviceEstimatePreview.subtotalSparepart) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Catatan wajib</p>
                                <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                    {{ serviceEstimatePreview.disclaimer || 'Estimasi awal, final setelah inspeksi teknisi.' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="serviceEstimatePreview.warnings.length || serviceEstimatePreview.notes.length" class="grid gap-3 md:grid-cols-2">
                            <div v-if="serviceEstimatePreview.warnings.length" class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Risiko / perhatian</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="warning in serviceEstimatePreview.warnings" :key="warning">- {{ warning }}</li>
                                </ul>
                            </div>
                            <div v-if="serviceEstimatePreview.notes.length" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Saran review</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="note in serviceEstimatePreview.notes" :key="note">- {{ note }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="reorderPreview"
                        class="space-y-4 rounded-xl border border-white/80 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/80"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Rekomendasi</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ formatNumber(reorderPreview.recommendations.length) }}</p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Prioritas Tinggi</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ formatNumber(reorderPreview.highPriorityCount) }}</p>
                            </div>
                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Status</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ reorderPreview.summary || 'Rekomendasi reorder siap direview.' }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Daftar Reorder</p>
                            <div class="space-y-2">
                                <div
                                    v-for="item in reorderPreview.recommendations"
                                    :key="item.id"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                    :class="priorityBadgeClass(item.priority)"
                                                >
                                                    {{ item.priority || 'medium' }}
                                                </span>
                                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.sparePartName }}</span>
                                            </div>
                                            <p v-if="item.reason" class="text-sm text-slate-600 dark:text-slate-300">{{ item.reason }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Stok sekarang {{ formatNumber(item.currentStock) }} | Confidence {{ item.confidence }}%
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Saran reorder</p>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatNumber(item.suggestedReorderQty) }} pcs</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="reorderPreview.warnings.length" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Catatan perhatian</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                <li v-for="warning in reorderPreview.warnings" :key="warning">- {{ warning }}</li>
                            </ul>
                        </div>
                    </div>

                    <div
                        v-else-if="diagnosisPreview"
                        class="space-y-4 rounded-xl border border-white/80 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/80"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Dugaan</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ formatNumber(diagnosisPreview.possibleCauses.length) }}</p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Urgensi Tinggi</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ formatNumber(diagnosisPreview.highSeverityCount) }}</p>
                            </div>
                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Confidence Tertinggi</p>
                                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ diagnosisPreview.topConfidence }}%</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ringkasan Diagnosa</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                                {{ diagnosisPreview.summary || 'Diagnosa awal siap direview frontdesk.' }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kemungkinan Penyebab</p>
                            <div class="space-y-2">
                                <div
                                    v-for="cause in diagnosisPreview.possibleCauses"
                                    :key="cause.id"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                    :class="priorityBadgeClass(cause.severity)"
                                                >
                                                    {{ cause.severity || 'medium' }}
                                                </span>
                                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ cause.label }}</span>
                                            </div>
                                            <p v-if="cause.reason" class="text-sm text-slate-600 dark:text-slate-300">{{ cause.reason }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Confidence {{ cause.confidence }}%
                                            </p>
                                        </div>
                                    </div>

                                    <div v-if="cause.recommendedChecks.length || cause.recommendedActions.length" class="mt-3 grid gap-3 md:grid-cols-2">
                                        <div v-if="cause.recommendedChecks.length" class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pengecekan awal</p>
                                            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                                <li v-for="check in cause.recommendedChecks" :key="check">- {{ check }}</li>
                                            </ul>
                                        </div>
                                        <div v-if="cause.recommendedActions.length" class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tindakan awal</p>
                                            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                                <li v-for="action in cause.recommendedActions" :key="action">- {{ action }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="diagnosisPreview.warnings.length || diagnosisPreview.customerAdvice.length" class="grid gap-3 md:grid-cols-2">
                            <div v-if="diagnosisPreview.warnings.length" class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Peringatan</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="warning in diagnosisPreview.warnings" :key="warning">- {{ warning }}</li>
                                </ul>
                            </div>
                            <div v-if="diagnosisPreview.customerAdvice.length" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Saran ke customer</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="advice in diagnosisPreview.customerAdvice" :key="advice">- {{ advice }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Catatan wajib</p>
                            <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                {{ diagnosisPreview.disclaimer || 'Diagnosa awal, hasil final setelah pemeriksaan teknisi.' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else-if="monthlyReportPreview"
                        class="space-y-4 rounded-xl border border-white/80 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/80"
                    >
                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Periode</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">{{ monthlyReportPreview.periodLabel || '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Omzet Total</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">{{ formatRupiah(monthlyReportPreview.totalRevenue) }}</p>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Order Selesai</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">
                                    {{ formatNumber(monthlyReportPreview.completedServiceOrders) }} / {{ formatNumber(monthlyReportPreview.totalServiceOrders) }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-400/30 dark:bg-indigo-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Customer Baru</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">{{ formatNumber(monthlyReportPreview.newCustomers) }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ringkasan Eksekutif</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                                {{ monthlyReportPreview.executiveSummary || 'Ringkasan bulanan siap direview owner/manager.' }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Omzet Jasa</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(monthlyReportPreview.serviceRevenue) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Omzet Sparepart</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(monthlyReportPreview.sparepartRevenue) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Estimasi Laba Kotor</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(monthlyReportPreview.grossProfitEstimate) }}</p>
                            </div>
                        </div>

                        <div v-if="monthlyReportPreview.highlights.length" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Highlights</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                <li v-for="highlight in monthlyReportPreview.highlights" :key="highlight">- {{ highlight }}</li>
                            </ul>
                        </div>

                        <div
                            v-if="monthlyReportPreview.risks.length || monthlyReportPreview.recommendations.length || monthlyReportPreview.nextMonthFocus.length"
                            class="grid gap-3 md:grid-cols-3"
                        >
                            <div v-if="monthlyReportPreview.risks.length" class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Risiko</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="risk in monthlyReportPreview.risks" :key="risk">- {{ risk }}</li>
                                </ul>
                            </div>
                            <div v-if="monthlyReportPreview.recommendations.length" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Rekomendasi</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="recommendation in monthlyReportPreview.recommendations" :key="recommendation">- {{ recommendation }}</li>
                                </ul>
                            </div>
                            <div v-if="monthlyReportPreview.nextMonthFocus.length" class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Fokus Bulan Depan</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="focus in monthlyReportPreview.nextMonthFocus" :key="focus">- {{ focus }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Catatan wajib</p>
                            <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                {{ monthlyReportPreview.disclaimer || 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        Respons AI berhasil diterima, tetapi belum bisa divisualkan sebagai preview bisnis. Cek respons mentah di bawah untuk evaluasi.
                    </div>

                    <details class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Lihat detail teknis respons
                        </summary>
                        <div class="mt-3 space-y-3">
                            <div class="space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Respons mentah</p>
                                <pre class="max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ activeResult.output_text }}</pre>
                            </div>
                            <div v-if="activeResult.output_json" class="space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">JSON terdeteksi</p>
                                <pre class="max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ prettyJson(activeResult.output_json) }}</pre>
                            </div>
                        </div>
                    </details>

                    <p
                        v-if="!hasBusinessPreview && !activeResult.output_json"
                        class="text-xs text-slate-500 dark:text-slate-400"
                    >
                        Tips: arahkan aturan hasil agar AI mengembalikan struktur yang konsisten supaya preview bisa dibentuk otomatis.
                    </p>
                </div>

                <footer class="flex flex-wrap items-center justify-end gap-2 pt-1">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-sky-400/40 dark:bg-sky-500/15 dark:text-sky-300 dark:hover:bg-sky-500/25"
                        :disabled="testForm.processing"
                        @click="emit('test-output')"
                    >
                        {{ testForm.processing ? 'Menjalankan Test...' : 'Test Output Prompt' }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        :disabled="processing"
                        @click="emit('submit')"
                    >
                        {{ processing ? 'Menyimpan...' : 'Simpan Prompt' }}
                    </button>
                </footer>
            </div>
        </div>
    </article>
</template>
