<?php

namespace App\Http\Requests\Owner\User;

use App\Models\Workshop;
use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateOwnerUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $ignoreUserId = trim((string) $this->route('user', ''));

        return [
            'workshop_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('users', 'email')->ignore($ignoreUserId, 'id'),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'role' => ['required', 'string', 'in:admin,mekanik'],
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
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh user lain.',
            'password.min' => 'Password minimal 8 karakter.',
            'role.required' => 'Tipe user wajib dipilih.',
            'role.in' => 'Tipe user harus Admin atau Mekanik.',
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
            'email' => $this->normalizeEmail((string) $this->input('email', '')),
            'password' => $this->normalizeNullableString((string) $this->input('password', '')),
            'role' => $this->normalizeRole((string) $this->input('role', '')),
        ]);
    }

    private function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    private function normalizeRole(string $value): string
    {
        $normalizedRole = strtolower(trim($value));

        return in_array($normalizedRole, ['admin', 'mekanik'], true)
            ? $normalizedRole
            : '';
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
