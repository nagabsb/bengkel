<?php

namespace App\Http\Requests\Platform\VehicleMaster;

use Illuminate\Foundation\Http\FormRequest;

class ImportPlatformVehicleMasterRequest extends FormRequest
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
            'import_file' => [
                'required',
                'file',
                'max:10240',
                'mimes:json',
                'mimetypes:application/json,text/plain,application/octet-stream',
            ],
            'deactivate_missing' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'import_file.required' => 'File JSON wajib dipilih.',
            'import_file.file' => 'Upload file JSON tidak valid.',
            'import_file.max' => 'Ukuran file maksimal 10MB.',
            'import_file.mimes' => 'File harus berformat .json.',
            'import_file.mimetypes' => 'Konten file bukan JSON yang valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'deactivate_missing' => (bool) $this->boolean('deactivate_missing'),
        ]);
    }
}

