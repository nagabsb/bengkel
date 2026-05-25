<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import SignaturePad from '../Components/UI/SignaturePad.vue';
import { formatRupiah } from '../Utils/formatCurrency';
import { formatDateIndonesia, formatDateTimeIndonesia } from '../Utils/indonesiaDate';

const props = defineProps({
    isValid: {
        type: Boolean,
        default: false,
    },
    canRespond: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
    submitPath: {
        type: String,
        default: null,
    },
    estimate: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashStatus = computed(() => String(page.props?.flash?.status || '').trim());
const estimateCustomerName = computed(() => String(props.estimate?.customer_name || '').trim());
const estimateCustomerPhone = computed(() => String(props.estimate?.customer_phone || '').trim());
const estimateItems = computed(() => (Array.isArray(props.estimate?.items) ? props.estimate.items : []));
const estimateDiagnosis = computed(() => (
    props.estimate?.diagnosis && typeof props.estimate.diagnosis === 'object'
        ? props.estimate.diagnosis
        : null
));
const signaturePadRef = ref(null);
const form = useForm({
    action: 'approve',
    approver_name: estimateCustomerName.value,
    approver_phone: estimateCustomerPhone.value,
    approval_note: '',
    rejection_reason: '',
    signature: '',
});

watch(
    () => [estimateCustomerName.value, estimateCustomerPhone.value],
    ([nextName, nextPhone]) => {
        if (String(form.approver_name || '').trim() === '' && nextName !== '') {
            form.approver_name = nextName;
        }

        if (String(form.approver_phone || '').trim() === '' && nextPhone !== '') {
            form.approver_phone = nextPhone;
        }
    },
    { immediate: true },
);

const statusClass = computed(() => {
    const status = String(props.estimate?.status || '').toLowerCase();
    if (status === 'approved') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }
    if (status === 'pending_approval') {
        return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300';
    }
    if (status === 'rejected') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300';
    }
    if (status === 'expired') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200';
});

const resolveItemTypeLabel = (itemType) => (
    String(itemType || '').toLowerCase() === 'sparepart' ? 'Sparepart' : 'Jasa'
);

const resolveDiagnosisSeverityClass = (severity) => {
    const normalizedSeverity = String(severity || '').trim().toLowerCase();
    if (normalizedSeverity === 'high') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/15 dark:text-rose-300';
    }

    if (normalizedSeverity === 'low') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300';
};

const resolveDiagnosisSeverityLabel = (severity) => {
    const normalizedSeverity = String(severity || '').trim().toLowerCase();
    if (normalizedSeverity === 'high') {
        return 'Risiko Tinggi';
    }

    if (normalizedSeverity === 'low') {
        return 'Risiko Rendah';
    }

    return 'Risiko Sedang';
};

const resolveSignatureFromCanvas = () => {
    const signaturePad = signaturePadRef.value;
    if (!signaturePad || typeof signaturePad.getSignatureData !== 'function') {
        return String(form.signature || '').trim();
    }

    const data = signaturePad.getSignatureData({
        requireFilled: false,
        syncModelValue: true,
        emitErrors: false,
    });

    return String(data || '').trim();
};

