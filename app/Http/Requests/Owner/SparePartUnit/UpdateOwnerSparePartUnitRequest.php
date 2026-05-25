<?php

namespace App\Http\Requests\Owner\SparePartUnit;

use Illuminate\Validation\Rule;

class UpdateOwnerSparePartUnitRequest extends StoreOwnerSparePartUnitRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $unitId = (string) $this->route('sparepart_unit', '');

        return [
            'name' => [
                'required',
                'string',
                'max:30',
                Rule::unique('spare_part_units', 'name')
                    ->ignore($unitId, 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')),
            ],
            'symbol' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
