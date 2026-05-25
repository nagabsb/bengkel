<?php

namespace App\Http\Requests\Owner\ServiceOrder;

use Illuminate\Foundation\Http\FormRequest;

class GenerateOwnerServiceOrderDiagnosisAiRequest extends FormRequest
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
            'symptoms' => ['nullable', 'array', 'max:10'],
            'symptoms.*' => ['required', 'string', 'max:180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawSymptoms = $this->input('symptoms', []);

        if (is_string($rawSymptoms)) {
            $rawSymptoms = preg_split('/[\r\n,;]+/', $rawSymptoms) ?: [];
        }

        $normalizedSymptoms = collect(is_array($rawSymptoms) ? $rawSymptoms : [])
            ->map(fn ($symptom): string => trim(strip_tags((string) $symptom)))
            ->filter(fn (string $symptom): bool => $symptom !== '')
            ->unique()
            ->values()
            ->take(10)
            ->all();

        $this->merge([
            'context_note' => $this->normalizeNullableString((string) $this->input('context_note', '')),
            'symptoms' => $normalizedSymptoms,
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim(strip_tags($value));

        return $normalized !== '' ? $normalized : null;
    }
}
