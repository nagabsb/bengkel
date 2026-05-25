<?php

namespace App\Http\Requests\Owner\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnerInvoiceDueDateRequest extends FormRequest
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
        return [
            'due_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.required' => 'Jatuh tempo wajib diisi.',
            'due_date.date' => 'Format jatuh tempo tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'due_date' => $this->normalizeDateInput($this->input('due_date')),
        ]);
    }

    private function normalizeDateInput(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($rawValue)->format('Y-m-d');
        } catch (\Throwable) {
            return $rawValue;
        }
    }
}
