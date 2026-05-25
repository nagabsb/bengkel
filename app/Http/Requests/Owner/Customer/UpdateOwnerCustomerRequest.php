<?php

namespace App\Http\Requests\Owner\Customer;

use App\Models\Workshop;
use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class UpdateOwnerCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('customers.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'workshop_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
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
            'name.required' => 'Nama customer wajib diisi.',
            'email.email' => 'Format email tidak valid. Kosongkan jika tidak digunakan.',
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
            'name' => trim((string) $this->input('name', '')),
            'phone' => $this->normalizeNullableString((string) $this->input('phone', '')),
            'email' => $this->normalizeNullableString((string) $this->input('email', '')),
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
}
