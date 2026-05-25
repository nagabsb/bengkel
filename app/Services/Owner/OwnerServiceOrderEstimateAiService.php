<?php

namespace App\Services\Owner;

use App\Models\PlatformAiPromptSetting;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEstimateAiLog;
use App\Models\SparePart;
use App\Models\TenantAiPromptOverride;
use App\Models\WarehouseSparePartStock;
use App\Models\Workshop;
use App\Services\Platform\PlatformAiAgentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerServiceOrderEstimateAiService
{
    private const FEATURE_KEY = 'service_estimate_v1';

    private const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah asisten estimasi servis bengkel.
Prioritaskan keselamatan, transparansi biaya, dan kejelasan untuk customer.
Jangan pernah memberi keputusan final; selalu tekankan estimasi awal dan perlu review Service Advisor.
PROMPT;

    private const DEFAULT_FEATURE_PROMPT = <<<'PROMPT'
Buat draft estimasi awal berdasarkan data order servis, riwayat servis, dan katalog sparepart tenant.
Output WAJIB JSON valid tanpa teks tambahan dengan struktur:
{
  "items": [
    {
      "item_type": "service|sparepart",
      "label": "string",
      "description": "string|null",
      "qty": 1,
      "unit_label": "string|null",
      "unit_price": 0,
      "spare_part_name": "string|null",
      "confidence": 0-100,
      "reason": "string|null"
    }
  ],
  "overall_confidence": 0-100,
  "risk_notes": ["string"],
  "advice": ["string"]
}
Aturan:
- Minimal 1 item.
- Jangan tambahkan field di luar struktur.
- Untuk item service, qty selalu 1.
- Untuk item sparepart, qty boleh > 1.
- Untuk item sparepart, gunakan nama yang persis seperti data `spare_part_catalog`.
PROMPT;

    public function __construct(
        private readonly PlatformAiAgentService $platformAiAgentService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function generateDraft(
        string $tenantId,
        string $activeWorkshopId,
        string $orderId,
        array $validated,
        ?Authenticatable $actor = null,
    ): array {
        $this->assertServiceOrderTableReady();

        $startedAt = microtime(true);
        $order = $this->findTenantOrderOrFail($tenantId, $activeWorkshopId, $orderId);
        $promptSetting = $this->resolvePromptSetting();
        $tenantPromptOverride = $this->resolveTenantPromptOverride($tenantId);
        $sparePartCatalog = $this->resolveSparePartCatalog($tenantId, $activeWorkshopId);
        $serviceHistory = $this->resolveServiceHistory($tenantId, $activeWorkshopId, (string) ($order->customer_vehicle_id ?? ''), (string) $order->id);

        $inputPayload = $this->buildInputPayload(
            $order,
            $serviceHistory,
            $sparePartCatalog,
            $validated,
        );

        $instructions = $this->buildInstructions($promptSetting, $tenantPromptOverride);
        $prompt = $this->buildUserPrompt($inputPayload);
        $actorUserId = $this->resolveActorUserId($actor);

        $logPayload = [
            'tenant_id' => $tenantId,
            'service_order_id' => (string) $order->id,
            'feature_key' => self::FEATURE_KEY,
            'generated_by_user_id' => $actorUserId,
            'input_payload' => $inputPayload,
            'prompt_snapshot' => [
                'system_prompt' => (string) ($promptSetting['system_prompt'] ?? self::DEFAULT_SYSTEM_PROMPT),
                'feature_prompt' => (string) ($promptSetting['feature_prompt'] ?? self::DEFAULT_FEATURE_PROMPT),
                'tenant_override' => $tenantPromptOverride,
                'runtime_prompt' => $prompt,
            ],
        ];

        try {
            $runtimeResult = $this->platformAiAgentService->promptWithFailover($prompt, $instructions, 45, [
                'source' => 'owner_service_runtime',
                'feature_key' => self::FEATURE_KEY,
                'tenant_id' => $tenantId,
                'requester_user_id' => $actorUserId,
                'service_order_id' => (string) $order->id,
            ]);
            $response = $runtimeResult['response'] ?? null;
            $responseText = $this->extractResponseText($response);
            $decodedPayload = $this->decodeAiJsonPayload($responseText);

            $normalizedDraft = $this->normalizeDraftPayload(
                $decodedPayload,
                $sparePartCatalog['lookup'] ?? [],
                $sparePartCatalog['rows'] ?? [],
            );
            $totals = $this->calculateTotals($normalizedDraft['items']);

            $latencyMs = max(1, (int) round((microtime(true) - $startedAt) * 1000));
            $promptTokens = max(0, (int) data_get($response, 'usage.promptTokens', 0));
            $completionTokens = max(0, (int) data_get($response, 'usage.completionTokens', 0));
            $totalTokens = $promptTokens + $completionTokens;

            $draftPayload = [
                'order_id' => (string) $order->id,
                'feature_key' => self::FEATURE_KEY,
                'generated_at' => now()->toIso8601String(),
                'confidence_level' => max(0, min(100, (int) ($normalizedDraft['confidence_level'] ?? 0))),
                'disclaimer' => 'Hasil AI adalah estimasi awal, final setelah inspeksi teknisi.',
                'warnings' => $normalizedDraft['warnings'],
                'notes' => $normalizedDraft['notes'],
                'items' => $normalizedDraft['items'],
                'totals' => [
                    'subtotal_service' => $totals['subtotal_service'],
                    'subtotal_sparepart' => $totals['subtotal_sparepart'],
                    'total_amount' => $totals['total_amount'],
                ],
            ];

            $aiLog = ServiceOrderEstimateAiLog::query()->create([
                ...$logPayload,
                'ai_agent_id' => (int) ($runtimeResult['agent_id'] ?? 0) > 0
                    ? (int) $runtimeResult['agent_id']
                    : null,
                'status' => 'success',
                'output_payload' => [
                    ...$draftPayload,
                    'raw_response_text' => $responseText,
                ],
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'latency_ms' => $latencyMs,
            ]);

            Log::info('owner.service-estimates.ai-generated', [
                'tenant_id' => $tenantId,
                'service_order_id' => (string) $order->id,
                'ai_log_id' => (string) $aiLog->id,
                'ai_agent_id' => (int) ($runtimeResult['agent_id'] ?? 0) > 0 ? (int) $runtimeResult['agent_id'] : null,
                'total_tokens' => $totalTokens,
                'latency_ms' => $latencyMs,
                'generated_by_user_id' => $actorUserId,
            ]);

            return [
                ...$draftPayload,
                'log_id' => (string) $aiLog->id,
                'ai_agent_id' => (int) ($runtimeResult['agent_id'] ?? 0) > 0 ? (int) $runtimeResult['agent_id'] : null,
                'provider' => (string) ($runtimeResult['provider'] ?? ''),
                'agent_model' => (string) ($runtimeResult['agent_model'] ?? ''),
                'token_usage' => [
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                ],
            ];
        } catch (ValidationException $validationException) {
            $this->logFailedGeneration(
                $logPayload,
                $startedAt,
                $validationException->getMessage(),
            );

            $message = collect($validationException->errors())
                ->flatten()
                ->first();

            throw ValidationException::withMessages([
                'estimate_ai' => is_string($message) && trim($message) !== ''
                    ? trim($message)
                    : 'Generate estimasi AI gagal. Silakan cek konfigurasi AI agent.',
            ]);
        } catch (\Throwable $throwable) {
            $this->logFailedGeneration(
                $logPayload,
                $startedAt,
                $throwable->getMessage(),
            );

            throw ValidationException::withMessages([
                'estimate_ai' => 'Generate estimasi AI gagal. Silakan coba beberapa saat lagi.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $logPayload
     */
    private function logFailedGeneration(array $logPayload, float $startedAt, string $errorMessage): void
    {
        ServiceOrderEstimateAiLog::query()->create([
            ...$logPayload,
            'status' => 'failed',
            'error_message' => Str::limit(trim($errorMessage), 1000, ''),
            'latency_ms' => max(1, (int) round((microtime(true) - $startedAt) * 1000)),
        ]);
    }

    private function assertServiceOrderTableReady(): void
    {
        if (! Schema::hasTable('service_orders')) {
            throw ValidationException::withMessages([
                'estimate_ai' => 'Tabel service order belum siap.',
            ]);
        }
    }

    private function findTenantOrderOrFail(string $tenantId, string $activeWorkshopId, string $orderId): ServiceOrder
    {
        $order = ServiceOrder::query()
            ->with([
                'customer:id,name,phone,email,workshop_id',
                'vehicle:id,brand,model,variant,year,plate_number',
                'latestEstimate' => function ($query): void {
                    $query->select([
                        'service_order_estimates.id',
                        'service_order_estimates.tenant_id',
                        'service_order_estimates.service_order_id',
                        'service_order_estimates.status',
                        'service_order_estimates.subtotal_service',
                        'service_order_estimates.subtotal_sparepart',
                        'service_order_estimates.total_amount',
                        'service_order_estimates.created_at',
                    ]);
                },
            ])
            ->where('tenant_id', $tenantId)
            ->where('id', $orderId)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery->where('workshop_id', $activeWorkshopId);
                });
            })
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'estimate_ai' => 'Data servis tidak ditemukan di cabang aktif.',
            ]);
        }

        return $order;
    }

    /**
     * @return array<string, string|bool>
     */
    private function resolvePromptSetting(): array
    {
        if (! Schema::hasTable('platform_ai_prompt_settings')) {
            return [
                'name' => 'Service Estimate Prompt V1',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'feature_prompt' => self::DEFAULT_FEATURE_PROMPT,
                'is_active' => true,
            ];
        }

        $setting = PlatformAiPromptSetting::query()->firstOrCreate(
            ['feature_key' => self::FEATURE_KEY],
            [
                'name' => 'Service Estimate Prompt V1',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'feature_prompt' => self::DEFAULT_FEATURE_PROMPT,
                'is_active' => true,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
            ],
        );

        $isActive = (bool) ($setting->is_active ?? true);

        return [
            'name' => (string) ($setting->name ?? 'Service Estimate Prompt V1'),
            'system_prompt' => $isActive
                ? trim((string) ($setting->system_prompt ?? self::DEFAULT_SYSTEM_PROMPT))
                : self::DEFAULT_SYSTEM_PROMPT,
            'feature_prompt' => $isActive
                ? trim((string) ($setting->feature_prompt ?? self::DEFAULT_FEATURE_PROMPT))
                : self::DEFAULT_FEATURE_PROMPT,
            'is_active' => $isActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTenantPromptOverride(string $tenantId): array
    {
        if (! Schema::hasTable('tenant_ai_prompt_overrides')) {
            return [];
        }

        $override = TenantAiPromptOverride::query()
            ->where('tenant_id', $tenantId)
            ->where('feature_key', self::FEATURE_KEY)
            ->where('is_active', true)
            ->first();

        if (! $override) {
            return [];
        }

        return [
            'communication_tone' => (string) ($override->communication_tone ?? ''),
            'preferred_sparepart_brands' => collect($override->preferred_sparepart_brands ?? [])
                ->map(fn ($brand): string => trim((string) $brand))
                ->filter(fn (string $brand): bool => $brand !== '')
                ->values()
                ->all(),
            'estimation_policy_notes' => $this->sanitizeNullableString($override->estimation_policy_notes ?? null),
            'labor_rounding_rule' => (string) ($override->labor_rounding_rule ?? ''),
            'additional_constraints' => $this->sanitizeNullableString($override->additional_constraints ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSparePartCatalog(string $tenantId, string $activeWorkshopId): array
    {
        if (! Schema::hasTable('spare_parts')) {
            return [
                'rows' => [],
                'lookup' => [],
            ];
        }

        if (! Schema::hasTable('warehouse_spare_part_stocks')) {
            $rows = SparePart::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderByDesc('stock')
                ->orderBy('name')
                ->limit(80)
                ->get([
                    'id',
                    'name',
                    'category',
                    'unit',
                    'selling_price',
                    'stock',
                ])
                ->map(function (SparePart $sparePart): array {
                    return [
                        'id' => (string) $sparePart->id,
                        'name' => trim((string) ($sparePart->name ?? '')),
                        'category' => trim((string) ($sparePart->category ?? '')),
                        'unit' => trim((string) ($sparePart->unit ?? '')),
                        'selling_price' => max((int) ($sparePart->selling_price ?? 0), 0),
                        'stock' => max((int) ($sparePart->stock ?? 0), 0),
                    ];
                })
                ->filter(fn (array $row): bool => $row['id'] !== '' && $row['name'] !== '')
                ->values();

            $lookup = $rows
                ->mapWithKeys(function (array $row): array {
                    return [$this->normalizeLookupKey((string) ($row['name'] ?? '')) => $row];
                })
                ->all();

            return [
                'rows' => $rows->all(),
                'lookup' => $lookup,
            ];
        }

        $stocks = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWarehouseStockWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->selectRaw('spare_part_id')
            ->selectRaw('SUM(stock) as total_stock')
            ->groupBy('spare_part_id');

        $rows = SparePart::query()
            ->from('spare_parts')
            ->leftJoinSub($stocks, 'stock_agg', function ($join): void {
                $join->on('stock_agg.spare_part_id', '=', 'spare_parts.id');
            })
            ->where('spare_parts.tenant_id', $tenantId)
            ->where('spare_parts.is_active', true)
            ->whereRaw('COALESCE(stock_agg.total_stock, 0) > 0')
            ->orderByDesc('available_stock')
            ->orderBy('spare_parts.name')
            ->limit(80)
            ->get([
                'spare_parts.id',
                'spare_parts.name',
                'spare_parts.category',
                'spare_parts.unit',
                'spare_parts.selling_price',
                DB::raw('COALESCE(stock_agg.total_stock, 0) as available_stock'),
            ])
            ->map(function (SparePart $sparePart): array {
                return [
                    'id' => (string) $sparePart->id,
                    'name' => trim((string) ($sparePart->name ?? '')),
                    'category' => trim((string) ($sparePart->category ?? '')),
                    'unit' => trim((string) ($sparePart->unit ?? '')),
                    'selling_price' => max((int) ($sparePart->selling_price ?? 0), 0),
                    'stock' => max((int) ($sparePart->getAttribute('available_stock') ?? 0), 0),
                ];
            })
            ->filter(fn (array $row): bool => $row['id'] !== '' && $row['name'] !== '')
            ->values();

        $lookup = $rows
            ->mapWithKeys(function (array $row): array {
                return [$this->normalizeLookupKey((string) ($row['name'] ?? '')) => $row];
            })
            ->all();

        return [
            'rows' => $rows->all(),
            'lookup' => $lookup,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveServiceHistory(
        string $tenantId,
        string $activeWorkshopId,
        string $vehicleId,
        string $currentOrderId,
    ): array {
        if ($vehicleId === '') {
            return [];
        }

        return ServiceOrder::query()
            ->with([
                'latestEstimate' => function ($query): void {
                    $query->select([
                        'service_order_estimates.id',
                        'service_order_estimates.tenant_id',
                        'service_order_estimates.service_order_id',
                        'service_order_estimates.total_amount',
                        'service_order_estimates.status',
                        'service_order_estimates.created_at',
                    ]);
                },
            ])
            ->where('tenant_id', $tenantId)
            ->where('customer_vehicle_id', $vehicleId)
            ->where('id', '!=', $currentOrderId)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery->where('workshop_id', $activeWorkshopId);
                });
            })
            ->orderByDesc('service_date')
            ->limit(5)
            ->get([
                'id',
                'code',
                'service_date',
                'status',
                'complaint',
                'odometer',
                'total_amount',
            ])
            ->map(function (ServiceOrder $serviceOrder): array {
                return [
                    'id' => (string) $serviceOrder->id,
                    'code' => (string) ($serviceOrder->code ?? ''),
                    'service_date' => $serviceOrder->service_date?->toDateString(),
                    'status' => (string) ($serviceOrder->status ?? ''),
                    'complaint' => (string) ($serviceOrder->complaint ?? ''),
                    'odometer' => $serviceOrder->odometer !== null ? (int) $serviceOrder->odometer : null,
                    'total_amount' => max((int) ($serviceOrder->total_amount ?? 0), 0),
                    'latest_estimate_total' => max((int) ($serviceOrder->latestEstimate?->total_amount ?? 0), 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $serviceHistory
     * @param  array<string, mixed>  $sparePartCatalog
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildInputPayload(
        ServiceOrder $order,
        array $serviceHistory,
        array $sparePartCatalog,
        array $validated,
    ): array {
        return [
            'order' => [
                'id' => (string) $order->id,
                'code' => (string) ($order->code ?? ''),
                'service_date' => $order->service_date?->toDateString(),
                'complaint' => (string) ($order->complaint ?? ''),
                'vehicle_condition' => (string) ($order->vehicle_condition ?? ''),
                'odometer' => $order->odometer !== null ? (int) $order->odometer : null,
                'status' => (string) ($order->status ?? ''),
            ],
            'customer' => [
                'name' => (string) ($order->customer?->name ?? ''),
                'phone' => (string) ($order->customer?->phone ?? ''),
            ],
            'vehicle' => [
                'brand' => (string) ($order->vehicle?->brand ?? ''),
                'model' => (string) ($order->vehicle?->model ?? ''),
                'variant' => (string) ($order->vehicle?->variant ?? ''),
                'year' => $order->vehicle?->year !== null ? (int) $order->vehicle->year : null,
                'plate_number' => (string) ($order->vehicle?->plate_number ?? ''),
            ],
            'latest_estimate' => [
                'status' => (string) ($order->latestEstimate?->status ?? ''),
                'total_amount' => max((int) ($order->latestEstimate?->total_amount ?? 0), 0),
                'created_at' => $order->latestEstimate?->created_at?->toIso8601String(),
            ],
            'service_history' => $serviceHistory,
            'spare_part_catalog' => $sparePartCatalog['rows'] ?? [],
            'context_note' => $this->sanitizeNullableString($validated['context_note'] ?? null),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, string|bool>  $promptSetting
     * @param  array<string, mixed>  $tenantPromptOverride
     */
    private function buildInstructions(array $promptSetting, array $tenantPromptOverride): string
    {
        $instructionBlocks = [
            trim((string) ($promptSetting['system_prompt'] ?? self::DEFAULT_SYSTEM_PROMPT)),
            trim((string) ($promptSetting['feature_prompt'] ?? self::DEFAULT_FEATURE_PROMPT)),
        ];

        if ($tenantPromptOverride !== []) {
            $instructionBlocks[] = 'Preferensi tenant:';
            $instructionBlocks[] = json_encode($tenantPromptOverride, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return collect($instructionBlocks)
            ->filter(fn ($block): bool => is_string($block) && trim($block) !== '')
            ->map(fn (string $block): string => trim($block))
            ->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildUserPrompt(array $payload): string
    {
        return "Data input estimasi servis:\n".json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param  mixed  $response
     */
    private function extractResponseText(mixed $response): string
    {
        if (! is_object($response)) {
            throw ValidationException::withMessages([
                'estimate_ai' => 'Respons AI tidak valid.',
            ]);
        }

        $candidateValues = [
            data_get($response, 'text'),
            data_get($response, 'raw.choices.0.message.content'),
            data_get($response, 'raw.choices.0.text'),
            data_get($response, 'raw.output_text'),
        ];

        foreach ($candidateValues as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = trim($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $stringResponse = trim((string) $response);
        if ($stringResponse !== '') {
            return $stringResponse;
        }

        throw ValidationException::withMessages([
            'estimate_ai' => 'AI tidak mengembalikan hasil yang bisa diproses.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAiJsonPayload(string $responseText): array
    {
        $normalizedText = trim($responseText);
        if ($normalizedText === '') {
            throw ValidationException::withMessages([
                'estimate_ai' => 'AI tidak mengembalikan konten estimasi.',
            ]);
        }

        $decoded = json_decode($normalizedText, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $normalizedText, $matches) === 1) {
            $decodedFromBlock = json_decode((string) ($matches[1] ?? ''), true);
            if (is_array($decodedFromBlock)) {
                return $decodedFromBlock;
            }
        }

        $firstBracePosition = strpos($normalizedText, '{');
        $lastBracePosition = strrpos($normalizedText, '}');
        if ($firstBracePosition !== false && $lastBracePosition !== false && $lastBracePosition > $firstBracePosition) {
            $jsonCandidate = substr(
                $normalizedText,
                $firstBracePosition,
                ($lastBracePosition - $firstBracePosition) + 1,
            );
            $decodedFromSubstring = json_decode((string) $jsonCandidate, true);
            if (is_array($decodedFromSubstring)) {
                return $decodedFromSubstring;
            }
        }

        throw ValidationException::withMessages([
            'estimate_ai' => 'Format respons AI belum sesuai. Silakan generate ulang.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $decodedPayload
     * @param  array<string, array<string, mixed>>  $sparePartLookup
     * @param  array<int, array<string, mixed>>  $sparePartRows
     * @return array<string, mixed>
     */
    private function normalizeDraftPayload(array $decodedPayload, array $sparePartLookup, array $sparePartRows): array
    {
        $sourceItems = collect(data_get($decodedPayload, 'items', []))
            ->filter(fn ($item): bool => is_array($item))
            ->values();

        if ($sourceItems->isEmpty()) {
            throw ValidationException::withMessages([
                'estimate_ai' => 'AI belum menghasilkan item estimasi yang valid.',
            ]);
        }

        $warnings = collect();

        $items = $sourceItems
            ->map(function (array $item) use ($sparePartLookup, $sparePartRows, $warnings): ?array {
                $itemType = strtolower(trim((string) data_get($item, 'item_type', 'service')));
                $itemType = in_array($itemType, ['service', 'sparepart'], true) ? $itemType : 'service';

                $label = trim((string) data_get($item, 'label', ''));
                if ($label === '') {
                    return null;
                }

                $description = $this->sanitizeNullableString(
                    data_get($item, 'description') ?? data_get($item, 'reason'),
                );
                $unitPrice = max((int) data_get($item, 'unit_price', 0), 0);
                $qty = max((int) data_get($item, 'qty', 1), 1);
                $unitLabel = $this->sanitizeNullableString(data_get($item, 'unit_label'));
                $sparePartId = null;

                if ($itemType === 'sparepart') {
                    $sparePartName = trim((string) (data_get($item, 'spare_part_name') ?? $label));
                    $matchResult = $this->resolveMatchedSparePart(
                        $item,
                        $sparePartName,
                        $sparePartLookup,
                        $sparePartRows,
                    );
                    $matchedSparePart = $matchResult['row'] ?? null;
                    $matchType = (string) ($matchResult['match_type'] ?? '');

                    if (is_array($matchedSparePart)) {
                        $sparePartId = (string) ($matchedSparePart['id'] ?? '');
                        $label = trim((string) ($matchedSparePart['name'] ?? $label));
                        $unitLabel = $this->sanitizeNullableString(
                            (string) ($matchedSparePart['unit'] ?? $unitLabel ?? ''),
                        );

                        if ($unitPrice < 1) {
                            $unitPrice = max((int) ($matchedSparePart['selling_price'] ?? 0), 0);
                        }

                        if ($matchType === 'fuzzy') {
                            $warnings->push(
                                "Sparepart '{$sparePartName}' dipetakan ke katalog '{$label}'. Pastikan item sudah sesuai.",
                            );
                        }
                    } else {
                        $itemType = 'service';
                        $qty = 1;
                        $unitLabel = null;
                        $warnings->push("Sparepart '{$sparePartName}' belum ditemukan di katalog aktif tenant.");
                    }
                } else {
                    $qty = 1;
                    $unitLabel = null;
                }

                return [
                    'item_type' => $itemType,
                    'label' => $label,
                    'description' => $description,
                    'unit_label' => $unitLabel,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'spare_part_id' => $sparePartId,
                ];
            })
            ->filter(fn ($item): bool => is_array($item))
            ->take(20)
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'estimate_ai' => 'AI belum menghasilkan draft item estimasi yang bisa dipakai.',
            ]);
        }

        $notes = collect(data_get($decodedPayload, 'risk_notes', []))
            ->merge(data_get($decodedPayload, 'advice', []))
            ->map(fn ($note): string => trim((string) $note))
            ->filter(fn (string $note): bool => $note !== '')
            ->values()
            ->take(8)
            ->all();

        $overallConfidence = (int) data_get($decodedPayload, 'overall_confidence', 0);
        if ($overallConfidence < 1 || $overallConfidence > 100) {
            $confidenceFromItems = collect($sourceItems)
                ->map(fn (array $item): int => max(0, min(100, (int) data_get($item, 'confidence', 0))))
                ->filter(fn (int $confidence): bool => $confidence > 0);

            $overallConfidence = $confidenceFromItems->isNotEmpty()
                ? (int) round((float) $confidenceFromItems->average())
                : 0;
        }

        return [
            'items' => $items->all(),
            'confidence_level' => max(0, min(100, $overallConfidence)),
            'warnings' => $warnings
                ->map(fn (string $warning): string => trim($warning))
                ->filter(fn (string $warning): bool => $warning !== '')
                ->values()
                ->all(),
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, array<string, mixed>>  $sparePartLookup
     * @param  array<int, array<string, mixed>>  $sparePartRows
     * @return array{row?: array<string, mixed>, match_type?: string}
     */
    private function resolveMatchedSparePart(
        array $item,
        string $sparePartName,
        array $sparePartLookup,
        array $sparePartRows,
    ): array {
        $candidateId = trim((string) data_get($item, 'spare_part_id', ''));
        if ($candidateId !== '') {
            $matchedById = collect($sparePartRows)
                ->first(fn (array $row): bool => trim((string) ($row['id'] ?? '')) === $candidateId);

            if (is_array($matchedById)) {
                return [
                    'row' => $matchedById,
                    'match_type' => 'id',
                ];
            }
        }

        $candidateNames = collect([
            $sparePartName,
            trim((string) data_get($item, 'spare_part_name', '')),
            trim((string) data_get($item, 'label', '')),
        ])
            ->map(fn ($name): string => trim((string) $name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values();

        foreach ($candidateNames as $candidateName) {
            $lookupKey = $this->normalizeLookupKey($candidateName);
            $matchedByName = $sparePartLookup[$lookupKey] ?? null;
            if (is_array($matchedByName)) {
                return [
                    'row' => $matchedByName,
                    'match_type' => 'exact',
                ];
            }
        }

        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($sparePartRows as $row) {
            $catalogName = trim((string) ($row['name'] ?? ''));
            if ($catalogName === '') {
                continue;
            }

            $catalogKey = $this->normalizeLookupKey($catalogName);
            if ($catalogKey === '') {
                continue;
            }

            foreach ($candidateNames as $candidateName) {
                $candidateKey = $this->normalizeLookupKey($candidateName);
                if ($candidateKey === '') {
                    continue;
                }

                $score = $this->calculateSparePartMatchScore($candidateKey, $catalogKey);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $row;
                }
            }
        }

        if (is_array($bestMatch) && $bestScore >= 0.74) {
            return [
                'row' => $bestMatch,
                'match_type' => 'fuzzy',
            ];
        }

        return [];
    }

    private function calculateSparePartMatchScore(string $requestedKey, string $catalogKey): float
    {
        if ($requestedKey === '' || $catalogKey === '') {
            return 0.0;
        }

        if ($requestedKey === $catalogKey) {
            return 1.0;
        }

        if (str_contains($requestedKey, $catalogKey) || str_contains($catalogKey, $requestedKey)) {
            return 0.92;
        }

        similar_text($requestedKey, $catalogKey, $similarityPercent);
        $similarityScore = max(0.0, min(1.0, ((float) $similarityPercent) / 100));

        $requestedTokens = collect(explode(' ', $requestedKey))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => $token !== '' && Str::length($token) >= 3)
            ->values();
        $catalogTokens = collect(explode(' ', $catalogKey))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => $token !== '' && Str::length($token) >= 3)
            ->values();

        $tokenOverlapScore = 0.0;
        if ($requestedTokens->isNotEmpty() && $catalogTokens->isNotEmpty()) {
            $intersectionCount = $requestedTokens->intersect($catalogTokens)->count();
            $tokenOverlapScore = $intersectionCount / max($requestedTokens->count(), $catalogTokens->count());
        }

        return max($similarityScore, $tokenOverlapScore);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function calculateTotals(array $items): array
    {
        $subtotalService = 0;
        $subtotalSparepart = 0;

        foreach ($items as $item) {
            $itemType = strtolower(trim((string) ($item['item_type'] ?? 'service')));
            $qty = max((int) ($item['qty'] ?? 1), 1);
            $unitPrice = max((int) ($item['unit_price'] ?? 0), 0);
            $subtotal = $qty * $unitPrice;

            if ($itemType === 'sparepart') {
                $subtotalSparepart += $subtotal;
                continue;
            }

            $subtotalService += $subtotal;
        }

        return [
            'subtotal_service' => $subtotalService,
            'subtotal_sparepart' => $subtotalSparepart,
            'total_amount' => $subtotalService + $subtotalSparepart,
        ];
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function shouldApplyCustomerWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasCustomerWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function shouldApplyWarehouseStockWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasWarehouseStockWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function hasCustomerWorkshopScope(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasColumn('customers', 'workshop_id');
    }

    private function hasWarehouseStockWorkshopScope(): bool
    {
        return Schema::hasTable('warehouse_spare_part_stocks')
            && Schema::hasColumn('warehouse_spare_part_stocks', 'workshop_id');
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

    private function normalizeLookupKey(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function sanitizeNullableString(mixed $value): ?string
    {
        $normalized = trim(strip_tags((string) $value));

        return $normalized !== '' ? $normalized : null;
    }
}
