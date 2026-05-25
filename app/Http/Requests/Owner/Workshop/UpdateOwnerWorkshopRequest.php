<?php

namespace App\Http\Requests\Owner\Workshop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerWorkshopRequest extends FormRequest
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
        $tenantId = (string) $this->attributes->get('tenant_id', $this->route('tenant'));
        $workshopId = (string) $this->route('workshop');

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('workshops', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($workshopId, 'id'),
            ],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama bengkel sudah digunakan di tenant ini.',
            'phone.required' => 'No. HP bengkel wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'code' => $this->normalizeCode((string) $this->input('code', '')),
            'phone' => trim((string) $this->input('phone', '')),
            'address' => $this->normalizeNullableString((string) $this->input('address', '')),
        ]);
    }

    private function normalizeCode(string $code): ?string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/[^A-Z0-9-]/', '', $normalized);

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return $normalized;
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
