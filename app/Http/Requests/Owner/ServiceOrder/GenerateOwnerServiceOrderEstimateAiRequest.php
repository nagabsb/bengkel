<?php

namespace App\Http\Requests\Owner\ServiceOrder;

use Illuminate\Foundation\Http\FormRequest;

class GenerateOwnerServiceOrderEstimateAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('service_orders.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'context_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'context_note' => $this->normalizeNullableString((string) $this->input('context_note', '')),
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
