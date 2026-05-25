<?php

namespace App\Services\Owner;

use App\Models\PlatformAiPromptSetting;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEstimateAiLog;
use App\Models\TenantAiPromptOverride;
use App\Models\Workshop;
use App\Services\Platform\PlatformAiAgentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerServiceOrderDiagnosisAiService
{
    private const FEATURE_KEY = 'symptom_diagnosis_v1';

    private const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah asisten diagnosa awal bengkel.
Fokus pada keselamatan, kejelasan gejala, dan dugaan penyebab yang paling mungkin.
Jangan pernah memberi keputusan final; tekankan bahwa hasil ini adalah hipotesis awal dan tetap wajib dicek teknisi.
PROMPT;

    private const DEFAULT_FEATURE_PROMPT = <<<'PROMPT'
Analisis keluhan, gejala, dan data kendaraan untuk menyusun diagnosa awal yang bisa dibaca frontdesk.
Output WAJIB JSON valid tanpa teks tambahan dengan struktur:
{
  "summary": "string",
  "possible_causes": [
    {
      "label": "string",
      "confidence": 0-100,
      "severity": "high|medium|low",
      "reason": "string",
      "recommended_checks": ["string"],
      "recommended_actions": ["string"]
    }
  ],
  "warnings": ["string"],
  "customer_advice": ["string"],
  "disclaimer": "string|null"
}
Aturan:
- Minimal 1 dugaan penyebab jika ada data gejala yang cukup.
- Maksimal 3 dugaan penyebab.
- Jangan tambahkan field di luar struktur.
- Jangan menyatakan hasil sebagai kepastian final.
- Gunakan bahasa yang mudah dipahami frontdesk dan customer.
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
        $inputPayload = $this->buildInputPayload($order, $validated);
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
            $normalizedDraft = $this->normalizeDraftPayload($decodedPayload, $inputPayload);

            $latencyMs = max(1, (int) round((microtime(true) - $startedAt) * 1000));
            $promptTokens = max(0, (int) data_get($response, 'usage.promptTokens', 0));
            $completionTokens = max(0, (int) data_get($response, 'usage.completionTokens', 0));
            $totalTokens = $promptTokens + $completionTokens;
            $confidenceLevel = $this->resolveConfidenceLevel($normalizedDraft['possible_causes']);

            $draftPayload = [
                'order_id' => (string) $order->id,
                'feature_key' => self::FEATURE_KEY,
                'generated_at' => now()->toIso8601String(),
                'confidence_level' => $confidenceLevel,
                'summary' => (string) ($normalizedDraft['summary'] ?? ''),
                'possible_causes' => $normalizedDraft['possible_causes'],
                'warnings' => $normalizedDraft['warnings'],
                'customer_advice' => $normalizedDraft['customer_advice'],
                'disclaimer' => $normalizedDraft['disclaimer'],
                'symptoms' => $inputPayload['symptoms'] ?? [],
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

            Log::info('owner.service-diagnosis.ai-generated', [
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
                'diagnosis_ai' => is_string($message) && trim($message) !== ''
                    ? trim($message)
                    : 'Generate diagnosa AI gagal. Silakan cek konfigurasi AI agent.',
            ]);
        } catch (\Throwable $throwable) {
            $this->logFailedGeneration(
                $logPayload,
                $startedAt,
                $throwable->getMessage(),
            );

            throw ValidationException::withMessages([
                'diagnosis_ai' => 'Generate diagnosa AI gagal. Silakan coba beberapa saat lagi.',
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
                'diagnosis_ai' => 'Tabel service order belum siap.',
            ]);
        }
    }

    private function findTenantOrderOrFail(string $tenantId, string $activeWorkshopId, string $orderId): ServiceOrder
    {
        $order = ServiceOrder::query()
            ->with([
                'customer:id,name,phone,workshop_id',
                'vehicle:id,brand,model,variant,year,plate_number',
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
                'diagnosis_ai' => 'Data servis tidak ditemukan di cabang aktif.',
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
                'name' => 'Symptom Diagnosis Prompt V1',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'feature_prompt' => self::DEFAULT_FEATURE_PROMPT,
                'is_active' => true,
            ];
        }

        $setting = PlatformAiPromptSetting::query()->firstOrCreate(
            ['feature_key' => self::FEATURE_KEY],
            [
                'name' => 'Symptom Diagnosis Prompt V1',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'feature_prompt' => self::DEFAULT_FEATURE_PROMPT,
                'is_active' => true,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
            ],
        );

        $isActive = (bool) ($setting->is_active ?? true);

        return [
            'name' => (string) ($setting->name ?? 'Symptom Diagnosis Prompt V1'),
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
            'additional_constraints' => $this->sanitizeNullableString($override->additional_constraints ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildInputPayload(ServiceOrder $order, array $validated): array
    {
        $symptoms = $this->resolveSymptoms($order, $validated);

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
            'vehicle' => [
                'brand' => (string) ($order->vehicle?->brand ?? ''),
                'model' => (string) ($order->vehicle?->model ?? ''),
                'variant' => (string) ($order->vehicle?->variant ?? ''),
                'year' => $order->vehicle?->year !== null ? (int) $order->vehicle->year : null,
                'plate_number' => (string) ($order->vehicle?->plate_number ?? ''),
            ],
            'customer' => [
                'name' => (string) ($order->customer?->name ?? ''),
                'phone' => (string) ($order->customer?->phone ?? ''),
            ],
            'symptoms' => $symptoms,
            'note' => $this->sanitizeNullableString($validated['context_note'] ?? null),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, string>
     */
    private function resolveSymptoms(ServiceOrder $order, array $validated): array
    {
        $symptomsFromInput = collect($validated['symptoms'] ?? [])
            ->map(fn ($symptom): string => trim((string) $symptom))
            ->filter(fn (string $symptom): bool => $symptom !== '')
            ->unique()
            ->values();

        if ($symptomsFromInput->isNotEmpty()) {
            return $symptomsFromInput->take(10)->all();
        }

        $fallbackSymptoms = collect([
            (string) ($order->complaint ?? ''),
            (string) ($order->vehicle_condition ?? ''),
        ])
            ->flatMap(function (string $source): array {
                return preg_split('/[\r\n,.;]+/', $source) ?: [];
            })
            ->map(fn ($symptom): string => trim($symptom))
            ->filter(fn (string $symptom): bool => $symptom !== '' && Str::length($symptom) >= 4)
            ->unique()
            ->values();

        if ($fallbackSymptoms->isNotEmpty()) {
            return $fallbackSymptoms->take(8)->all();
        }

        $complaint = trim((string) ($order->complaint ?? ''));

        return $complaint !== '' ? [$complaint] : [];
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
        return "Data input diagnosa awal:\n".json_encode(
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
                'diagnosis_ai' => 'Respons AI tidak valid.',
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
            'diagnosis_ai' => 'AI tidak mengembalikan hasil yang bisa diproses.',
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
                'diagnosis_ai' => 'AI tidak mengembalikan konten diagnosa.',
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
            'diagnosis_ai' => 'Format respons AI belum sesuai. Silakan generate ulang.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $decodedPayload
     * @param  array<string, mixed>  $inputPayload
     * @return array<string, mixed>
     */
    private function normalizeDraftPayload(array $decodedPayload, array $inputPayload): array
    {
        $summary = $this->sanitizeNullableString(data_get($decodedPayload, 'summary'));

        $possibleCauses = collect(data_get($decodedPayload, 'possible_causes', []))
            ->filter(fn ($cause): bool => is_array($cause))
            ->map(function (array $cause): ?array {
                $label = $this->sanitizeNullableString(data_get($cause, 'label'));
                if ($label === null) {
                    return null;
                }

                $severity = strtolower(trim((string) data_get($cause, 'severity', 'medium')));
                if (! in_array($severity, ['high', 'medium', 'low'], true)) {
                    $severity = 'medium';
                }

                return [
                    'label' => $label,
                    'confidence' => max(0, min(100, (int) data_get($cause, 'confidence', 0))),
                    'severity' => $severity,
                    'reason' => $this->sanitizeNullableString(data_get($cause, 'reason')),
                    'recommended_checks' => $this->normalizeStringList(data_get($cause, 'recommended_checks'), 5),
                    'recommended_actions' => $this->normalizeStringList(data_get($cause, 'recommended_actions'), 5),
                ];
            })
            ->filter(fn ($cause): bool => is_array($cause))
            ->values()
            ->take(5)
            ->all();

        $warnings = $this->normalizeStringList(data_get($decodedPayload, 'warnings'), 8);
        $customerAdvice = $this->normalizeStringList(data_get($decodedPayload, 'customer_advice'), 8);
        $disclaimer = $this->sanitizeNullableString(data_get($decodedPayload, 'disclaimer'));

        if (count($possibleCauses) < 1 && $summary === null) {
            $sourceSymptoms = collect($inputPayload['symptoms'] ?? [])
                ->map(fn ($symptom): string => trim((string) $symptom))
                ->filter(fn (string $symptom): bool => $symptom !== '')
                ->values();

            if ($sourceSymptoms->isEmpty()) {
                throw ValidationException::withMessages([
                    'diagnosis_ai' => 'Data gejala masih kurang untuk membuat diagnosa awal AI.',
                ]);
            }

            throw ValidationException::withMessages([
                'diagnosis_ai' => 'AI belum menghasilkan diagnosa awal yang valid. Silakan generate ulang.',
            ]);
        }

        if ($summary === null && count($possibleCauses) > 0) {
            $topCause = (string) ($possibleCauses[0]['label'] ?? '');
            $summary = $topCause !== ''
                ? "Dugaan awal mengarah ke {$topCause}. Perlu verifikasi teknisi."
                : 'Diagnosa awal berhasil dibuat, lanjutkan verifikasi teknisi.';
        }

        return [
            'summary' => (string) ($summary ?? ''),
            'possible_causes' => $possibleCauses,
            'warnings' => $warnings,
            'customer_advice' => $customerAdvice,
            'disclaimer' => $disclaimer,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value, int $limit): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($row): string => trim(strip_tags((string) $row)))
            ->filter(fn (string $row): bool => $row !== '')
            ->unique()
            ->values()
            ->take(max(1, $limit))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $possibleCauses
     */
    private function resolveConfidenceLevel(array $possibleCauses): int
    {
        $levels = collect($possibleCauses)
            ->map(fn (array $cause): int => max(0, min(100, (int) ($cause['confidence'] ?? 0))))
            ->filter(fn (int $confidence): bool => $confidence > 0)
            ->values();

        if ($levels->isEmpty()) {
            return 0;
        }

        return max(0, min(100, (int) round((float) $levels->average())));
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

    private function hasCustomerWorkshopScope(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasColumn('customers', 'workshop_id');
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

    private function sanitizeNullableString(mixed $value): ?string
    {
        $normalized = trim(strip_tags((string) $value));

        return $normalized !== '' ? $normalized : null;
    }
}
