<?php

namespace App\Http\Requests\Owner\Expense;

use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateOwnerExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('expenses.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', $this->route('tenant'));
        $categoryRules = ['required', 'string', 'max:80'];

        if (Schema::hasTable('expense_categories')) {
            $categoryRules[] = Rule::exists('expense_categories', 'name')->where(
                fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            );
        }

        return [
            'workshop_id' => [
                'required',
                'string',
                Rule::exists('workshops', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true),
                ),
            ],
            'expense_date' => ['required', 'date'],
            'category' => $categoryRules,
            'description' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workshop_id.required' => 'Bengkel tujuan wajib dipilih.',
            'workshop_id.exists' => 'Bengkel tujuan tidak valid atau tidak aktif.',
            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'expense_date.date' => 'Format tanggal pengeluaran tidak valid.',
            'category.required' => 'Kategori pengeluaran wajib diisi.',
            'category.exists' => 'Kategori pengeluaran harus dipilih dari master kategori.',
            'description.required' => 'Deskripsi pengeluaran wajib diisi.',
            'amount.required' => 'Nominal pengeluaran wajib diisi.',
            'amount.min' => 'Nominal pengeluaran minimal Rp 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $activeWorkshopId = (string) $this->attributes->get('tenant_workshop_id', $tenantId);
        $requestedWorkshopId = trim((string) $this->input('workshop_id', ''));
        $defaultWorkshopId = ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId)
            ? trim($activeWorkshopId)
            : '';

        $this->merge([
            'workshop_id' => $requestedWorkshopId !== '' ? $requestedWorkshopId : $defaultWorkshopId,
            'expense_date' => $this->normalizeDateInput($this->input('expense_date')),
            'category' => trim((string) $this->input('category', '')),
            'description' => trim((string) $this->input('description', '')),
            'reference_number' => $this->normalizeNullableString((string) $this->input('reference_number', '')),
            'notes' => $this->normalizeNullableString((string) $this->input('notes', '')),
            'amount' => $this->normalizeAmount($this->input('amount')),
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
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
}
