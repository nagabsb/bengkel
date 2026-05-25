<?php

namespace App\Http\Requests\Owner\Booking;

use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreOwnerBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('bookings.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $activeWorkshopId = (string) $this->attributes->get('tenant_workshop_id', $tenantId);
        $requestedWorkshopId = trim((string) $this->input('workshop_id', ''));
        $targetWorkshopId = $requestedWorkshopId !== ''
            ? $requestedWorkshopId
            : (! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId) ? trim($activeWorkshopId) : '');
        $customerRuleSet = ['nullable', 'string'];
        $vehicleRuleSet = ['nullable', 'string'];
        $vehicleMasterRuleSet = ['nullable', 'string'];

        if (Schema::hasTable('customers')) {
            $customerRuleSet[] = Rule::exists('customers', 'id')->where(function ($query) use ($tenantId, $targetWorkshopId): void {
                $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true);

                if ($this->hasSoftDeleteColumn('customers')) {
                    $query->whereNull('deleted_at');
                }

                if (
                    $this->hasCustomerWorkshopScope()
                    && $targetWorkshopId !== ''
                    && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($targetWorkshopId)
                ) {
                    $query->where('workshop_id', $targetWorkshopId);
                }
            });
        }

        if (Schema::hasTable('customer_vehicles')) {
            $vehicleRuleSet[] = Rule::exists('customer_vehicles', 'id')->where(function ($query) use ($tenantId, $targetWorkshopId): void {
                $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true);

                if ($this->hasSoftDeleteColumn('customer_vehicles')) {
                    $query->whereNull('deleted_at');
                }

                if (
                    $this->hasCustomerWorkshopScope()
                    && $targetWorkshopId !== ''
                    && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($targetWorkshopId)
                    && Schema::hasTable('customers')
                ) {
                    $query->whereIn('customer_id', function ($customerQuery) use ($tenantId, $targetWorkshopId): void {
                        $customerQuery
                            ->select('id')
                            ->from('customers')
                            ->where('tenant_id', $tenantId)
                            ->where('is_active', true)
                            ->where('workshop_id', $targetWorkshopId);

                        if ($this->hasSoftDeleteColumn('customers')) {
                            $customerQuery->whereNull('deleted_at');
                        }
                    });
                }
            });
        }

        if (Schema::hasTable('tenant_vehicle_masters')) {
            $vehicleMasterRuleSet[] = Rule::exists('tenant_vehicle_masters', 'id')->where(
                fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->when(
                        $this->hasSoftDeleteColumn('tenant_vehicle_masters'),
                        fn ($vehicleMasterQuery) => $vehicleMasterQuery->whereNull('deleted_at'),
                    ),
            );
        }

        return [
            'workshop_id' => [
                'required',
                'string',
                Rule::exists('workshops', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->when(
                            $this->hasSoftDeleteColumn('workshops'),
                            fn ($workshopQuery) => $workshopQuery->whereNull('deleted_at'),
                        ),
                ),
            ],
            'booking_date' => ['required', 'date'],
            'booking_time' => ['nullable', 'date_format:H:i'],
            'customer_id' => $customerRuleSet,
            'customer_vehicle_id' => $vehicleRuleSet,
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'vehicle_master_id' => $vehicleMasterRuleSet,
            'vehicle_type' => ['nullable', 'string', Rule::in(['motor', 'mobil'])],
            'vehicle_brand' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:20'],
            'complaint' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workshop_id.required' => 'Bengkel tujuan wajib dipilih.',
            'workshop_id.exists' => 'Bengkel tujuan tidak valid atau tidak aktif.',
            'booking_date.required' => 'Tanggal booking wajib diisi.',
            'booking_date.date' => 'Format tanggal booking tidak valid.',
            'booking_time.date_format' => 'Format jam booking harus HH:mm.',
            'customer_id.exists' => 'Pelanggan tidak valid untuk cabang aktif.',
            'customer_vehicle_id.exists' => 'Kendaraan pelanggan tidak valid untuk cabang aktif.',
            'customer_name.required_without' => 'Nama pelanggan wajib diisi jika belum memilih pelanggan.',
            'vehicle_master_id.exists' => 'Master kendaraan tidak valid atau sudah nonaktif.',
            'vehicle_type.in' => 'Jenis kendaraan harus motor atau mobil.',
            'complaint.required' => 'Keluhan awal wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $activeWorkshopId = (string) $this->attributes->get('tenant_workshop_id', $tenantId);
        $requestedWorkshopId = trim((string) $this->input('workshop_id', ''));
        $defaultWorkshopId = ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId)
            ? trim($activeWorkshopId)
            : '';
        $resolvedWorkshopId = $requestedWorkshopId !== '' ? $requestedWorkshopId : $defaultWorkshopId;
        $customerId = $this->normalizeNullableString((string) $this->input('customer_id', ''));
        $selectedCustomer = $this->resolveSelectedCustomer($tenantId, $resolvedWorkshopId, $customerId);
        $normalizedCustomerVehicleId = $this->normalizeNullableString((string) $this->input('customer_vehicle_id', ''));
        if ($normalizedCustomerVehicleId === '__new_vehicle__') {
            $normalizedCustomerVehicleId = null;
        }
        $hasSelectedExistingVehicle = $normalizedCustomerVehicleId !== null && $normalizedCustomerVehicleId !== '';

        $this->merge([
            'workshop_id' => $resolvedWorkshopId,
            'booking_date' => $this->normalizeDateInput($this->input('booking_date')),
            'booking_time' => $this->normalizeTimeInput($this->input('booking_time')),
            'customer_id' => $customerId,
            'customer_vehicle_id' => $normalizedCustomerVehicleId,
            'customer_name' => $this->normalizeNullableString((string) ($selectedCustomer?->name ?? $this->input('customer_name', ''))),
            'customer_phone' => $this->normalizeNullableString((string) ($selectedCustomer?->phone ?? $this->input('customer_phone', ''))),
            'vehicle_master_id' => $hasSelectedExistingVehicle
                ? null
                : $this->normalizeNullableString((string) $this->input('vehicle_master_id', '')),
            'vehicle_type' => $hasSelectedExistingVehicle
                ? null
                : $this->normalizeVehicleType((string) $this->input('vehicle_type', '')),
            'vehicle_brand' => $hasSelectedExistingVehicle
                ? null
                : $this->normalizeNullableString((string) $this->input('vehicle_brand', '')),
            'vehicle_model' => $hasSelectedExistingVehicle
                ? null
                : $this->normalizeNullableString((string) $this->input('vehicle_model', '')),
            'vehicle_plate_number' => $hasSelectedExistingVehicle
                ? null
                : $this->normalizeNullableString((string) $this->input('vehicle_plate_number', '')),
            'complaint' => trim((string) $this->input('complaint', '')),
            'notes' => $this->normalizeNullableString((string) $this->input('notes', '')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
            $customerId = trim((string) $this->input('customer_id', ''));
            $customerVehicleId = trim((string) $this->input('customer_vehicle_id', ''));
            $hasManualVehicleInput = $this->hasManualVehicleInput();

            if ($customerVehicleId !== '') {
                if ($customerId === '') {
                    $validator->errors()->add('customer_vehicle_id', 'Pilih pelanggan terlebih dahulu sebelum memilih kendaraan.');

                    return;
                }

                if (! Schema::hasTable('customer_vehicles')) {
                    return;
                }

                $vehicle = CustomerVehicle::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $customerVehicleId)
                    ->where('is_active', true)
                    ->when($this->hasSoftDeleteColumn('customer_vehicles'), function ($query): void {
                        $query->whereNull('deleted_at');
                    })
                    ->first(['id', 'customer_id']);

                if (! $vehicle || (string) $vehicle->customer_id !== $customerId) {
                    $validator->errors()->add('customer_vehicle_id', 'Kendaraan harus sesuai dengan pelanggan yang dipilih.');
                }
            }

            if (! $hasManualVehicleInput || $customerVehicleId !== '') {
                return;
            }

            if (! Schema::hasTable('customers') || ! Schema::hasTable('customer_vehicles')) {
                $validator->errors()->add('customer_vehicle_id', 'Data kendaraan belum dapat disimpan. Jalankan migrasi terbaru.');

                return;
            }

            $vehiclePlateNumber = trim((string) $this->input('vehicle_plate_number', ''));
            if ($vehiclePlateNumber === '') {
                $validator->errors()->add('vehicle_plate_number', 'Nomor polisi wajib diisi saat menambah kendaraan baru.');
            }

            $vehicleMasterId = trim((string) $this->input('vehicle_master_id', ''));
            $vehicleBrand = trim((string) $this->input('vehicle_brand', ''));
            $vehicleModel = trim((string) $this->input('vehicle_model', ''));

            if ($vehicleMasterId === '' && ($vehicleBrand === '' || $vehicleModel === '')) {
                $validator->errors()->add('vehicle_master_id', 'Model kendaraan wajib dipilih saat menambah kendaraan baru.');
            }
        });
    }

    private function resolveSelectedCustomer(string $tenantId, string $workshopId, ?string $customerId): ?Customer
    {
        if ($customerId === null || $customerId === '' || ! Schema::hasTable('customers')) {
            return null;
        }

        $query = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $customerId)
            ->where('is_active', true);

        if (
            $this->hasCustomerWorkshopScope()
            && $workshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($workshopId)
        ) {
            $query->where('workshop_id', $workshopId);
        }

        return $query->first(['id', 'name', 'phone']);
    }

    private function hasCustomerWorkshopScope(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasColumn('customers', 'workshop_id');
    }

    private function hasSoftDeleteColumn(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'deleted_at');
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeVehicleType(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, ['motor', 'mobil'], true) ? $normalized : null;
    }

    private function normalizeDateInput(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($rawValue)->format('Y-m-d');
        } catch (\Throwable) {
            return $rawValue;
        }
    }

    private function normalizeTimeInput(mixed $value): ?string
    {
        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $rawValue) === 1) {
            return $rawValue;
        }

        try {
            return \Carbon\Carbon::parse($rawValue)->format('H:i');
        } catch (\Throwable) {
            return $rawValue;
        }
    }

    private function hasManualVehicleInput(): bool
    {
        $fields = [
            'vehicle_master_id',
            'vehicle_type',
            'vehicle_brand',
            'vehicle_model',
            'vehicle_plate_number',
        ];

        foreach ($fields as $field) {
            if ($this->normalizeNullableString((string) $this->input($field, '')) !== null) {
                return true;
            }
        }

        return false;
    }
}
