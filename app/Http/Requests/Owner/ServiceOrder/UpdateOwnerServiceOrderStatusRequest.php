<?php

namespace App\Http\Requests\Owner\ServiceOrder;

use App\Services\Owner\OwnerWorkshopSwitcherService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateOwnerServiceOrderStatusRequest extends FormRequest
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
        $status = strtolower(trim((string) $this->input('status', '')));
        $allowNoSpareparts = (bool) $this->input('allow_no_spareparts', false);

        $statusRules = [
            'status' => [
                'required',
                'string',
                Rule::in(['in_progress', 'done', 'cancelled']),
            ],
        ];

        if ($status !== 'done') {
            return $statusRules;
        }

        return [
            ...$statusRules,
            'allow_no_spareparts' => [
                'nullable',
                'boolean',
            ],
            'service_fee' => [
                'required',
                'integer',
                'min:0',
                'max:1000000000000',
            ],
            'mechanic_user_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'mechanic_user_ids.*' => [
                'required',
                'string',
                Rule::exists('users', 'id')->where(function (Builder $query) use ($tenantId, $activeWorkshopId, $shouldApplyWorkshopScope): void {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_superadmin', false)
                        ->where('is_owner', false)
                        ->where(function (Builder $roleQuery): void {
                            $roleQuery
                                ->whereRaw('LOWER(COALESCE(user_type, ?)) = ?', ['', 'mekanik'])
                                ->orWhereRaw('LOWER(COALESCE(role, ?)) = ?', ['', 'mekanik']);
                        });

                    if (
                        $shouldApplyWorkshopScope
                        && Schema::hasTable('users')
                        && Schema::hasColumn('users', 'workshop_id')
                    ) {
                        $query->where('workshop_id', $activeWorkshopId);
                    }
                }),
            ],
            'spareparts' => $allowNoSpareparts
                ? ['nullable', 'array']
                : ['required', 'array', 'min:1'],
            'spareparts.*.spare_part_id' => [
                'required',
                'string',
                Rule::exists('spare_parts', 'id')->where(fn (Builder $query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->when(
                        $this->hasSoftDeleteColumn('spare_parts'),
                        fn (Builder $sparePartQuery) => $sparePartQuery->whereNull('deleted_at'),
                    )),
            ],
            'spareparts.*.qty' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],
            'spareparts.*.warehouse_id' => [
                'nullable',
                'string',
                Rule::exists('warehouses', 'id')->where(function (Builder $query) use ($tenantId, $activeWorkshopId, $shouldApplyWorkshopScope): void {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true);

                    if ($this->hasSoftDeleteColumn('warehouses')) {
                        $query->whereNull('deleted_at');
                    }

                    if (
                        $shouldApplyWorkshopScope
                        && Schema::hasTable('warehouses')
                        && Schema::hasColumn('warehouses', 'workshop_id')
                    ) {
                        $query->where('workshop_id', $activeWorkshopId);
                    }
                }),
            ],
            'spareparts.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'completion_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status servis wajib dipilih.',
            'status.in' => 'Status servis tidak valid.',
            'service_fee.required' => 'Biaya jasa wajib diisi sebelum servis diselesaikan.',
            'service_fee.integer' => 'Biaya jasa harus angka.',
            'service_fee.min' => 'Biaya jasa tidak boleh kurang dari 0.',
            'mechanic_user_ids.required_if' => 'Mekanik wajib diisi sebelum servis diselesaikan.',
            'mechanic_user_ids.min' => 'Pilih minimal 1 mekanik.',
            'mechanic_user_ids.*.exists' => 'Mekanik tidak valid atau bukan dari bengkel aktif.',
            'spareparts.required_if' => 'Sparepart terpakai wajib diisi sebelum servis diselesaikan.',
            'spareparts.min' => 'Tambah minimal 1 sparepart terpakai.',
            'spareparts.*.spare_part_id.required_with' => 'Sparepart wajib dipilih.',
            'spareparts.*.spare_part_id.exists' => 'Sparepart tidak ditemukan.',
            'spareparts.*.qty.required_with' => 'Qty sparepart wajib diisi.',
            'spareparts.*.qty.integer' => 'Qty sparepart harus angka.',
            'spareparts.*.qty.min' => 'Qty sparepart minimal 1.',
            'spareparts.*.warehouse_id.exists' => 'Gudang sparepart tidak valid di bengkel aktif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $mechanicUserIds = collect($this->input('mechanic_user_ids', []))
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        $allowNoSpareparts = $this->normalizeBoolean($this->input('allow_no_spareparts', false));

        $spareParts = collect($this->input('spareparts', []))
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'spare_part_id' => trim((string) ($item['spare_part_id'] ?? '')),
                    'qty' => $this->normalizeNullableInteger($item['qty'] ?? null),
                    'warehouse_id' => $this->normalizeNullableString((string) ($item['warehouse_id'] ?? '')),
                    'notes' => $this->normalizeNullableString((string) ($item['notes'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['spare_part_id'] !== '' || $item['qty'] !== null)
            ->values()
            ->all();

        $this->merge([
            'status' => strtolower(trim((string) $this->input('status', ''))),
            'allow_no_spareparts' => $allowNoSpareparts,
            'service_fee' => $this->normalizeNullableInteger($this->input('service_fee')),
            'mechanic_user_ids' => $mechanicUserIds,
            'spareparts' => $spareParts,
            'completion_notes' => $this->normalizeNullableString((string) $this->input('completion_notes', '')),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('owner.orders.update-status.request-validation-failed', [
            'tenant_id' => (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', '')),
            'order_id' => (string) $this->route('order', ''),
            'user_id' => (string) ($this->user()?->getAuthIdentifier() ?? ''),
            'payload' => $this->all(),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }

    protected function failedAuthorization(): void
    {
        Log::warning('owner.orders.update-status.request-authorization-failed', [
            'tenant_id' => (string) $this->attributes->get('tenant_id', (string) $this->route('tenant', '')),
            'order_id' => (string) $this->route('order', ''),
            'user_id' => (string) ($this->user()?->getAuthIdentifier() ?? ''),
        ]);

        throw new HttpException(403, 'Anda tidak memiliki akses untuk mengubah status servis.');
    }

    private function normalizeNullableString(string $value): ?string
    {
        $normalized = trim($value);

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
