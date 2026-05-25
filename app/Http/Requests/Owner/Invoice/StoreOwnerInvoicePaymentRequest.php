<?php

namespace App\Http\Requests\Owner\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('invoice_payments.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'method' => [
                'required',
                'string',
                Rule::in(['cash', 'transfer', 'qris', 'debit', 'credit', 'other']),
            ],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_at.required' => 'Tanggal pembayaran wajib diisi.',
            'paid_at.date' => 'Format tanggal pembayaran tidak valid.',
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.integer' => 'Nominal pembayaran harus berupa angka.',
            'amount.min' => 'Nominal pembayaran minimal Rp 1.',
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'paid_at' => $this->normalizeDateInput($this->input('paid_at')),
            'amount' => $this->normalizeAmount($this->input('amount')),
            'method' => strtolower(trim((string) $this->input('method', 'cash'))),
            'reference_number' => $this->normalizeNullableString((string) $this->input('reference_number', '')),
            'notes' => $this->normalizeNullableString((string) $this->input('notes', '')),
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

    private function normalizeAmount(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        $normalized = preg_replace('/[^\d]/', '', (string) $value);
        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
