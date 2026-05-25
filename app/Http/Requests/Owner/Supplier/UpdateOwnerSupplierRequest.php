<?php

namespace App\Http\Requests\Owner\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnerSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('suppliers.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'pic_phone' => ['nullable', 'string', 'max:30'],
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
            'name.required' => 'Nama supplier wajib diisi.',
            'email.email' => 'Format email tidak valid. Kosongkan jika tidak digunakan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'phone' => $this->normalizeNullableString((string) $this->input('phone', '')),
            'email' => $this->normalizeNullableString((string) $this->input('email', '')),
            'address' => $this->normalizeNullableString((string) $this->input('address', '')),
            'pic_name' => $this->normalizeNullableString((string) $this->input('pic_name', '')),
            'pic_phone' => $this->normalizeNullableString((string) $this->input('pic_phone', '')),
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

