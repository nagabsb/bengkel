<?php

namespace App\Http\Requests\Platform\Plan;

use Illuminate\Foundation\Http\FormRequest;

class TogglePlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.billing.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}

