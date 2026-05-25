<?php

namespace App\Http\Requests\Platform\Plan;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:plans,slug'],
            'max_workshops' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_users_per_ws' => ['required', 'integer', 'min:1', 'max:100000'],
            'has_ai_feature' => ['nullable', 'boolean'],
            'has_notification' => ['nullable', 'boolean'],
            'has_loyalty' => ['nullable', 'boolean'],
            'has_trial' => ['nullable', 'boolean'],
            'trial_duration_days' => ['exclude_unless:has_trial,1', 'nullable', 'integer', 'min:1', 'max:365', 'required_if:has_trial,1'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:36'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'menu_ids' => ['nullable', 'array'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
        ];
    }
}


