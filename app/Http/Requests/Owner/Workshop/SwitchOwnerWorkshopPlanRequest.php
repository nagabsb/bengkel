<?php

namespace App\Http\Requests\Owner\Workshop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchOwnerWorkshopPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_price_id' => ['required', 'integer', 'exists:plan_prices,id'],
            'payment_method' => ['required', Rule::in(['midtrans', 'manual'])],
            'manual_provider_id' => ['exclude_unless:payment_method,manual', 'nullable', 'integer', 'required_if:payment_method,manual', 'exists:platform_manual_payment_providers,id'],
        ];
    }
}
