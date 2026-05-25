<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ExportPlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tenant_search' => ['nullable', 'string', 'max:150'],
            'tenant_sort_by' => ['nullable', 'string', 'in:name,code,is_active,created_at'],
            'tenant_sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
