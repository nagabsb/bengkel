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
    errors: {
        type: Object,
        default: () => ({}),
    },
    workshopOptions: {
        type: Array,
        default: () => [],
    },
    customerOptions: {
        type: Array,
        default: () => [],
    },
    filteredVehicleOptions: {
        type: Array,
        default: () => [],
    },
    vehicleMasterOptions: {
        type: Array,
        default: () => [],
    },
    isWorkshopSelectable: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit']);
const firstInputId = 'owner-booking-complaint';
const newCustomerOptionValue = '__new_customer__';
const newVehicleOptionValue = '__new_vehicle__';

const normalizedCustomerOptions = computed(() => (
    props.customerOptions
        .map((customer) => {
            const value = String(customer?.id || '').trim();
            const name = String(customer?.name || '').trim();
            const phone = String(customer?.phone || '').trim();
            const address = String(customer?.address || '').trim();
            const subtitle = String(customer?.subtitle || '').trim();
            const fallbackName = name !== '' ? name : (subtitle !== '' ? subtitle : 'Pelanggan');
            const label = address !== '' ? `${fallbackName} (${address})` : fallbackName;

            return {
                value,
                label,
                name,
                phone,
                subtitle,
                address,
                raw: customer,
            };
        })
        .filter((customer) => customer.value !== '')
));

const customerSelectOptions = computed(() => ([
    {
        value: newCustomerOptionValue,
        label: 'Tambah pelanggan baru',
        subtitle: 'Isi data pelanggan manual',
    },
    ...normalizedCustomerOptions.value,
]));

const hasSelectedCustomer = computed(() => {
    const customerId = String(props.form.customer_id || '').trim();
    return customerId !== '' && customerId !== newCustomerOptionValue;
});

const isNewCustomerMode = computed(() => String(props.form.customer_id || '').trim() === newCustomerOptionValue);

const normalizedVehicleHistoryOptions = computed(() => (
    Array.isArray(props.filteredVehicleOptions)
        ? props.filteredVehicleOptions
            .map((vehicle) => {
                const value = String(vehicle?.id || '').trim();
                const vehicleTypeLabel = String(vehicle?.vehicle_type_label || 'Kendaraan').trim();
                const displayName = String(vehicle?.display_name || '').trim() || 'Kendaraan';
                const plateNumber = String(vehicle?.plate_number || '').trim();

                return {
                    value,
                    label: `${vehicleTypeLabel} - ${displayName}${plateNumber !== '' ? ` (${plateNumber})` : ''}`,
                    subtitle: String(vehicle?.year || '').trim(),
                    raw: vehicle,
                };
            })
            .filter((vehicle) => vehicle.value !== '')
        : []
));

const vehicleSelectOptions = computed(() => ([
    {
        value: newVehicleOptionValue,
        label: 'Tambah kendaraan baru',
        subtitle: 'Isi data kendaraan manual',
    },
    ...normalizedVehicleHistoryOptions.value,
]));

const hasVehicleHistoryOptions = computed(() => normalizedVehicleHistoryOptions.value.length > 0);
const isNewVehicleMode = computed(() => String(props.form.customer_vehicle_id || '').trim() === newVehicleOptionValue);
const shouldShowManualVehicleSection = computed(() => isNewCustomerMode.value || isNewVehicleMode.value);
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

const resetManualVehicleFields = () => {
    props.form.vehicle_master_id = null;
    props.form.vehicle_type = 'motor';
    props.form.vehicle_brand = '';
    props.form.vehicle_model = '';
    props.form.vehicle_plate_number = '';
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
    focusFirstInput();
});

watch(
    () => props.form.customer_id,
    (customerId) => {
        const normalizedCustomerId = String(customerId || '').trim();
        if (normalizedCustomerId === '') {
            props.form.customer_vehicle_id = null;
            props.form.customer_name = '';
            props.form.customer_phone = '';
            resetManualVehicleFields();
            return;
        }

        if (normalizedCustomerId === newCustomerOptionValue) {
            props.form.customer_vehicle_id = null;
            props.form.customer_name = '';
            props.form.customer_phone = '';
            resetManualVehicleFields();
            return;
        }

        const selectedCustomer = normalizedCustomerOptions.value.find((customer) => customer.value === normalizedCustomerId);
        if (!selectedCustomer) {
            props.form.customer_vehicle_id = null;
            props.form.customer_name = '';
            props.form.customer_phone = '';
            resetManualVehicleFields();
            return;
        }

        props.form.customer_vehicle_id = null;
        props.form.customer_name = selectedCustomer.name;
        props.form.customer_phone = selectedCustomer.phone;
        resetManualVehicleFields();
    },
);

