<?php

namespace App\Http\Requests\EstimateApproval;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondServiceOrderEstimateApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'approver_name' => ['required', 'string', 'max:150'],
            'approver_phone' => ['nullable', 'string', 'max:30'],
            'approval_note' => ['nullable', 'string', 'max:1000'],
            'signature' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Aksi approval wajib dipilih.',
            'action.in' => 'Aksi approval tidak valid.',
            'approver_name.required' => 'Nama penyetuju wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => strtolower(trim((string) $this->input('action', ''))),
            'approver_name' => $this->normalizeNullableString((string) $this->input('approver_name', '')),
            'approver_phone' => $this->normalizeNullableString((string) $this->input('approver_phone', '')),
            'approval_note' => $this->normalizeNullableString((string) $this->input('approval_note', '')),
            'rejection_reason' => $this->normalizeNullableString((string) $this->input('rejection_reason', '')),
            'signature' => $this->normalizeNullableString((string) $this->input('signature', '')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $action = strtolower(trim((string) $this->input('action', '')));

            if ($action === 'approve') {
                $signature = trim((string) $this->input('signature', ''));
                if ($signature === '' || ! str_starts_with($signature, 'data:image/png;base64,')) {
                    $validator->errors()->add('signature', 'Tanda tangan digital wajib diisi untuk menyetujui estimasi.');
                }
            }

            if ($action === 'reject') {
                $reason = trim((string) $this->input('rejection_reason', ''));
                if ($reason === '') {
                    $validator->errors()->add('rejection_reason', 'Alasan penolakan wajib diisi.');
                }
            }
        });
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}

