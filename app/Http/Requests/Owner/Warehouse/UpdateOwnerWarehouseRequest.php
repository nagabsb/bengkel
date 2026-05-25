<?php

namespace App\Http\Requests\Owner\Warehouse;

use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('warehouses.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', $this->route('tenant'));
        $workshopId = trim((string) $this->input('workshop_id', ''));
        $warehouseId = (string) $this->route('warehouse');

        return [
            'workshop_id' => [
                'required',
                'string',
                Rule::exists('workshops', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true),
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('warehouses', 'name')
                    ->ignore($warehouseId)
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('workshop_id', $workshopId)
                        ->whereNull('deleted_at')),
            ],
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('warehouses', 'code')
                    ->ignore($warehouseId)
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('workshop_id', $workshopId)
                        ->whereNull('deleted_at')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
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
            'name.required' => 'Nama gudang wajib diisi.',
            'name.unique' => 'Nama gudang sudah dipakai di cabang aktif ini.',
            'code.unique' => 'Kode gudang sudah dipakai di cabang aktif ini.',
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

        $this->merge([
            'workshop_id' => $requestedWorkshopId !== '' ? $requestedWorkshopId : $defaultWorkshopId,
            'name' => trim((string) $this->input('name', '')),
            'code' => $this->normalizeNullableString((string) $this->input('code', '')),
            'address' => $this->normalizeNullableString((string) $this->input('address', '')),
            'notes' => $this->normalizeNullableString((string) $this->input('notes', '')),
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : true,
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }
}
