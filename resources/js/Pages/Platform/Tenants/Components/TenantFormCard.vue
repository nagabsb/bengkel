<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import InputField from '../../../../Components/UI/InputField.vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import DatePicker from '../../../../Components/UI/DatePicker.vue';
import { formatRupiah } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    isEditMode: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
    planOptions: {
        type: Array,
        default: () => [],
    },
    tenantRootDomain: {
        type: String,
        default: '',
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'submit']);
const firstInputId = 'tenant-name';

const normalizeDate = (value) => {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsedDate = new Date(value);
        return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
    }

    return null;
};

const normalizedPlanOptions = computed(() => {
    if (!Array.isArray(props.planOptions)) {
        return [];
    }

    return props.planOptions.map((planPrice) => {
        const amount = Number(planPrice?.amount) || 0;
        const durationMonths = Number(planPrice?.duration_months) || 1;
        const discountPct = Number(planPrice?.discount_pct) || 0;
        const planName = String(planPrice?.plan?.name || '-');
        const label = String(planPrice?.label || '-');

        return {
            value: Number(planPrice?.id) || 0,
            label: `${planName} - ${label}`,
            raw: {
                ...planPrice,
                subtitle: `${formatRupiah(amount)} / ${durationMonths} bulan${discountPct > 0 ? ` - Diskon ${discountPct}%` : ''}`,
            },
        };
    }).filter((planPrice) => Number(planPrice.value) > 0);
});

const hasSelectedPlan = computed(() => {
    return Number(props.form.plan_price_id) > 0;
});

watch(
    hasSelectedPlan,
    (selected) => {
        if (!selected) {
            props.form.plan_started_at = null;
            return;
        }

        if (!normalizeDate(props.form.plan_started_at)) {
            props.form.plan_started_at = new Date();
        }
    },
    { immediate: true },
);

const selectedPlanPrice = computed(() => {
    const selectedPlanPriceId = Number(props.form.plan_price_id);
    if (!Number.isInteger(selectedPlanPriceId) || selectedPlanPriceId <= 0) {
        return null;
    }

    const selectedOption = normalizedPlanOptions.value.find((option) => Number(option.value) === selectedPlanPriceId);
    return selectedOption?.raw || null;
});

const subscriptionPreview = computed(() => {
    if (!selectedPlanPrice.value) {
        return null;
    }

    const startedAt = normalizeDate(props.form.plan_started_at);
    if (!startedAt) {
        return null;
    }

    const durationMonths = Math.max(1, Number(selectedPlanPrice.value?.duration_months) || 1);
    const expiredAt = new Date(startedAt);
    expiredAt.setMonth(expiredAt.getMonth() + durationMonths);

    return {
        startedAt,
        expiredAt,
    };
});

const subdomainSuffix = computed(() => {
    const normalizedRootDomain = String(props.tenantRootDomain || '').trim().toLowerCase();
    return normalizedRootDomain !== '' ? `.${normalizedRootDomain}` : '';
});

const handleEscKey = (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    event.preventDefault();
    emit('close');
};

const handleEnterKey = (event) => {
    if (event.key !== 'Enter' || event.isComposing) {
        return;
    }

    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    if (target.closest('[data-enter-ignore="true"]')) {
        return;
    }

    const tagName = target.tagName.toLowerCase();
    if (tagName === 'textarea' || tagName === 'button') {
        return;
    }

    if (tagName === 'input') {
        const inputType = String(target.getAttribute('type') || 'text').toLowerCase();
        if (['checkbox', 'radio', 'file', 'submit', 'button'].includes(inputType)) {
            return;
        }
    }

    event.preventDefault();
    emit('submit');
};

const focusFirstInput = () => {
    nextTick(() => {
        const firstInput = document.getElementById(firstInputId);
        if (!(firstInput instanceof HTMLInputElement)) {
            return;
        }

        firstInput.focus();
        firstInput.select();
    });
};

onMounted(() => {
    if (!props.isEditMode) {
        focusFirstInput();
    }
});
</script>

