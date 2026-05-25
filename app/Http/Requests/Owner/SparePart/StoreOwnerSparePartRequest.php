<?php

namespace App\Http\Requests\Owner\SparePart;

use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreOwnerSparePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('spareparts.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $workshopId = trim((string) $this->input('workshop_id', ''));

        $rules = [
            'workshop_id' => [
                'required',
                'string',
                Rule::exists('workshops', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true),
                ),
            ],
            'supplier_id' => [
                'nullable',
                'string',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->when(
                        $this->hasSoftDeleteColumn('suppliers'),
                        fn ($supplierQuery) => $supplierQuery->whereNull('deleted_at'),
                    )),
            ],
            'warehouse_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'selling_price' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'minimum_stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (Schema::hasTable('warehouses')) {
            $rules['warehouse_id'][] = Rule::exists('warehouses', 'id')->where(
                fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('workshop_id', $workshopId)
                    ->where('is_active', true)
                    ->when(
                        $this->hasSoftDeleteColumn('warehouses'),
                        fn ($warehouseQuery) => $warehouseQuery->whereNull('deleted_at'),
                    ),
            );
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workshop_id.required' => 'Bengkel tujuan wajib dipilih.',
            'workshop_id.exists' => 'Bengkel tujuan tidak valid atau tidak aktif.',
            'name.required' => 'Nama sparepart wajib diisi.',
            'category.required' => 'Kategori wajib diisi.',
            'unit.required' => 'Satuan wajib diisi.',
            'supplier_id.exists' => 'Supplier tidak valid untuk tenant ini.',
            'warehouse_id.exists' => 'Gudang tidak valid untuk cabang aktif.',
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
            'supplier_id' => $this->normalizeNullableString((string) $this->input('supplier_id', '')),
            'warehouse_id' => $this->normalizeNullableString((string) $this->input('warehouse_id', '')),
            'name' => trim((string) $this->input('name', '')),
            'sku' => $this->normalizeNullableString(strtoupper((string) $this->input('sku', ''))),
            'category' => $this->normalizeNullableString((string) $this->input('category', '')),
            'unit' => $this->normalizeNullableString((string) $this->input('unit', '')),
            'purchase_price' => $this->normalizeNullableInteger($this->input('purchase_price')),
            'selling_price' => $this->normalizeNullableInteger($this->input('selling_price')),
            'stock' => $this->normalizeNullableInteger($this->input('stock')),
            'minimum_stock' => $this->normalizeNullableInteger($this->input('minimum_stock')),
            'notes' => $this->normalizeNullableString((string) $this->input('notes', '')),
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : true,
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function hasSoftDeleteColumn(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'deleted_at');
    }
}
