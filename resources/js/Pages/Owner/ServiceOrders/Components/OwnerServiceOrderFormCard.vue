<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import DatePicker from '../../../../Components/UI/DatePicker.vue';
import InputField from '../../../../Components/UI/InputField.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    customerOptions: {
        type: Array,
        default: () => [],
    },
    filteredVehicleOptions: {
        type: Array,
        default: () => [],
    },
    workshopOptions: {
        type: Array,
        default: () => [],
    },
    isWorkshopSelectable: {
        type: Boolean,
        default: false,
    },
    vehicleMasterOptions: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'submit']);
const firstInputId = 'service-customer-name';
const normalizedWorkshopOptions = computed(() => (
    Array.isArray(props.workshopOptions)
        ? props.workshopOptions.map((workshop) => ({
            value: String(workshop?.value || ''),
            label: String(workshop?.label || ''),
            subtitle: String(workshop?.subtitle || ''),
        })).filter((workshop) => workshop.value !== '' && workshop.label !== '')
        : []
));

const normalizedCustomerOptions = computed(() => (
    props.customerOptions.map((customer) => {
        const value = String(customer?.id || '').trim();
        const name = String(customer?.name || '').trim();
        const address = String(customer?.address || '').trim();
        const subtitle = String(customer?.subtitle || '').trim();
        const fallbackName = name !== '' ? name : (subtitle !== '' ? subtitle : 'Pelanggan');
        const label = address !== '' ? `${fallbackName} (${address})` : fallbackName;

        return {
            value,
            label,
            name,
            address,
            subtitle,
            raw: customer,
        };
    }).filter((customer) => customer.value !== '')
));

const normalizedVehicleOptions = computed(() => (
    props.filteredVehicleOptions.map((vehicle) => ({
        value: String(vehicle?.id || ''),
        label: `${String(vehicle?.vehicle_type_label || 'Kendaraan')} - ${String(vehicle?.display_name || '-')}${String(vehicle?.plate_number || '').trim() !== '' ? ` - ${String(vehicle.plate_number)}` : ''}`,
        raw: vehicle,
    })).filter((vehicle) => vehicle.value !== '')
));

const hasSelectedCustomer = computed(() => String(props.form.customer_id || '').trim() !== '');
const hasSelectedVehicle = computed(() => String(props.form.vehicle_id || '').trim() !== '');
const hasVehicleHistoryOptions = computed(() => normalizedVehicleOptions.value.length > 0);
const normalizedVehicleType = computed(() => {
    const value = String(props.form.vehicle_type || '').trim().toLowerCase();
    return value === 'mobil' ? 'mobil' : 'motor';
});

const normalizedVehicleMasterOptions = computed(() => (
    props.vehicleMasterOptions
        .map((vehicleMaster) => {
            const value = String(vehicleMaster?.id || '').trim();
            const brand = String(vehicleMaster?.brand || '').trim();
            const model = String(vehicleMaster?.model || '').trim();
            const vehicleType = String(vehicleMaster?.vehicle_type || 'motor').trim().toLowerCase();

            return {
                value,
                label: model,
                brand,
                model,
                vehicle_type: vehicleType === 'mobil' ? 'mobil' : 'motor',
                raw: vehicleMaster,
            };
        })
        .filter((vehicleMaster) => vehicleMaster.value !== '' && vehicleMaster.brand !== '' && vehicleMaster.model !== '')
        .filter((vehicleMaster) => vehicleMaster.vehicle_type === normalizedVehicleType.value)
));
const estimatedFinishPreview = computed(() => {
    const rawDays = String(props.form.estimated_days ?? '').trim();
    const estimatedDays = Number(rawDays);

    if (!Number.isFinite(estimatedDays) || estimatedDays < 1) {
        return '';
    }

    const serviceDate = props.form.service_date instanceof Date
        ? props.form.service_date
        : new Date(props.form.service_date);

    if (Number.isNaN(serviceDate.getTime())) {
        return '';
    }

    const finishDate = new Date(serviceDate);
    finishDate.setDate(finishDate.getDate() + Math.max(1, Math.floor(estimatedDays)) - 1);

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(finishDate);
});

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

    event.preventDefault();
    emit('submit');
};

