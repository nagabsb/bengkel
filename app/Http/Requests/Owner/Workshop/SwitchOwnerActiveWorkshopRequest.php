<?php

namespace App\Http\Requests\Owner\Workshop;

use App\Models\Workshop;
use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;

class SwitchOwnerActiveWorkshopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('owner.dashboard.view');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));

        return [
            'workshop_id' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                    $normalizedWorkshopId = trim((string) $value);
                    if ($normalizedWorkshopId === '') {
                        $fail('Bengkel tujuan wajib dipilih.');

                        return;
                    }

                    if (OwnerWorkshopSwitcherService::isAllWorkshopsId($normalizedWorkshopId)) {
                        return;
                    }

                    $exists = Workshop::query()
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->where('id', $normalizedWorkshopId)
                        ->exists();

                    if (! $exists) {
                        $fail('Bengkel tidak ditemukan atau tidak aktif.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workshop_id.required' => 'Bengkel tujuan wajib dipilih.',
            'workshop_id.exists' => 'Bengkel tidak ditemukan atau tidak aktif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'workshop_id' => trim((string) $this->input('workshop_id', '')),
        ]);
    }
}
