<script setup>
const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    paperSizeOptions: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    installerDownloadPath: {
        type: String,
        default: '',
    },
    flashStatus: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['submit']);

const submit = () => {
    emit('submit');
};

const downloadInstaller = () => {
    if (!props.installerDownloadPath || !props.canManage || props.form.processing) {
        return;
    }

    if (typeof window === 'undefined') {
        return;
    }

    window.location.assign(props.installerDownloadPath);
};

const fallbackPaperSizeOptions = [
    {
        value: '58mm',
        label: '58 mm',
        description: 'Cocok untuk printer thermal kecil.',
    },
    {
        value: '80mm',
        label: '80 mm',
        description: 'Cocok untuk printer thermal kasir standar.',
    },
];

const resolvedPaperSizeOptions = () => (
    Array.isArray(props.paperSizeOptions) && props.paperSizeOptions.length > 0
        ? props.paperSizeOptions
        : fallbackPaperSizeOptions
);
</script>

<template>
    <section class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Pengaturan Nota Thermal</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Tentukan ukuran kertas thermal untuk cetak nota tenant.
                </p>
            </div>

            <span
                v-if="flashStatus"
                class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
            >
                {{ flashStatus }}
            </span>
        </header>

        <form class="space-y-5 p-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label for="owner-print-printer-name" class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    Nama Printer
                </label>
                <input
                    id="owner-print-printer-name"
                    v-model="form.printer_name"
                    type="text"
                    placeholder="Contoh: Thermal Kasir Depan"
                    maxlength="120"
                    class="h-11 w-full rounded-xl border border-slate-300/80 bg-white/80 px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                    :disabled="!canManage || form.processing"
                >
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Nama ini untuk memudahkan identifikasi printer aktif.
                </p>
                <p v-if="form.errors?.printer_name" class="text-xs text-rose-600 dark:text-rose-300">
                    {{ form.errors.printer_name }}
                </p>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    Ukuran Kertas Thermal
                </label>

                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="paperSize in resolvedPaperSizeOptions()"
                        :key="paperSize.value"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition"
                        :class="form.paper_size === paperSize.value
                            ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-400/40 dark:bg-emerald-500/10'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500'"
                    >
                        <input
                            v-model="form.paper_size"
                            type="radio"
                            name="owner-print-paper-size"
                            :value="paperSize.value"
                            class="mt-0.5 h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:accent-emerald-400"
                            :disabled="!canManage || form.processing"
                        >
                        <span class="space-y-1">
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ paperSize.label }}
                            </span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ paperSize.description }}
                            </span>
                        </span>
                    </label>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Pilihan ini dipakai sebagai default saat cetak nota.
                </p>
                <p v-if="form.errors?.paper_size" class="text-xs text-rose-600 dark:text-rose-300">
                    {{ form.errors.paper_size }}
                </p>
            </div>

            <div class="space-y-3 rounded-xl border border-sky-100 bg-sky-50/70 p-4 dark:border-sky-400/20 dark:bg-sky-500/10">
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                        Installer Auto Print (Windows)
                    </p>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Download script setup sekali install untuk aktifkan kiosk printing + auto start saat login Windows.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-sky-300 bg-white px-3 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-sky-400/40 dark:bg-slate-900 dark:text-sky-300 dark:hover:bg-sky-500/15"
                    :disabled="!canManage || form.processing || !installerDownloadPath"
                    @click="downloadInstaller"
                >
                    Download Installer Sekali Klik (.cmd)
                </button>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Jalankan file `.cmd` sekali di tiap komputer kasir. Setelah selesai, app bisa terbuka otomatis saat login.
                </p>
            </div>

            <div
                v-if="!canManage"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
            >
                Mode hanya-baca. Hanya owner/admin yang bisa mengubah pengaturan nota.
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="!canManage || form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan Nota' }}
                </button>
            </div>
        </form>
    </section>
</template>
