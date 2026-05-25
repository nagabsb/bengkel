<?php

namespace App\Http\Requests\Platform\Menu;

use Illuminate\Foundation\Http\FormRequest;

class ReorderSystemMenuRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer'],
            'source_id' => ['required', 'integer', 'exists:menus,id'],
            'target_id' => ['required', 'integer', 'different:source_id', 'exists:menus,id'],
        ];
    }
}