watch(
    normalizedVehicleHistoryOptions,
    (vehicleOptions) => {
        const selectedVehicleId = String(props.form.customer_vehicle_id || '').trim();
        if (selectedVehicleId === '' || selectedVehicleId === newVehicleOptionValue) {
            return;
        }

        const isVehicleStillAvailable = vehicleOptions.some((vehicle) => vehicle.value === selectedVehicleId);
        if (!isVehicleStillAvailable) {
            props.form.customer_vehicle_id = null;
        }
    },
    { deep: true },
);

watch(
    () => props.form.customer_vehicle_id,
    (customerVehicleId) => {
        const selectedVehicleId = String(customerVehicleId || '').trim();

        if (isNewCustomerMode.value) {
            return;
        }

        if (selectedVehicleId === '' || selectedVehicleId === newVehicleOptionValue) {
            return;
        }

        resetManualVehicleFields();
    },
);

watch(
    () => props.form.vehicle_type,
    () => {
        if (!shouldShowManualVehicleSection.value) {
            return;
        }

        const selectedVehicleMasterId = String(props.form.vehicle_master_id || '').trim();
        if (selectedVehicleMasterId === '') {
            return;
        }

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
        if (!shouldShowManualVehicleSection.value) {
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
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                Tambah Booking Servis
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
            <form class="space-y-4" @submit.prevent="emit('submit')">
                <div v-if="isWorkshopSelectable" class="grid min-w-0 gap-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-workshop">
                        Bengkel Tujuan
                        <span class="ml-1 text-rose-500">*</span>
                    </label>
                    <AsyncSelect
                        id="owner-booking-workshop"
                        v-model="form.workshop_id"
                        :options="workshopOptions"
                        placeholder="Pilih bengkel tujuan"
                        search-placeholder="Cari bengkel..."
                        empty-text="Bengkel tidak ditemukan."
                        :clearable="false"
                        :trigger-class="form.errors.workshop_id
                            ? 'h-11 border-rose-400/80 bg-rose-50/40 text-rose-700 hover:border-rose-400 focus-visible:ring-rose-400/20 dark:border-rose-400/60 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/70 dark:focus-visible:ring-rose-300/30'
                            : 'h-11 border-slate-300/80 bg-white/80 text-slate-900 hover:border-emerald-400/60 focus-visible:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-300/60 dark:focus-visible:ring-emerald-400/20'"
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

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-date">
                            Tanggal Booking
                            <span class="ml-1 text-rose-500">*</span>
                        </label>
                        <DatePicker
                            id="owner-booking-date"
                            v-model="form.booking_date"
                            placeholder="Pilih tanggal booking"
                            :clearable="false"
                            :hide-input-icon="true"
                            appearance="field"
                        />
                        <p v-if="form.errors.booking_date" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.booking_date }}</p>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-time">
                            Jam Booking
                        </label>
                        <input
                            id="owner-booking-time"
                            type="time"
                            v-model="form.booking_time"
                            step="60"
                            class="h-11 w-full rounded-xl border border-slate-300/80 bg-white/80 px-3 text-sm tracking-wide text-slate-900 outline-none transition focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-blue-200/20 dark:bg-slate-900/80 dark:text-slate-100 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20"
                        />
                        <p v-if="form.errors.booking_time" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.booking_time }}</p>
                    </div>
                </div>

                <div class="grid gap-2" data-enter-ignore="true">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-customer-id">
                        Pilih Pelanggan
                        <span class="ml-1 text-rose-500">*</span>
                    </label>
                    <AsyncSelect
                        id="owner-booking-customer-id"
                        v-model="form.customer_id"
                        :options="customerSelectOptions"
                        placeholder="Pilih pelanggan"
                        search-placeholder="Cari pelanggan..."
                        :clearable="false"
                        trigger-class="h-11"
                        fixed-menu
                    >
                        <template #option="{ option }">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{
                                        option?.value === newCustomerOptionValue
                                            ? (option?.label || 'Tambah pelanggan baru')
                                            : (option?.raw?.name || option?.name || option?.label || 'Pelanggan')
                                    }}
                                </p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{
                                        option?.value === newCustomerOptionValue
                                            ? (option?.subtitle || '-')
                                            : (option?.raw?.subtitle || option?.subtitle || option?.raw?.address || option?.address || '-')
                                    }}
                                </p>
                            </div>
                        </template>
                    </AsyncSelect>
                    <p v-if="form.errors.customer_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.customer_id }}</p>
                </div>

                <div v-if="hasSelectedCustomer" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <InputField
                        id="owner-booking-customer-name"
                        v-model="form.customer_name"
                        label="Nama Pelanggan"
                        readonly
                        :error="form.errors.customer_name"
                    />

                    <InputField
                        id="owner-booking-customer-phone"
                        v-model="form.customer_phone"
                        label="Nomor WhatsApp / Telepon"
                        readonly
                        :error="form.errors.customer_phone"
                    />
                </div>

                <div v-if="hasSelectedCustomer" class="grid gap-2" data-enter-ignore="true">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-customer-vehicle-id">
                        Kendaraan Pelanggan (Opsional)
                    </label>
                    <AsyncSelect
                        id="owner-booking-customer-vehicle-id"
                        v-model="form.customer_vehicle_id"
                        :options="vehicleSelectOptions"
                        :clearable="true"
                        clear-text="Lewati input kendaraan"
                        placeholder="Pilih kendaraan atau tambah kendaraan baru"
                        search-placeholder="Cari kendaraan..."
                        empty-text="Data kendaraan pelanggan belum ada."
                        trigger-class="h-11"
                        fixed-menu
                    >
                        <template #option="{ option }">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{
                                        option?.value === newVehicleOptionValue
                                            ? (option?.label || 'Tambah kendaraan baru')
                                            : (option?.raw?.display_name || option?.label || 'Kendaraan')
                                    }}
                                </p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{
                                        option?.value === newVehicleOptionValue
                                            ? (option?.subtitle || '-')
                                            : (
                                                option?.raw?.plate_number
                                                    ? `${option.raw.vehicle_type_label || 'Kendaraan'} - ${option.raw.plate_number}`
                                                    : (option?.raw?.vehicle_type_label || '-')
                                            )
                                    }}
                                </p>
                            </div>
                        </template>
                    </AsyncSelect>
                    <p v-if="!hasVehicleHistoryOptions" class="text-xs text-slate-500 dark:text-slate-400">
                        Pelanggan ini belum punya histori kendaraan. Pilih "Tambah kendaraan baru" jika ingin langsung simpan data kendaraan.
                    </p>
                    <p v-if="form.errors.customer_vehicle_id" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.customer_vehicle_id }}</p>
                </div>

                <div v-else-if="isNewCustomerMode" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <InputField
                        id="owner-booking-customer-name"
                        v-model="form.customer_name"
                        label="Nama Pelanggan"
                        placeholder="Contoh: Budi Santoso"
                        :required="true"
                        :error="form.errors.customer_name"
                    />

                    <InputField
                        id="owner-booking-customer-phone"
                        v-model="form.customer_phone"
                        label="Nomor WhatsApp / Telepon"
                        placeholder="Contoh: 081234567890"
                        :error="form.errors.customer_phone"
                    />
                </div>

                <div v-if="shouldShowManualVehicleSection" class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-800/30">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Data Kendaraan Baru</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Data ini akan disimpan ke histori pelanggan.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-vehicle-type">
                                Jenis Kendaraan
                            </label>
                            <div class="relative">
                                <select
                                    id="owner-booking-vehicle-type"
                                    v-model="form.vehicle_type"
                                    class="h-11 w-full cursor-pointer appearance-none rounded-xl border border-slate-300/80 bg-white/80 px-3 pr-10 text-sm tracking-wide text-slate-900 outline-none transition focus:border-emerald-500/70 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:focus:border-emerald-300/70 dark:focus:ring-emerald-400/20"
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

                        <div class="grid gap-2 md:col-span-2" data-enter-ignore="true">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-vehicle-master-id">
                                Model Kendaraan
                            </label>
                            <AsyncSelect
                                id="owner-booking-vehicle-master-id"
                                v-model="form.vehicle_master_id"
                                :options="normalizedVehicleMasterOptions"
                                :clearable="true"
                                clear-text="Kosongkan model"
                                placeholder="Pilih model kendaraan"
                                search-placeholder="Cari model kendaraan..."
                                empty-text="Model kendaraan tidak ditemukan."
                                trigger-class="h-11"
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
                    </div>

                    <InputField
                        id="owner-booking-vehicle-plate-number"
                        v-model="form.vehicle_plate_number"
                        label="Nomor Polisi"
                        placeholder="Contoh: B1234CD"
                        :error="form.errors.vehicle_plate_number"
                    />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-complaint">
                        Keluhan Awal
                        <span class="ml-1 text-rose-500">*</span>
                    </label>
                    <textarea
                        id="owner-booking-complaint"
                        v-model="form.complaint"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                        placeholder="Contoh: Mesin susah dinyalakan saat pagi hari."
                    />
                    <p v-if="form.errors.complaint" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.complaint }}</p>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="owner-booking-notes">
                        Catatan Tambahan
                    </label>
                    <textarea
                        id="owner-booking-notes"
                        v-model="form.notes"
                        rows="2"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                        placeholder="Catatan opsional"
                    />
                    <p v-if="form.errors.notes" class="text-sm text-rose-600 dark:text-rose-300">{{ form.errors.notes }}</p>
                </div>

                <p
                    v-if="errors?.create_booking && !form.errors.create_booking"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_booking }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Booking' }}
                </button>
            </form>
        </div>
    </article>
</template>
