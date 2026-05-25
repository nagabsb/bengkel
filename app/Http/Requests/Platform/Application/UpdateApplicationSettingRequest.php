<?php

namespace App\Http\Requests\Platform\Application;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationSettingRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:100'],
            'app_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'logo_background_enabled' => ['nullable', 'boolean'],
            'logo_background_color' => ['nullable', 'regex:/^#[A-Fa-f0-9]{6}$/'],
        ];
    }
}