onMounted(() => {
    focusFirstInput();
});

watch(
    () => props.form.vehicle_type,
    () => {
        if (hasSelectedVehicle.value) {
            return;
        }

        const selectedVehicleMasterId = String(props.form.vehicle_master_id || '').trim();
        const isMasterStillValid = normalizedVehicleMasterOptions.value.some((vehicleMaster) => vehicleMaster.value === selectedVehicleMasterId);
        if (!isMasterStillValid) {
            props.form.vehicle_master_id = null;
            props.form.vehicle_brand = '';
            props.form.vehicle_model = '';
        }
    },
);

watch(
    () => props.form.vehicle_master_id,
    (vehicleMasterId) => {
        if (hasSelectedVehicle.value) {
            return;
        }

        const normalizedVehicleMasterId = String(vehicleMasterId || '').trim();
        if (normalizedVehicleMasterId === '') {
            props.form.vehicle_brand = '';
            props.form.vehicle_model = '';
            return;
        }

        const selectedVehicleMaster = normalizedVehicleMasterOptions.value.find((vehicleMaster) => vehicleMaster.value === normalizedVehicleMasterId);
        if (!selectedVehicleMaster) {
            return;
        }

        props.form.vehicle_type = selectedVehicleMaster.vehicle_type;
        props.form.vehicle_brand = selectedVehicleMaster.brand;
        props.form.vehicle_model = selectedVehicleMaster.model;
    },
);

watch(
    () => props.form.vehicle_plate_number,
    (plateNumber) => {
        const normalizedPlateNumber = String(plateNumber || '').toUpperCase();
        if (normalizedPlateNumber === String(plateNumber || '')) {
            return;
        }

        props.form.vehicle_plate_number = normalizedPlateNumber;
    },
);
</script>

