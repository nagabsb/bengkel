<?php

namespace App\Http\Requests\Owner\ServiceOrder;

use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

class StoreOwnerServiceOrderEstimateRequest extends FormRequest
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
        $tenantId = (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', ''));
        $activeWorkshopId = (string) $this->attributes->get('tenant_workshop_id', $tenantId);
        $shouldApplyWorkshopScope = ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);

        return [
            'approval_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_type' => ['required', 'string', Rule::in(['service', 'sparepart'])],
            'items.*.label' => ['required', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.unit_label' => ['nullable', 'string', 'max:50'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'items.*.spare_part_id' => [
                'nullable',
                'string',
                Rule::exists('spare_parts', 'id')->where(function (Builder $query) use ($tenantId, $activeWorkshopId, $shouldApplyWorkshopScope): void {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->when(
                            $this->hasSoftDeleteColumn('spare_parts'),
                            fn (Builder $sparePartQuery) => $sparePartQuery->whereNull('deleted_at'),
                        );

                    if (
                        $shouldApplyWorkshopScope
                        && Schema::hasTable('spare_parts')
                        && Schema::hasColumn('spare_parts', 'workshop_id')
                    ) {
                        $query->where('workshop_id', $activeWorkshopId);
                    }
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Item estimasi wajib diisi minimal 1 baris.',
            'items.min' => 'Item estimasi wajib diisi minimal 1 baris.',
            'items.*.item_type.in' => 'Tipe item estimasi tidak valid.',
            'items.*.label.required' => 'Nama item estimasi wajib diisi.',
            'items.*.qty.required' => 'Qty item estimasi wajib diisi.',
            'items.*.qty.min' => 'Qty item estimasi minimal 1.',
            'items.*.unit_price.required' => 'Harga satuan item estimasi wajib diisi.',
            'items.*.unit_price.min' => 'Harga satuan item estimasi tidak boleh negatif.',
            'items.*.spare_part_id.exists' => 'Sparepart untuk item estimasi tidak valid.',
            'approval_expires_at.after_or_equal' => 'Batas waktu approval tidak boleh sebelum hari ini.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizedItems = collect($this->input('items', []))
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                return [
                    'item_type' => strtolower(trim((string) ($row['item_type'] ?? 'service'))),
                    'label' => $this->normalizeNullableString((string) ($row['label'] ?? '')),
                    'description' => $this->normalizeNullableString((string) ($row['description'] ?? '')),
                    'unit_label' => $this->normalizeNullableString((string) ($row['unit_label'] ?? '')),
                    'qty' => $this->normalizeNullableInteger($row['qty'] ?? null),
                    'unit_price' => $this->normalizeNullableInteger($row['unit_price'] ?? null),
                    'spare_part_id' => $this->normalizeNullableString((string) ($row['spare_part_id'] ?? '')),
                ];
            })
            ->filter(fn (array $row): bool => $row['label'] !== null || $row['qty'] !== null || $row['unit_price'] !== null)
            ->values()
            ->all();

        $this->merge([
            'approval_expires_at' => $this->normalizeNullableString((string) $this->input('approval_expires_at', '')),
            'internal_note' => $this->normalizeNullableString((string) $this->input('internal_note', '')),
            'submit_for_approval' => $this->normalizeBoolean($this->input('submit_for_approval', false)),
            'items' => $normalizedItems,
        ]);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $normalized = preg_replace('/[^\d]/', '', (string) $value);
        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function hasSoftDeleteColumn(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'deleted_at');
    }
}

