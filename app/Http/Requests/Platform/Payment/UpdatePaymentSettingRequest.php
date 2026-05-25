<?php

namespace App\Http\Requests\Platform\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.billing.manage');
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'midtrans_enabled' => ['nullable', 'boolean'],
            'midtrans_environment' => ['nullable', Rule::requiredIf(fn (): bool => $this->boolean('midtrans_enabled')), Rule::in(['sandbox', 'production'])],
            'midtrans_merchant_id' => ['nullable', Rule::requiredIf(fn (): bool => $this->boolean('midtrans_enabled')), 'string', 'max:100'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'manual_payment_enabled' => ['nullable', 'boolean'],
            'manual_providers' => ['nullable', 'array'],
            'manual_providers.*.id' => ['nullable', 'integer', 'exists:platform_manual_payment_providers,id'],
            'manual_providers.*.provider_name' => ['nullable', 'string', 'max:100'],
            'manual_providers.*.account_name' => ['nullable', 'string', 'max:100'],
            'manual_providers.*.account_number' => ['nullable', 'string', 'max:100'],
            'manual_providers.*.notes' => ['nullable', 'string', 'max:500'],
            'manual_providers.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
