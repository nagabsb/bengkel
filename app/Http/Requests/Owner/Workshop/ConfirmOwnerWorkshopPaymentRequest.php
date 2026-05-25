<?php

namespace App\Http\Requests\Owner\Workshop;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmOwnerWorkshopPaymentRequest extends FormRequest
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
            'order_id' => ['required', 'string', 'max:100'],
            'silent' => ['nullable', 'boolean'],
        ];
    }
}
