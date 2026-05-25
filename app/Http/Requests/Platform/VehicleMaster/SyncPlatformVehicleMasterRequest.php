<?php

namespace App\Http\Requests\Platform\VehicleMaster;

use Illuminate\Foundation\Http\FormRequest;

class SyncPlatformVehicleMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.tenants.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sync_path' => ['required', 'string', 'max:255'],
            'deactivate_missing' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sync_path.required' => 'Path file JSON wajib diisi.',
            'sync_path.max' => 'Path file JSON maksimal 255 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $path = trim(strip_tags((string) $this->input('sync_path', '')));

        $this->merge([
            'sync_path' => $path,
            'deactivate_missing' => (bool) $this->boolean('deactivate_missing'),
        ]);
    }
}

