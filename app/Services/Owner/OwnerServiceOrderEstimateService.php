<?php

namespace App\Services\Owner;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderEstimate;
use App\Models\ServiceOrderEstimateAiLog;
use App\Models\ServiceOrderEstimateItem;
use App\Models\SparePart;
use App\Models\Workshop;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerServiceOrderEstimateService
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function createEstimate(
        string $tenantId,
        string $activeWorkshopId,
        string $orderId,
        array $validated,
        ?Authenticatable $actor = null,
    ): array {
        $this->assertEstimateTablesReady();

        return DB::transaction(function () use ($tenantId, $activeWorkshopId, $orderId, $validated, $actor): array {
            $order = $this->findTenantOrderOrFail(
                $tenantId,
                $activeWorkshopId,
                $orderId,
                'create_estimate',
                lockForUpdate: true,
            );

            if ($this->hasApprovedEstimate($tenantId, (string) $order->id)) {
                throw ValidationException::withMessages([
                    'estimate' => 'Estimasi sudah disetujui pelanggan dan tidak bisa diubah lagi.',
                ]);
            }

            $normalizedRows = $this->normalizeEstimateRows($validated['items'] ?? []);
            if (count($normalizedRows) < 1) {
                throw ValidationException::withMessages([
                    'items' => 'Item estimasi wajib diisi minimal 1 baris.',
                ]);
            }

            $this->expirePreviousOpenEstimates($tenantId, (string) $order->id);

            $sendForApproval = (bool) ($validated['submit_for_approval'] ?? false);
            $tokenPlain = null;
            $tokenHash = null;
            $validUntil = null;
            $approvalRequestedAt = null;
            $status = 'draft';

            if ($sendForApproval) {
                $status = 'pending_approval';
                $approvalRequestedAt = now();
                $validUntil = $this->resolveApprovalExpiry($validated['approval_expires_at'] ?? null);
                $tokenPlain = Str::random(64);
                $tokenHash = hash('sha256', $tokenPlain);
            }

            $nextRevision = ((int) ServiceOrderEstimate::query()
                ->where('tenant_id', $tenantId)
                ->where('service_order_id', (string) $order->id)
                ->max('revision')) + 1;

            $estimate = ServiceOrderEstimate::query()->create([
                'tenant_id' => $tenantId,
                'service_order_id' => (string) $order->id,
                'code' => $this->generateEstimateCode($tenantId),
                'revision' => max(1, $nextRevision),
                'status' => $status,
                'customer_name' => trim((string) ($order->customer?->name ?? 'Pelanggan')),
                'customer_phone' => $this->sanitizeNullableString($order->customer?->phone ?? null),
                'customer_email' => $this->sanitizeNullableString($order->customer?->email ?? null),
                'subtotal_service' => 0,
                'subtotal_sparepart' => 0,
                'total_amount' => 0,
                'valid_until' => $validUntil,
                'approval_requested_at' => $approvalRequestedAt,
                'approval_token_hash' => $tokenHash,
                'internal_note' => $this->sanitizeNullableString($validated['internal_note'] ?? null),
                'requested_by_user_id' => $this->resolveActorUserId($actor),
            ]);

            [$subtotalService, $subtotalSparePart] = $this->createEstimateItems(
                $tenantId,
                $activeWorkshopId,
                $estimate,
                $normalizedRows,
            );
            $totalAmount = $subtotalService + $subtotalSparePart;

            $estimate->forceFill([
                'subtotal_service' => $subtotalService,
                'subtotal_sparepart' => $subtotalSparePart,
                'total_amount' => $totalAmount,
            ])->save();

            $approvalLink = null;
            if ($tokenPlain !== null) {
                $approvalLink = route('estimate-approval.show', ['token' => $tokenPlain]);
            }

            Log::info('owner.service-estimates.created', [
                'tenant_id' => $tenantId,
                'service_order_id' => (string) $order->id,
                'estimate_id' => (string) $estimate->id,
                'estimate_code' => (string) $estimate->code,
                'status' => (string) $estimate->status,
                'requested_by_user_id' => $this->resolveActorUserId($actor),
            ]);

            return [
                'message' => $sendForApproval
                    ? 'Estimasi disimpan dan link approval siap dibagikan ke pelanggan.'
                    : 'Estimasi draft berhasil disimpan.',
                'estimate_id' => (string) $estimate->id,
                'estimate_code' => (string) $estimate->code,
                'estimate_status' => (string) $estimate->status,
                'approval_link' => $approvalLink,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildApprovalPageData(string $token): array
    {
        $this->assertEstimateTablesReady();

        $normalizedToken = trim($token);
        if ($normalizedToken === '') {
            return [
                'isValid' => false,
                'canRespond' => false,
                'errorMessage' => 'Link approval tidak valid.',
                'estimate' => null,
                'submitPath' => null,
            ];
        }

        $tokenHash = $this->resolveApprovalTokenHash($normalizedToken);
        $estimate = ServiceOrderEstimate::query()
            ->with([
                'serviceOrder:id,customer_id,customer_vehicle_id,code,service_date,complaint,status',
                'serviceOrder.customer:id,name,phone,email',
                'serviceOrder.vehicle:id,brand,model,plate_number',
                'items:id,service_order_estimate_id,item_type,label,unit_label,description,qty,unit_price,subtotal',
            ])
            ->where('approval_token_hash', $tokenHash)
            ->first();

        if (! $estimate) {
            return [
                'isValid' => false,
                'canRespond' => false,
                'errorMessage' => 'Link approval tidak ditemukan atau sudah tidak aktif.',
                'estimate' => null,
                'submitPath' => null,
            ];
        }

        $isExpired = $estimate->status === 'pending_approval'
            && $estimate->valid_until instanceof Carbon
            && $estimate->valid_until->lt(now());
        $displayStatus = $isExpired ? 'expired' : (string) ($estimate->status ?? 'draft');

        $canRespond = $estimate->status === 'pending_approval' && ! $isExpired;
        $resolvedCustomerName = $this->sanitizeNullableString(
            $estimate->customer_name
            ?? $estimate->serviceOrder?->customer?->name
            ?? null,
        ) ?? 'Pelanggan';
        $resolvedCustomerPhone = $this->sanitizeNullableString(
            $estimate->customer_phone
            ?? $estimate->serviceOrder?->customer?->phone
            ?? null,
        ) ?? '';
        $resolvedCustomerEmail = $this->sanitizeNullableString(
            $estimate->customer_email
            ?? $estimate->serviceOrder?->customer?->email
            ?? null,
        ) ?? '';
        $diagnosisSummary = $this->resolveLatestDiagnosisSummary(
            (string) $estimate->tenant_id,
            (string) $estimate->service_order_id,
        );

        return [
            'isValid' => true,
            'canRespond' => $canRespond,
            'errorMessage' => null,
            'submitPath' => route('estimate-approval.respond', ['token' => $normalizedToken], false),
            'estimate' => [
                'id' => (string) $estimate->id,
                'code' => (string) ($estimate->code ?? ''),
                'revision' => (int) ($estimate->revision ?? 1),
                'status' => $displayStatus,
                'status_label' => $this->resolveEstimateStatusLabel($displayStatus),
                'service_order_code' => (string) ($estimate->serviceOrder?->code ?? ''),
                'service_date' => $estimate->serviceOrder?->service_date,
                'complaint' => (string) ($estimate->serviceOrder?->complaint ?? ''),
                'vehicle_name' => trim(implode(' ', array_filter([
                    (string) ($estimate->serviceOrder?->vehicle?->brand ?? ''),
                    (string) ($estimate->serviceOrder?->vehicle?->model ?? ''),
                ]))),
                'vehicle_plate_number' => (string) ($estimate->serviceOrder?->vehicle?->plate_number ?? ''),
                'customer_name' => $resolvedCustomerName,
                'customer_phone' => $resolvedCustomerPhone,
                'customer_email' => $resolvedCustomerEmail,
                'subtotal_service' => max((int) ($estimate->subtotal_service ?? 0), 0),
                'subtotal_sparepart' => max((int) ($estimate->subtotal_sparepart ?? 0), 0),
                'total_amount' => max((int) ($estimate->total_amount ?? 0), 0),
                'valid_until' => $estimate->valid_until,
                'approval_requested_at' => $estimate->approval_requested_at,
                'approved_at' => $estimate->approved_at,
                'rejected_at' => $estimate->rejected_at,
                'expired_at' => $estimate->expired_at,
                'approval_note' => (string) ($estimate->approval_note ?? ''),
                'rejection_reason' => (string) ($estimate->rejection_reason ?? ''),
                'approved_by_name' => (string) ($estimate->approved_by_name ?? ''),
                'diagnosis' => $diagnosisSummary,
                'items' => $estimate->items
                    ->map(fn (ServiceOrderEstimateItem $item): array => [
                        'id' => (string) $item->id,
                        'item_type' => (string) ($item->item_type ?? 'service'),
                        'label' => (string) ($item->label ?? ''),
                        'unit_label' => (string) ($item->unit_label ?? ''),
                        'description' => (string) ($item->description ?? ''),
                        'qty' => max((int) ($item->qty ?? 0), 0),
                        'unit_price' => max((int) ($item->unit_price ?? 0), 0),
                        'subtotal' => max((int) ($item->subtotal ?? 0), 0),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    public function respondToApproval(
        string $token,
        array $validated,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $this->assertEstimateTablesReady();

        $normalizedToken = trim($token);
        if ($normalizedToken === '') {
            throw ValidationException::withMessages([
                'token' => 'Link approval tidak valid.',
            ]);
        }

        $tokenHash = $this->resolveApprovalTokenHash($normalizedToken);
        $action = strtolower(trim((string) ($validated['action'] ?? '')));

        return DB::transaction(function () use ($tokenHash, $validated, $action, $ipAddress, $userAgent): array {
            $estimate = ServiceOrderEstimate::query()
                ->where('approval_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $estimate) {
                throw ValidationException::withMessages([
                    'token' => 'Link approval tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            if ($estimate->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'action' => 'Estimasi ini sudah diproses sebelumnya.',
                ]);
            }

            if ($estimate->valid_until instanceof Carbon && $estimate->valid_until->lt(now())) {
                $estimate->forceFill([
                    'status' => 'expired',
                    'expired_at' => now(),
                ])->save();

                throw ValidationException::withMessages([
                    'action' => 'Link approval sudah kadaluarsa.',
                ]);
            }

            $approverName = $this->sanitizeNullableString($validated['approver_name'] ?? null);
            $approverPhone = $this->sanitizeNullableString($validated['approver_phone'] ?? null);
            $approvalNote = $this->sanitizeNullableString($validated['approval_note'] ?? null);

            if ($approverName === null) {
                throw ValidationException::withMessages([
                    'approver_name' => 'Nama penyetuju wajib diisi.',
                ]);
            }

            if ($action === 'approve') {
                $signatureData = $this->decodeSignatureToBinary((string) ($validated['signature'] ?? ''));
                $signaturePath = $this->storeSignature(
                    (string) $estimate->tenant_id,
                    $signatureData,
                );

                $estimate->forceFill([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by_name' => $approverName,
                    'approved_by_phone' => $approverPhone,
                    'approved_signature_path' => $signaturePath,
                    'approved_ip' => $this->sanitizeNullableString($ipAddress),
                    'approved_user_agent' => $this->sanitizeNullableString($userAgent),
                    'approval_note' => $approvalNote,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'approval_payload' => [
                        'action' => 'approve',
                        'approved_by_name' => $approverName,
                        'approved_by_phone' => $approverPhone,
                        'approved_ip' => $this->sanitizeNullableString($ipAddress),
                        'approved_user_agent' => $this->sanitizeNullableString($userAgent),
                        'approved_at' => now()->toIso8601String(),
                    ],
                ])->save();

                Log::info('public.service-estimates.approved', [
                    'tenant_id' => (string) $estimate->tenant_id,
                    'service_order_id' => (string) $estimate->service_order_id,
                    'estimate_id' => (string) $estimate->id,
                    'estimate_code' => (string) $estimate->code,
                    'approved_by' => $approverName,
                    'approved_ip' => $this->sanitizeNullableString($ipAddress),
                ]);

                return [
                    'status' => 'approved',
                    'message' => 'Estimasi berhasil disetujui. Tim bengkel bisa mulai pengerjaan.',
                ];
            }

            $rejectionReason = $this->sanitizeNullableString($validated['rejection_reason'] ?? null);
            if ($action === 'reject' && $rejectionReason === null) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Alasan penolakan wajib diisi.',
                ]);
            }

            $estimate->forceFill([
                'status' => 'rejected',
                'rejected_at' => now(),
                'approved_at' => null,
                'approved_by_name' => $approverName,
                'approved_by_phone' => $approverPhone,
                'approval_note' => $approvalNote,
                'rejection_reason' => $rejectionReason,
                'approval_payload' => [
                    'action' => 'reject',
                    'rejected_by_name' => $approverName,
                    'rejected_by_phone' => $approverPhone,
                    'rejected_ip' => $this->sanitizeNullableString($ipAddress),
                    'rejected_user_agent' => $this->sanitizeNullableString($userAgent),
                    'rejected_at' => now()->toIso8601String(),
                    'rejection_reason' => $rejectionReason,
                ],
            ])->save();

            Log::info('public.service-estimates.rejected', [
                'tenant_id' => (string) $estimate->tenant_id,
                'service_order_id' => (string) $estimate->service_order_id,
                'estimate_id' => (string) $estimate->id,
                'estimate_code' => (string) $estimate->code,
                'rejected_by' => $approverName,
                'rejected_ip' => $this->sanitizeNullableString($ipAddress),
            ]);

            return [
                'status' => 'rejected',
                'message' => 'Estimasi ditolak. Tim bengkel akan melakukan revisi.',
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLatestDiagnosisSummary(string $tenantId, string $serviceOrderId): ?array
    {
        if (
            $tenantId === ''
            || $serviceOrderId === ''
            || ! Schema::hasTable('service_order_estimate_ai_logs')
        ) {
            return null;
        }

        $latestDiagnosisLog = ServiceOrderEstimateAiLog::query()
            ->where('tenant_id', $tenantId)
            ->where('service_order_id', $serviceOrderId)
            ->where('feature_key', 'symptom_diagnosis_v1')
            ->where('status', 'success')
            ->orderByDesc('created_at')
            ->first([
                'id',
                'output_payload',
                'created_at',
            ]);

        if (! $latestDiagnosisLog) {
            return null;
        }

        $outputPayload = is_array($latestDiagnosisLog->output_payload)
            ? $latestDiagnosisLog->output_payload
            : [];

        $summary = trim((string) ($outputPayload['summary'] ?? ''));
        $possibleCauses = collect($outputPayload['possible_causes'] ?? [])
            ->filter(fn ($cause): bool => is_array($cause))
            ->map(function (array $cause): ?array {
                $label = trim((string) ($cause['label'] ?? ''));
                if ($label === '') {
                    return null;
                }

                $severity = strtolower(trim((string) ($cause['severity'] ?? 'medium')));
                if (! in_array($severity, ['high', 'medium', 'low'], true)) {
                    $severity = 'medium';
                }

                return [
                    'label' => $label,
                    'severity' => $severity,
                    'confidence' => max(0, min(100, (int) ($cause['confidence'] ?? 0))),
                    'reason' => $this->sanitizeNullableString($cause['reason'] ?? null),
                ];
            })
            ->filter(fn ($cause): bool => is_array($cause))
            ->values()
            ->take(3)
            ->all();

        $warnings = collect($outputPayload['warnings'] ?? [])
            ->map(fn ($warning): string => trim((string) $warning))
            ->filter(fn (string $warning): bool => $warning !== '')
            ->unique()
            ->values()
            ->take(4)
            ->all();

        $disclaimer = $this->sanitizeNullableString($outputPayload['disclaimer'] ?? null)
            ?? 'Diagnosa awal, hasil final setelah pemeriksaan teknisi.';

        if ($summary === '' && count($possibleCauses) < 1 && count($warnings) < 1) {
            return null;
        }

        return [
            'log_id' => (string) $latestDiagnosisLog->id,
            'summary' => $summary,
            'possible_causes' => $possibleCauses,
            'warnings' => $warnings,
            'disclaimer' => $disclaimer,
            'generated_at' => $latestDiagnosisLog->created_at?->toIso8601String(),
        ];
    }

    public function hasApprovedEstimate(string $tenantId, string $serviceOrderId): bool
    {
        if (! Schema::hasTable('service_order_estimates')) {
            return false;
        }

        return ServiceOrderEstimate::query()
            ->where('tenant_id', $tenantId)
            ->where('service_order_id', $serviceOrderId)
            ->where('status', 'approved')
            ->exists();
    }

    private function assertEstimateTablesReady(): void
    {
        if (! Schema::hasTable('service_order_estimates') || ! Schema::hasTable('service_order_estimate_items')) {
            throw ValidationException::withMessages([
                'estimate' => 'Modul estimasi belum siap. Jalankan migrasi terbaru.',
            ]);
        }
    }

    private function findTenantOrderOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $orderId,
        string $errorKey,
        bool $lockForUpdate = false,
    ): ServiceOrder {
        $query = ServiceOrder::query()
            ->with(['customer:id,name,phone,email,workshop_id'])
            ->where('tenant_id', $tenantId)
            ->where('id', $orderId)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery->where('workshop_id', $activeWorkshopId);
                });
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $order = $query->first();
        if (! $order) {
            throw ValidationException::withMessages([
                $errorKey => 'Data servis tidak ditemukan di cabang aktif.',
            ]);
        }

        return $order;
    }

    /**
     * @param  mixed  $rawRows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEstimateRows(mixed $rawRows): array
    {
        return collect($rawRows)
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                $qty = $this->normalizeNullableInteger($row['qty'] ?? null) ?? 0;
                $unitPrice = $this->normalizeNullableInteger($row['unit_price'] ?? null) ?? 0;
                $itemType = strtolower(trim((string) ($row['item_type'] ?? 'service')));
                $label = $this->sanitizeNullableString($row['label'] ?? null);
                $description = $this->sanitizeNullableString($row['description'] ?? null);
                $unitLabel = $this->sanitizeNullableString($row['unit_label'] ?? null);
                $sparePartId = trim((string) ($row['spare_part_id'] ?? ''));

                return [
                    'item_type' => in_array($itemType, ['service', 'sparepart'], true) ? $itemType : 'service',
                    'label' => $label ?? '',
                    'description' => $description,
                    'unit_label' => $unitLabel,
                    'spare_part_id' => $sparePartId !== '' ? $sparePartId : null,
                    'qty' => max(1, $qty),
                    'unit_price' => max(0, $unitPrice),
                ];
            })
            ->filter(fn (array $row): bool => $row['label'] !== '' || $row['spare_part_id'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0:int,1:int}
     */
    private function createEstimateItems(
        string $tenantId,
        string $activeWorkshopId,
        ServiceOrderEstimate $estimate,
        array $rows,
    ): array {
        $sparePartIds = collect($rows)
            ->filter(fn (array $row): bool => (string) ($row['item_type'] ?? '') === 'sparepart')
            ->pluck('spare_part_id')
            ->filter(fn ($sparePartId): bool => is_string($sparePartId) && trim($sparePartId) !== '')
            ->map(fn (string $sparePartId): string => trim($sparePartId))
            ->unique()
            ->values();

        $sparePartById = collect();
        if ($sparePartIds->isNotEmpty()) {
            $sparePartById = SparePart::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when($this->shouldApplySparePartWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                    $query->where('workshop_id', $activeWorkshopId);
                })
                ->whereIn('id', $sparePartIds->all())
                ->get(['id', 'name', 'unit'])
                ->keyBy(fn (SparePart $sparePart): string => (string) $sparePart->id);
        }

        $subtotalService = 0;
        $subtotalSparePart = 0;

        foreach ($rows as $index => $row) {
            $itemType = (string) ($row['item_type'] ?? 'service');
            $qty = max((int) ($row['qty'] ?? 0), 1);
            $unitPrice = max((int) ($row['unit_price'] ?? 0), 0);
            $subtotal = $qty * $unitPrice;

            $sparePartId = $row['spare_part_id'] ?? null;
            $label = (string) ($row['label'] ?? '');
            $unitLabel = $row['unit_label'] ?? null;

            if ($itemType === 'sparepart') {
                if (is_string($sparePartId) && trim($sparePartId) !== '') {
                    $sparePart = $sparePartById->get($sparePartId);
                    if (! $sparePart instanceof SparePart) {
                        throw ValidationException::withMessages([
                            "items.{$index}.spare_part_id" => 'Sparepart item estimasi tidak valid di bengkel aktif.',
                        ]);
                    }

                    $label = trim((string) $sparePart->name);
                    $unitLabel = trim((string) ($sparePart->unit ?? ''));
                }

                if (trim($label) === '') {
                    throw ValidationException::withMessages([
                        "items.{$index}.label" => 'Nama sparepart wajib diisi.',
                    ]);
                }

                $subtotalSparePart += $subtotal;
            } else {
                $subtotalService += $subtotal;
            }

            ServiceOrderEstimateItem::query()->create([
                'tenant_id' => $tenantId,
                'service_order_estimate_id' => (string) $estimate->id,
                'item_type' => $itemType,
                'spare_part_id' => $itemType === 'sparepart' && is_string($sparePartId) && trim($sparePartId) !== ''
                    ? trim($sparePartId)
                    : null,
                'label' => $label,
                'unit_label' => $unitLabel,
                'description' => $this->sanitizeNullableString($row['description'] ?? null),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }

        return [$subtotalService, $subtotalSparePart];
    }

    private function expirePreviousOpenEstimates(string $tenantId, string $serviceOrderId): void
    {
        ServiceOrderEstimate::query()
            ->where('tenant_id', $tenantId)
            ->where('service_order_id', $serviceOrderId)
            ->whereIn('status', ['draft', 'pending_approval'])
            ->update([
                'status' => 'expired',
                'expired_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function resolveApprovalExpiry(mixed $rawDate): Carbon
    {
        $normalized = trim((string) $rawDate);
        if ($normalized === '') {
            return now()->addDays(2)->endOfDay();
        }

        try {
            $date = Carbon::parse($normalized)->endOfDay();
        } catch (\Throwable) {
            return now()->addDays(2)->endOfDay();
        }

        if ($date->lt(now())) {
            return now()->addHours(6);
        }

        return $date;
    }

    private function generateEstimateCode(string $tenantId): string
    {
        $prefix = 'ES-'.now()->format('Ymd');

        for ($sequence = 1; $sequence <= 999; $sequence++) {
            $candidateCode = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $exists = ServiceOrderEstimate::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $prefix.'-'.Str::upper(Str::random(4));
    }

    private function decodeSignatureToBinary(string $signatureData): string
    {
        $normalized = trim($signatureData);
        if ($normalized === '' || ! str_starts_with($normalized, 'data:image/png;base64,')) {
            throw ValidationException::withMessages([
                'signature' => 'Format tanda tangan digital tidak valid.',
            ]);
        }

        $rawBase64 = substr($normalized, strlen('data:image/png;base64,'));
        $decoded = base64_decode($rawBase64, true);
        if (! is_string($decoded) || $decoded === '') {
            throw ValidationException::withMessages([
                'signature' => 'Data tanda tangan digital tidak valid.',
            ]);
        }

        if (strlen($decoded) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'signature' => 'Ukuran tanda tangan digital terlalu besar.',
            ]);
        }

        return $decoded;
    }

    private function resolveApprovalTokenHash(string $token): string
    {
        $normalizedToken = trim($token);
        if ($normalizedToken === '') {
            return '';
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $normalizedToken) === 1) {
            return strtolower($normalizedToken);
        }

        return hash('sha256', $normalizedToken);
    }

    private function storeSignature(string $tenantId, string $signatureBinary): string
    {
        $path = 'tenants/'.$tenantId.'/signatures/'.Str::uuid().'.png';
        Storage::put($path, $signatureBinary);

        return $path;
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function sanitizeNullableString(mixed $value): ?string
    {
        $normalized = trim(strip_tags((string) $value));

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

        $normalized = preg_replace('/[^\d]/', '', (string) $value);
        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function shouldApplyCustomerWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasCustomerWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function shouldApplySparePartWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasSparePartWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function hasCustomerWorkshopScope(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasColumn('customers', 'workshop_id');
    }

    private function hasSparePartWorkshopScope(): bool
    {
        return Schema::hasTable('spare_parts')
            && Schema::hasColumn('spare_parts', 'workshop_id');
    }

    private function hasActiveWorkshops(string $tenantId): bool
    {
        if ($tenantId === '' || ! Schema::hasTable('workshops')) {
            return false;
        }

        return Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }

    private function resolveEstimateStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'expired' => 'Kadaluarsa',
            default => 'Draft',
        };
    }
}