<template>
    <article
        class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-emerald-500/20 dark:bg-slate-900"
    >
        <div
            class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Tambah Servis
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Isi data per bagian agar lebih cepat dan rapi.
                </p>
            </div>
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

        <div class="modal-scroll-green min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <form
                id="service-order-form"
                class="space-y-5"
                @submit.prevent="emit('submit')"
                @keydown.esc="handleEscKey"
                @keydown.enter="handleEnterKey"
            >
                <section class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Pelanggan</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Pilih histori atau isi pelanggan baru</p>
                    </div>

                    <div v-if="isWorkshopSelectable" class="space-y-1.5" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-workshop-id">
                            Bengkel Tujuan
                            <span class="ml-1 text-rose-500">*</span>
                        </label>
                        <AsyncSelect
                            id="service-workshop-id"
                            v-model="form.workshop_id"
                            :options="normalizedWorkshopOptions"
                            placeholder="Pilih bengkel tujuan"
                            search-placeholder="Cari bengkel..."
                            :clearable="false"
                            trigger-class="h-11"
                            fixed-menu
                        >
                            <template #option="{ option }">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ option?.label || '-' }}
                                    </p>
                                    <p v-if="option?.subtitle" class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ option.subtitle }}
                                    </p>
                                </div>
                            </template>
                        </AsyncSelect>
                        <p v-if="form.errors.workshop_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.workshop_id }}</p>
                    </div>

                    <div class="space-y-1.5" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-customer-id">Pilih Pelanggan (Opsional)</label>
                        <AsyncSelect
                            id="service-customer-id"
                            v-model="form.customer_id"
                            :options="normalizedCustomerOptions"
                            placeholder="Tambah pelanggan baru"
                            search-placeholder="Cari pelanggan..."
                            :clearable="true"
                            trigger-class="h-11"
                            fixed-menu
                        >
                            <template #option="{ option }">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ option?.raw?.name || option?.name || option?.label || 'Pelanggan' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ option?.raw?.address || option?.address || option?.raw?.subtitle || option?.subtitle || '-' }}
                                    </p>
                                </div>
                            </template>
                        </AsyncSelect>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Jika pelanggan belum ada, isi data di bawah.</p>
                        <p v-if="form.errors.customer_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.customer_id }}</p>
                    </div>

                    <div v-if="!hasSelectedCustomer" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <InputField
                            id="service-customer-name"
                            v-model="form.customer_name"
                            label="Nama Pelanggan"
                            placeholder="Contoh: Budi Santoso"
                            :required="true"
                            :error="form.errors.customer_name"
                        />

                        <InputField
                            id="service-customer-phone"
                            v-model="form.customer_phone"
                            label="No. HP (Opsional)"
                            placeholder="Contoh: 081234567890"
                            :error="form.errors.customer_phone"
                        />

                        <InputField
                            id="service-customer-email"
                            v-model="form.customer_email"
                            label="Email (Opsional)"
                            type="email"
                            placeholder="Contoh: budi@email.com"
                            :error="form.errors.customer_email"
                        />

                        <div class="md:col-span-3">
                            <InputField
                                id="service-customer-address"
                                v-model="form.customer_address"
                                label="Alamat (Opsional)"
                                placeholder="Alamat pelanggan"
                                :error="form.errors.customer_address"
                            />
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Kendaraan</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Simpan histori motor/mobil pelanggan dan KM terakhir</p>
                    </div>

                    <div v-if="hasSelectedCustomer && hasVehicleHistoryOptions" class="space-y-1.5" data-enter-ignore="true">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-vehicle-id">Pilih Kendaraan Tersimpan (Opsional)</label>
                        <AsyncSelect
                            id="service-vehicle-id"
                            v-model="form.vehicle_id"
                            :options="normalizedVehicleOptions"
                            placeholder="Pilih kendaraan dari histori pelanggan"
                            clear-text="Masukkan kendaraan baru"
                            search-placeholder="Cari kendaraan..."
                            :clearable="true"
                            trigger-class="h-11"
                            fixed-menu
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">Pilih kendaraan dari histori pelanggan jika sudah pernah servis.</p>
                        <p v-if="form.errors.vehicle_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.vehicle_id }}</p>
                    </div>

                    <div v-if="!hasSelectedVehicle" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-vehicle-type">
                                    Jenis Kendaraan
                                </label>
                                <div class="relative">
                                    <select
                                        id="service-vehicle-type"
                                        v-model="form.vehicle_type"
                                        class="h-11 w-full cursor-pointer appearance-none rounded-xl border px-3 pr-10 text-sm tracking-wide outline-none transition focus:outline-none focus-visible:outline-none"
                                        :class="form.errors.vehicle_type
                                            ? 'border-rose-400/80 bg-rose-50/40 text-rose-700 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:focus:border-rose-300/70 dark:focus:ring-rose-300/30'
                                            : 'border-emerald-300/80 bg-emerald-50/40 text-emerald-800 hover:border-emerald-400/70 focus:border-emerald-500/80 focus:ring-2 focus:ring-emerald-500/20 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-200 dark:hover:border-emerald-300/70 dark:focus:border-emerald-300/80 dark:focus:ring-emerald-300/30'"
                                    >
                                        <option value="motor">Motor</option>
                                        <option value="mobil">Mobil</option>
                                    </select>
                                    <span
                                        class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500 dark:text-slate-400"
                                        aria-hidden="true"
                                    >
                                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4">
                                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                                <p v-if="form.errors.vehicle_type" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.vehicle_type }}</p>
                            </div>

                            <div class="grid gap-2" data-enter-ignore="true">
                                <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-vehicle-master-id">
                                    Model Kendaraan
                                    <span class="ml-1 text-rose-500">*</span>
                                </label>
                                <AsyncSelect
                                    id="service-vehicle-master-id"
                                    v-model="form.vehicle_master_id"
                                    :options="normalizedVehicleMasterOptions"
                                    :clearable="false"
                                    placeholder="Pilih model kendaraan"
                                    search-placeholder="Cari model kendaraan..."
                                    trigger-class="h-11 border-emerald-300/80 bg-emerald-50/40 text-emerald-800 hover:border-emerald-400/70 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-200 dark:hover:border-emerald-300/70"
                                    fixed-menu
                                >
                                    <template #option="{ option }">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium">
                                                {{ option?.model || option?.label || 'Model' }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                                {{ option?.brand || '-' }}
                                            </p>
                                        </div>
                                    </template>
                                </AsyncSelect>
                                <p v-if="form.errors.vehicle_master_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.vehicle_master_id }}</p>
                            </div>

                            <InputField
                                id="service-vehicle-plate"
                                v-model="form.vehicle_plate_number"
                                label="Nomor Polisi"
                                placeholder="Contoh: B1234CD"
                                :required="true"
                                :error="form.errors.vehicle_plate_number"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <InputField
                                id="service-vehicle-year"
                                v-model="form.vehicle_year"
                                label="Tahun (Opsional)"
                                type="number"
                                placeholder="Contoh: 2023"
                                :error="form.errors.vehicle_year"
                            />

                            <InputField
                                id="service-odometer"
                                v-model="form.odometer"
                                label="Odometer (KM) (Opsional)"
                                type="number"
                                placeholder="Contoh: 12000"
                                :error="form.errors.odometer"
                            />
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Detail Servis</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Kondisi dan estimasi opsional</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-2" data-enter-ignore="true">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-date">
                                Tanggal Servis
                                <span class="ml-1 text-rose-500">*</span>
                            </label>
                            <DatePicker
                                id="service-date"
                                v-model="form.service_date"
                                placeholder="Pilih tanggal servis"
                                :clearable="false"
                                :hide-input-icon="true"
                                appearance="field"
                            />
                            <p v-if="form.errors.service_date" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.service_date }}</p>
                        </div>

                        <div class="grid gap-2">
                            <InputField
                                id="service-estimated-days"
                                v-model="form.estimated_days"
                                label="Estimasi Pengerjaan (Hari) (Opsional)"
                                type="number"
                                placeholder="Contoh: 2"
                                :error="form.errors.estimated_days"
                            />
                            <p
                                v-if="estimatedFinishPreview"
                                class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                Estimasi selesai: {{ estimatedFinishPreview }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-vehicle-condition">
                                Kondisi Kendaraan Masuk (Opsional)
                            </label>
                            <textarea
                                id="service-vehicle-condition"
                                v-model="form.vehicle_condition"
                                rows="4"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                                placeholder="Contoh: body mulus, ban belakang tipis, rem depan kurang pakem"
                            />
                            <p v-if="form.errors.vehicle_condition" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.vehicle_condition }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="service-complaint">
                                Keluhan Pelanggan
                                <span class="ml-1 text-rose-500">*</span>
                            </label>
                            <textarea
                                id="service-complaint"
                                v-model="form.complaint"
                                rows="4"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                                placeholder="Contoh: Mesin berisik saat langsam"
                            />
                            <p v-if="form.errors.complaint" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.complaint }}</p>
                        </div>
                    </div>
                </section>

                <p
                    v-if="errors?.create_service_order && !form.errors.create_service_order"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_service_order }}
                </p>
            </form>
        </div>

        <div class="shrink-0 border-t border-slate-200 bg-white px-5 py-3 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="emit('close')"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    form="service-order-form"
                    class="inline-flex min-w-36 cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Servis' }}
                </button>
            </div>
        </div>
    </article>
</template>
