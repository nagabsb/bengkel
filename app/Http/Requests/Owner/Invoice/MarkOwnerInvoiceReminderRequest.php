<?php

namespace App\Http\Requests\Owner\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MarkOwnerInvoiceReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('receivables.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
