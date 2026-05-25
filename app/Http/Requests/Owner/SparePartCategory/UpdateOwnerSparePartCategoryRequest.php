<?php

namespace App\Http\Requests\Owner\SparePartCategory;

use Illuminate\Validation\Rule;

class UpdateOwnerSparePartCategoryRequest extends StoreOwnerSparePartCategoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $categoryId = (string) $this->route('sparepart_category', '');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('spare_part_categories', 'name')
                    ->ignore($categoryId, 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

