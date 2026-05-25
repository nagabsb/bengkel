<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class SyncPlatformPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.tenants.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'role_permissions' => ['required', 'array'],
            'role_permissions.*' => ['array'],
            'role_permissions.*.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
