<?php

namespace App\Http\Requests\Platform\Menu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemMenuRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'label' => ['required', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:200'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