<template>
    <article
        class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-emerald-500/20 dark:bg-slate-900"
    >
        <div
            class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900"
        >
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ isEditMode ? 'Edit Tenant' : 'Tambah Tenant' }}
            </h3>
            <button
                type="button"
                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                aria-label="Tutup modal"
                @click="emit('close')"
            >
                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="modal-scroll-green min-h-0 overflow-y-auto px-5 pb-5 pt-4">
            <form class="space-y-4" @submit.prevent="emit('submit')" @keydown.esc="handleEscKey" @keydown.enter="handleEnterKey">
                <InputField
                    id="tenant-name"
                    v-model="form.name"
                    label="Nama Tenant"
                    placeholder="Contoh: AutoServ Group"
                    :error="form.errors.name"
                />

                <InputField
                    id="tenant-code"
                    v-model="form.code"
                    label="Kode Tenant"
                    placeholder="Auto-generated"
                    :readonly="true"
                    :error="form.errors.code"
                />
                <p class="-mt-2 text-xs text-slate-500 dark:text-slate-400">Kode tenant dibuat otomatis dan unik dari nama tenant.</p>

                <InputField
                    id="tenant-subdomain"
                    v-model="form.subdomain"
                    label="Subdomain Tenant"
                    placeholder="Contoh: bengkel-maju"
                    :error="form.errors.subdomain"
                >
                    <template #suffix>
                        <span class="text-xs font-medium tracking-normal text-slate-500 dark:text-slate-300">
                            {{ subdomainSuffix }}
                        </span>
                    </template>
                </InputField>

                <InputField
                    id="tenant-phone"
                    v-model="form.phone"
                    label="No. HP Bengkel"
                    placeholder="Contoh: 081234567890"
                    autocomplete="tel"
                    :required="true"
                    :error="form.errors.phone"
                />

                <InputField
                    id="tenant-address"
                    v-model="form.address"
                    label="Alamat Bengkel"
                    placeholder="Opsional: alamat bengkel untuk header nota"
                    :error="form.errors.address"
                />

                <div v-if="!isEditMode" class="grid gap-4 sm:grid-cols-2">
                    <InputField
                        id="tenant-owner-name"
                        v-model="form.owner_name"
                        label="Nama Owner"
                        placeholder="Contoh: Budi Santoso"
                        autocomplete="name"
                        :error="form.errors.owner_name"
                    />

                    <InputField
                        id="tenant-owner-email"
                        v-model="form.owner_email"
                        label="Email Owner"
                        type="email"
                        placeholder="owner@contoh.com"
                        autocomplete="email"
                        :error="form.errors.owner_email"
                    />
                </div>

                <InputField
                    v-if="!isEditMode"
                    id="tenant-owner-password"
                    v-model="form.owner_password"
                    label="Password Owner"
                    type="password"
                    placeholder="Minimal 8 karakter"
                    autocomplete="new-password"
                    :error="form.errors.owner_password"
                />

                <div class="space-y-1.5" data-enter-ignore="true">
                    <label
                        for="tenant-plan-price"
                        class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                    >
                        Paket Aktif
                    </label>
                    <AsyncSelect
                        id="tenant-plan-price"
                        v-model="form.plan_price_id"
                        :options="normalizedPlanOptions"
                        placeholder="Pilih paket"
                        search-placeholder="Cari paket..."
                        :clearable="true"
                        trigger-class="h-11"
                        menu-max-height-class="max-h-64"
                        fixed-menu
                    >
                        <template #option="{ option }">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ option.label }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ option.subtitle }}</p>
                            </div>
                        </template>
                    </AsyncSelect>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Kosongkan bila tenant belum berlangganan.
                    </p>
                    <p
                        v-if="form.errors.plan_price_id"
                        class="text-sm font-medium text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.plan_price_id }}
                    </p>
                </div>

                <div class="space-y-1.5" data-enter-ignore="true">
                    <label
                        for="tenant-plan-started-at"
                        class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                    >
                        Tanggal Mulai Paket
                    </label>
                    <DatePicker
                        id="tenant-plan-started-at"
                        v-model="form.plan_started_at"
                        :disabled="!hasSelectedPlan"
                        placeholder="Pilih tanggal mulai"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Saat paket dipilih, tanggal akhir dihitung otomatis dari durasi paket.
                    </p>
                    <p
                        v-if="form.errors.plan_started_at"
                        class="text-sm font-medium text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.plan_started_at }}
                    </p>
                </div>

                <div
                    v-if="selectedPlanPrice"
                    class="space-y-1 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/30 dark:text-slate-300"
                >
                    <p class="font-semibold text-slate-700 dark:text-slate-200">Detail Paket</p>
                    <p>{{ selectedPlanPrice.plan?.name || '-' }} - {{ selectedPlanPrice.label || '-' }}</p>
                    <p>{{ selectedPlanPrice.subtitle }}</p>
                    <p>
                        Mulai: {{ subscriptionPreview?.startedAt ? formatDateIndonesia(subscriptionPreview.startedAt) : '-' }}
                    </p>
                    <p>
                        Berakhir: {{ subscriptionPreview?.expiredAt ? formatDateIndonesia(subscriptionPreview.expiredAt) : '-' }}
                    </p>
                </div>

                <label
                    class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600 dark:text-slate-300"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Tenant aktif
                </label>

                <p
                    v-if="errors?.create_tenant && !form.errors.create_tenant"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_tenant }}
                </p>
                <p
                    v-if="errors?.update_tenant && !form.errors.update_tenant"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.update_tenant }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Tenant') }}
                </button>
            </form>
        </div>
    </article>
</template>