const submitApproval = (action) => {
    if (!props.canRespond || !props.submitPath) {
        return;
    }

    form.clearErrors();
    form.action = action;

    if (action === 'approve') {
        const latestSignature = resolveSignatureFromCanvas();
        const cachedSignature = String(form.signature || '').trim();
        const normalizedSignature = latestSignature !== '' ? latestSignature : cachedSignature;

        if (normalizedSignature === '' || !normalizedSignature.startsWith('data:image/png;base64,')) {
            form.setError('signature', 'Tanda tangan digital wajib diisi sebelum menyetujui estimasi.');
            return;
        }

        form.signature = normalizedSignature;
    }

    form.post(props.submitPath, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Approval Estimasi Servis" />

    <main class="min-h-screen bg-gradient-to-br from-emerald-50 via-sky-50 to-white px-4 py-8 text-slate-800 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 dark:text-slate-100 sm:px-6">
        <div class="mx-auto w-full max-w-5xl space-y-4">
            <header class="rounded-2xl border border-emerald-200 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-emerald-500/20 dark:bg-slate-900/90">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Approval Estimasi Servis</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Konfirmasi estimasi biaya sebelum pengerjaan dimulai.
                </p>
            </header>

            <section
                v-if="flashStatus"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                {{ flashStatus }}
            </section>

            <section
                v-if="!isValid"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300"
            >
                {{ errorMessage || 'Link approval tidak valid.' }}
            </section>

            <section
                v-if="isValid && estimate"
                class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimasi</p>
                        <p class="text-lg font-bold">{{ estimate.code || '-' }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Servis {{ estimate.service_order_code || '-' }}</p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="statusClass">
                        {{ estimate.status_label || '-' }}
                    </span>
                </div>

                <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/50 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pelanggan</p>
                        <p class="mt-1 font-semibold">{{ estimate.customer_name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kendaraan</p>
                        <p class="mt-1 font-semibold">{{ estimate.vehicle_name || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ estimate.vehicle_plate_number || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Servis</p>
                        <p class="mt-1 font-semibold">{{ estimate.service_date ? formatDateIndonesia(estimate.service_date) : '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Batas Approval</p>
                        <p class="mt-1 font-semibold">{{ estimate.valid_until ? formatDateTimeIndonesia(estimate.valid_until) : '-' }}</p>
                    </div>
                </div>

                <div class="space-y-2 sm:hidden">
                    <article
                        v-for="item in estimateItems"
                        :key="item.id"
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ item.label || '-' }}</p>
                            <span
                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                :class="String(item.item_type || '').toLowerCase() === 'sparepart'
                                    ? 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-300'
                                    : 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-400/30 dark:bg-indigo-500/15 dark:text-indigo-300'"
                            >
                                {{ resolveItemTypeLabel(item.item_type) }}
                            </span>
                        </div>

                        <p v-if="item.description" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ item.description }}
                        </p>

                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Qty</p>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">{{ item.qty || 0 }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Harga</p>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">{{ formatRupiah(item.unit_price || 0) }}</p>
                            </div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                <p class="text-[11px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Subtotal</p>
                                <p class="font-semibold text-emerald-700 dark:text-emerald-300">{{ formatRupiah(item.subtotal || 0) }}</p>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!estimateItems.length"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-4 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                    >
                        Tidak ada item estimasi
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Estimasi</p>
                            <p class="text-base font-bold text-emerald-700 dark:text-emerald-300">{{ formatRupiah(estimate.total_amount || 0) }}</p>
                        </div>
                    </div>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 sm:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <tr>
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2">Jenis</th>
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-right">Harga</th>
                                <th class="px-3 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in estimateItems"
                                :key="item.id"
                                class="border-t border-slate-200 dark:border-slate-700"
                            >
                                <td class="px-3 py-2">
                                    <p class="font-semibold">{{ item.label || '-' }}</p>
                                    <p v-if="item.description" class="text-xs text-slate-500 dark:text-slate-400">{{ item.description }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    {{ resolveItemTypeLabel(item.item_type) }}
                                </td>
                                <td class="px-3 py-2 text-right">{{ item.qty || 0 }}</td>
                                <td class="px-3 py-2 text-right">{{ formatRupiah(item.unit_price || 0) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal || 0) }}</td>
                            </tr>
                            <tr v-if="!estimateItems.length" class="border-t border-slate-200 dark:border-slate-700">
                                <td colspan="5" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">Tidak ada item estimasi</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/60">
                            <tr>
                                <td colspan="4" class="px-3 py-2 text-right">Total Estimasi</td>
                                <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-300">{{ formatRupiah(estimate.total_amount || 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/40">
                    <p class="font-semibold">Keluhan</p>
                    <p class="mt-1 text-slate-600 dark:text-slate-300">{{ estimate.complaint || '-' }}</p>
                </div>

                <section
                    v-if="estimateDiagnosis"
                    class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-sm dark:border-amber-400/20 dark:bg-amber-500/10"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-amber-800 dark:text-amber-300">Ringkasan Diagnosa Awal</p>
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                            {{ estimateDiagnosis.generated_at ? formatDateTimeIndonesia(estimateDiagnosis.generated_at) : '-' }}
                        </p>
                    </div>

                    <p class="text-slate-700 dark:text-slate-200">
                        {{ estimateDiagnosis.summary || 'Diagnosa awal tersedia sebagai gambaran sebelum inspeksi teknisi.' }}
                    </p>

                    <div
                        v-if="Array.isArray(estimateDiagnosis.possible_causes) && estimateDiagnosis.possible_causes.length > 0"
                        class="space-y-2"
                    >
                        <article
                            v-for="(cause, causeIndex) in estimateDiagnosis.possible_causes"
                            :key="`estimate-diagnosis-cause-${causeIndex}`"
                            class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ cause.label || `Dugaan ${causeIndex + 1}` }}</p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                        :class="resolveDiagnosisSeverityClass(cause.severity)"
                                    >
                                        {{ resolveDiagnosisSeverityLabel(cause.severity) }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                        {{ Number(cause.confidence || 0) }}%
                                    </span>
                                </div>
                            </div>
                            <p v-if="cause.reason" class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ cause.reason }}</p>
                        </article>
                    </div>

                    <div
                        v-if="Array.isArray(estimateDiagnosis.warnings) && estimateDiagnosis.warnings.length > 0"
                        class="rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Peringatan</p>
                        <ul class="mt-1 space-y-1">
                            <li
                                v-for="(warning, warningIndex) in estimateDiagnosis.warnings"
                                :key="`estimate-diagnosis-warning-${warningIndex}`"
                                class="text-xs text-rose-700 dark:text-rose-300"
                            >
                                - {{ warning }}
                            </li>
                        </ul>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ estimateDiagnosis.disclaimer || 'Diagnosa awal, hasil final setelah pemeriksaan teknisi.' }}
                    </p>
                </section>
            </section>

            <section
                v-if="isValid && estimate && canRespond"
                class="space-y-4 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900"
            >
                <h2 class="text-base font-semibold">Konfirmasi Persetujuan</h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Nama Penyetuju</label>
                        <input
                            v-model="form.approver_name"
                            type="text"
                            class="h-11 w-full rounded-xl border border-slate-300/80 bg-white px-3 text-sm outline-none transition focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                            placeholder="Nama jelas"
                        >
                        <p v-if="form.errors.approver_name" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.approver_name }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">No. HP</label>
                        <input
                            v-model="form.approver_phone"
                            type="text"
                            class="h-11 w-full rounded-xl border border-slate-300/80 bg-white px-3 text-sm outline-none transition focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                            placeholder="08xxxxxxxxxx"
                        >
                        <p v-if="form.errors.approver_phone" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.approver_phone }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Catatan Approval</label>
                    <textarea
                        v-model="form.approval_note"
                        rows="3"
                        class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-emerald-400/70 dark:focus:ring-emerald-400/20"
                        placeholder="Opsional"
                    />
                    <p v-if="form.errors.approval_note" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.approval_note }}</p>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Tanda Tangan Digital (untuk setujui)</label>
                    <SignaturePad
                        ref="signaturePadRef"
                        v-model="form.signature"
                        :disabled="form.processing"
                        :show-save-button="false"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">Tanda tangan otomatis diproses saat klik Setujui Estimasi.</p>
                    <p v-if="form.errors.signature" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.signature }}</p>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Alasan Penolakan (jika menolak)</label>
                    <textarea
                        v-model="form.rejection_reason"
                        rows="3"
                        class="w-full rounded-xl border border-slate-300/80 bg-white px-3 py-2 text-sm outline-none transition focus:border-rose-500/70 focus:ring-2 focus:ring-rose-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-rose-400/70 dark:focus:ring-rose-400/20"
                        placeholder="Wajib diisi saat klik Tolak"
                    />
                    <p v-if="form.errors.rejection_reason" class="text-xs text-rose-600 dark:text-rose-300">{{ form.errors.rejection_reason }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center rounded-lg border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                        :disabled="form.processing"
                        @click="submitApproval('reject')"
                    >
                        {{ form.processing ? 'Memproses...' : 'Tolak Estimasi' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        :disabled="form.processing"
                        @click="submitApproval('approve')"
                    >
                        {{ form.processing ? 'Memproses...' : 'Setujui Estimasi' }}
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>
