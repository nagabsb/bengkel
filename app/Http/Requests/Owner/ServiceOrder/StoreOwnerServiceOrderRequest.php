<?php

namespace App\Http\Requests\Owner\ServiceOrder;

use App\Models\Workshop;
use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class StoreOwnerServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('service_orders.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $targetWorkshopId = trim((string) $this->input('workshop_id', ''));
        $vehicleMasterRules = ['nullable', 'string'];
        if (Schema::hasTable('tenant_vehicle_masters')) {
            $vehicleMasterRules[] = Rule::exists('tenant_vehicle_masters', 'id')->where(
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
            'workshop_id' => ['nullable', 'string'],
            'customer_id' => [
                'nullable',
                'string',
                Rule::exists('customers', 'id')->where(function ($query) use ($tenantId, $targetWorkshopId): void {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true);

                    if ($this->hasSoftDeleteColumn('customers')) {
                        $query->whereNull('deleted_at');
                    }

                    if (
                        $targetWorkshopId !== ''
                        && Schema::hasTable('customers')
                        && Schema::hasColumn('customers', 'workshop_id')
                    ) {
                        $query->where('workshop_id', $targetWorkshopId);
                    }
                }),
            ],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email:rfc', 'max:150'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'vehicle_id' => [
                'nullable',
                'string',
                Rule::exists('customer_vehicles', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->when(
                        $this->hasSoftDeleteColumn('customer_vehicles'),
                        fn ($vehicleQuery) => $vehicleQuery->whereNull('deleted_at'),
                    )),
            ],
            'vehicle_master_id' => $vehicleMasterRules,
            'vehicle_type' => ['nullable', 'string', Rule::in(['motor', 'mobil'])],
            'vehicle_brand' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_variant' => ['nullable', 'string', 'max:100'],
            'vehicle_plate_number' => ['required_without:vehicle_id', 'nullable', 'string', 'max:20'],
            'vehicle_year' => ['nullable', 'integer', 'between:1900,2100'],
            'vehicle_notes' => ['nullable', 'string', 'max:500'],
            'service_date' => ['required', 'date'],
            'vehicle_condition' => ['nullable', 'string', 'max:1000'],
            'estimated_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'complaint' => ['required', 'string', 'max:1000'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
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
            'customer_name.required_without' => 'Nama pelanggan wajib diisi jika belum memilih pelanggan.',
            'customer_email.email' => 'Format email pelanggan tidak valid.',
            'vehicle_master_id.required_without' => 'Model kendaraan wajib dipilih dari master kendaraan.',
            'vehicle_plate_number.required_without' => 'Nomor polisi wajib diisi jika belum memilih kendaraan.',
            'vehicle_type.in' => 'Jenis kendaraan harus motor atau mobil.',
            'service_date.required' => 'Tanggal servis wajib diisi.',
            'complaint.required' => 'Keluhan pelanggan wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $activeWorkshopId = (string) $this->attributes->get('tenant_workshop_id', $tenantId);
        $requestedWorkshopId = trim((string) $this->input('workshop_id', ''));
        $defaultWorkshopId = ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId)
            && $this->hasActiveWorkshop($tenantId, $activeWorkshopId)
            ? trim($activeWorkshopId)
            : '';

        $this->merge([
            'workshop_id' => $requestedWorkshopId !== '' ? $requestedWorkshopId : $defaultWorkshopId,
            'customer_id' => $this->normalizeNullableString((string) $this->input('customer_id', '')),
            'customer_name' => $this->normalizeNullableString((string) $this->input('customer_name', '')),
            'customer_phone' => $this->normalizeNullableString((string) $this->input('customer_phone', '')),
            'customer_email' => $this->normalizeNullableString(strtolower((string) $this->input('customer_email', ''))),
            'customer_address' => $this->normalizeNullableString((string) $this->input('customer_address', '')),
            'vehicle_id' => $this->normalizeNullableString((string) $this->input('vehicle_id', '')),
            'vehicle_master_id' => $this->normalizeNullableString((string) $this->input('vehicle_master_id', '')),
            'vehicle_type' => $this->normalizeVehicleType((string) $this->input('vehicle_type', '')),
            'vehicle_brand' => $this->normalizeNullableString((string) $this->input('vehicle_brand', '')),
            'vehicle_model' => $this->normalizeNullableString((string) $this->input('vehicle_model', '')),
            'vehicle_variant' => $this->normalizeNullableString((string) $this->input('vehicle_variant', '')),
            'vehicle_plate_number' => $this->normalizeNullableString((string) $this->input('vehicle_plate_number', '')),
            'vehicle_year' => $this->normalizeNullableInteger($this->input('vehicle_year')),
            'vehicle_notes' => $this->normalizeNullableString((string) $this->input('vehicle_notes', '')),
            'vehicle_condition' => $this->normalizeNullableString((string) $this->input('vehicle_condition', '')),
            'estimated_days' => $this->normalizeNullableInteger($this->input('estimated_days')),
            'complaint' => $this->normalizeNullableString((string) $this->input('complaint', '')),
            'odometer' => $this->normalizeNullableInteger($this->input('odometer')),
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeVehicleType(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, ['motor', 'mobil'], true) ? $normalized : null;
    }

    private function hasActiveWorkshop(string $tenantId, string $workshopId): bool
    {
        if ($tenantId === '' || $workshopId === '' || ! Schema::hasTable('workshops')) {
            return false;
        }

        return Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $workshopId)
            ->where('is_active', true)
            ->exists();
    }

    private function hasSoftDeleteColumn(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'deleted_at');
    }
}
