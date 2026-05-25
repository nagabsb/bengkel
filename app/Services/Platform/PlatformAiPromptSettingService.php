<?php

namespace App\Services\Platform;

use App\Models\AiRuntimeLog;
use App\Models\PlatformAiPromptSetting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlatformAiPromptSettingService
{
    private ?bool $featurePromptConfigColumnExists = null;

    public function __construct(
        private readonly PlatformAiAgentService $platformAiAgentService,
    ) {}

    /**
     * @var array<string, array<string, mixed>>
     */
    private const PROMPT_CATALOG = [
        'service_estimate_v1' => [
            'name' => 'Estimasi Biaya Servis + Rekomendasi Pekerjaan',
            'description' => 'Dipakai saat generate draft estimasi servis di frontdesk/SA.',
            'test_input_template' => <<<'PROMPT'
{
  "order": {
    "complaint": "Mesin bergetar saat langsam dan ada bunyi di area rem depan",
    "odometer": 68500
  },
  "vehicle": {
    "brand": "Toyota",
    "model": "Avanza",
    "year": 2019
  },
  "note": "Customer minta estimasi sebelum pekerjaan dimulai."
}
PROMPT,
            'system_prompt' => <<<'PROMPT'
Kamu adalah asisten estimasi servis bengkel.
Prioritaskan keselamatan, transparansi biaya, dan kejelasan untuk customer.
Jangan pernah memberi keputusan final; selalu tekankan estimasi awal dan perlu review Service Advisor.
PROMPT,
            'default_feature_prompt_config' => [
                'max_items' => 6,
                'prioritize_safety' => true,
                'include_confidence' => true,
                'include_risk_notes' => true,
                'include_advice' => true,
                'include_item_reason' => true,
                'include_disclaimer' => true,
                'disclaimer_text' => 'Estimasi awal, final setelah inspeksi teknisi.',
                'review_focus' => 'Utamakan item yang paling mungkin dibutuhkan agar approval customer lebih cepat.',
            ],
        ],
        'sparepart_reorder_v1' => [
            'name' => 'Prediksi Reorder Sparepart',
            'description' => 'Dipakai untuk rekomendasi restock agar stok tidak kosong/overstock.',
            'test_input_template' => <<<'PROMPT'
{
  "period_days": 30,
  "items": [
    {
      "spare_part_name": "Oli Mesin 10W-40",
      "current_stock": 8,
      "avg_daily_usage": 1.2,
      "lead_time_days": 7
    },
    {
      "spare_part_name": "Kampas Rem Depan",
      "current_stock": 3,
      "avg_daily_usage": 0.4,
      "lead_time_days": 10
    }
  ]
}
PROMPT,
            'system_prompt' => <<<'PROMPT'
Kamu adalah asisten perencanaan stok sparepart bengkel.
Fokus pada keseimbangan stok, cashflow sehat, dan pencegahan stockout.
Hasil AI adalah rekomendasi awal, tetap perlu review owner/purchasing.
PROMPT,
            'default_feature_prompt_config' => [
                'max_recommendations' => 5,
                'prioritize_fast_moving' => true,
                'include_priority' => true,
                'include_confidence' => true,
                'include_warnings' => true,
                'include_summary' => true,
                'reorder_unit_label' => 'pcs',
                'summary_focus' => 'Utamakan item dengan risiko stockout tertinggi dan dampak operasional paling besar.',
            ],
        ],
        'symptom_diagnosis_v1' => [
            'name' => 'Diagnosa Awal dari Gejala Kendaraan',
            'description' => 'Dipakai untuk membantu frontdesk menyusun dugaan penyebab awal dari gejala customer.',
            'test_input_template' => <<<'PROMPT'
{
  "order": {
    "complaint": "Mesin brebet saat langsam dan tarikan terasa berat"
  },
  "vehicle": {
    "brand": "Honda",
    "model": "Beat",
    "year": 2021,
    "odometer": 24500
  },
  "symptoms": [
    "Lampu indikator mesin kadang menyala",
    "Mesin terasa pincang saat berhenti",
    "Keluhan lebih terasa saat mesin panas"
  ],
  "note": "Customer meminta gambaran awal sebelum motor ditinggal."
}
PROMPT,
            'system_prompt' => <<<'PROMPT'
Kamu adalah asisten diagnosa awal bengkel.
Fokus pada keselamatan, kejelasan gejala, dan dugaan penyebab yang paling mungkin.
Jangan pernah memberi keputusan final; tekankan bahwa hasil ini adalah hipotesis awal dan tetap wajib dicek teknisi.
PROMPT,
            'default_feature_prompt_config' => [
                'max_possible_causes' => 3,
                'prioritize_safety_risk' => true,
                'include_confidence' => true,
                'include_recommended_checks' => true,
                'include_recommended_actions' => true,
                'include_warnings' => true,
                'include_customer_advice' => true,
                'include_disclaimer' => true,
                'disclaimer_text' => 'Diagnosa awal, hasil final setelah pemeriksaan teknisi.',
                'diagnosis_focus' => 'Utamakan dugaan penyebab yang paling mungkin dan mudah dijelaskan ke customer.',
            ],
        ],
        'monthly_business_report_v1' => [
            'name' => 'Laporan AI Bulanan',
            'description' => 'Dipakai untuk merangkum performa bulanan bengkel dalam format siap review owner/manager.',
            'test_input_template' => <<<'PROMPT'
{
  "period": {
    "month": 3,
    "year": 2026
  },
  "revenue": {
    "total": 125000000,
    "service": 73000000,
    "sparepart": 52000000,
    "gross_profit_estimate": 38500000
  },
  "orders": {
    "total": 186,
    "completed": 172,
    "pending": 14
  },
  "customers": {
    "new": 48,
    "returning": 97
  },
  "note": "Bulan ini ada promo tune up dan keterlambatan suplai kampas rem."
}
PROMPT,
            'system_prompt' => <<<'PROMPT'
Kamu adalah asisten analisis laporan bulanan bengkel.
Fokus pada insight yang bisa ditindaklanjuti owner/manager: tren keuangan, operasional, risiko, dan prioritas bulan berikutnya.
Jangan menampilkan data fiktif; jika data belum cukup, sebutkan keterbatasan secara eksplisit.
PROMPT,
            'default_feature_prompt_config' => [
                'max_highlights' => 5,
                'include_financial_summary' => true,
                'include_operational_summary' => true,
                'include_risks' => true,
                'include_recommendations' => true,
                'include_next_month_focus' => true,
                'include_disclaimer' => true,
                'disclaimer_text' => 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
                'report_focus' => 'Sorot tren omzet, efisiensi order servis, dan tindakan prioritas untuk bulan berikutnya.',
            ],
        ],
    ];

    /**
     * @return array{promptSettings: array<int, array<string, mixed>>}
     */
    public function buildPageData(): array
    {
        return [
            'promptSettings' => $this->resolvePromptSettings(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updatePromptSetting(
        string $featureKey,
        array $validated,
        ?Authenticatable $actor = null,
    ): void {
        $this->assertTableReady();

        $catalog = $this->resolveCatalogOrFail($featureKey);
        $actorId = $this->resolveActorUserId($actor);
        $defaultConfig = $this->resolveDefaultFeaturePromptConfig($featureKey, $catalog);
        $defaultFeaturePrompt = $this->buildFeaturePromptFromConfig($featureKey, $defaultConfig);

        $createAttributes = [
            'name' => (string) $catalog['name'],
            'system_prompt' => (string) $catalog['system_prompt'],
            'feature_prompt' => $defaultFeaturePrompt,
            'is_active' => true,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
        ];

        if ($this->supportsFeaturePromptConfig()) {
            $createAttributes['feature_prompt_config'] = $defaultConfig;
        }

        $setting = PlatformAiPromptSetting::query()->firstOrCreate(
            ['feature_key' => $featureKey],
            $createAttributes,
        );

        $hasStructuredConfig = array_key_exists('feature_prompt_config', $validated)
            && is_array($validated['feature_prompt_config']);

        $normalizedConfig = $hasStructuredConfig
            ? $this->normalizeFeaturePromptConfig($featureKey, $validated['feature_prompt_config'])
            : null;

        $payload = [
            'name' => (string) $catalog['name'],
            'system_prompt' => $this->sanitizePrompt(
                (string) ($validated['system_prompt'] ?? $catalog['system_prompt']),
                (string) $catalog['system_prompt'],
            ),
            'feature_prompt' => $normalizedConfig !== null
                ? $this->buildFeaturePromptFromConfig($featureKey, $normalizedConfig)
                : $this->sanitizePrompt(
                    (string) ($validated['feature_prompt'] ?? $defaultFeaturePrompt),
                    $defaultFeaturePrompt,
                ),
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : (bool) ($setting->is_active ?? true),
            'updated_by_user_id' => $actorId,
        ];

        if ($this->supportsFeaturePromptConfig() && $normalizedConfig !== null) {
            $payload['feature_prompt_config'] = $normalizedConfig;
        }

        $setting->forceFill($payload)->save();

        Log::info('platform.ai-prompt-setting.updated', [
            'tenant_id' => null,
            'feature_key' => $featureKey,
            'updated_by_user_id' => $actorId,
            'is_active' => (bool) $setting->is_active,
            'uses_structured_builder' => $normalizedConfig !== null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{ok: bool, message: string, result: ?array<string, mixed>}
     */
    public function testPromptOutput(
        string $featureKey,
        array $validated = [],
        ?Authenticatable $actor = null,
    ): array
    {
        $catalog = $this->resolveCatalogOrFail($featureKey);
        $setting = $this->resolvePromptSettingForFeature($featureKey, $catalog);
        $defaultConfig = $this->resolveDefaultFeaturePromptConfig($featureKey, $catalog);
        $actorId = $this->resolveActorUserId($actor);
        $systemPrompt = $this->sanitizePrompt(
            (string) ($validated['system_prompt'] ?? ''),
            (string) ($setting['system_prompt'] ?? $catalog['system_prompt']),
        );

        $featurePromptConfig = array_key_exists('feature_prompt_config', $validated)
            && is_array($validated['feature_prompt_config'])
            ? $this->normalizeFeaturePromptConfig($featureKey, $validated['feature_prompt_config'])
            : $this->normalizeFeaturePromptConfig(
                $featureKey,
                is_array($setting['feature_prompt_config'] ?? null)
                    ? $setting['feature_prompt_config']
                    : $defaultConfig,
            );

        $featurePrompt = array_key_exists('feature_prompt_config', $validated)
            && is_array($validated['feature_prompt_config'])
            ? $this->buildFeaturePromptFromConfig($featureKey, $featurePromptConfig)
            : $this->sanitizePrompt(
                (string) ($validated['feature_prompt'] ?? ''),
                (string) ($setting['feature_prompt'] ?? $this->buildFeaturePromptFromConfig($featureKey, $featurePromptConfig)),
            );

        $instructions = collect([
            $systemPrompt,
            $featurePrompt,
        ])
            ->filter(fn (string $block): bool => $block !== '')
            ->implode("\n\n");

        $testInput = trim((string) ($validated['test_input'] ?? ''));
        if ($testInput === '') {
            $testInput = trim((string) ($catalog['test_input_template'] ?? ''));
        }

        $prompt = implode("\n\n", [
            "Feature key: {$featureKey}",
            'Jalankan prompt ini menggunakan data uji berikut:',
            $testInput,
        ]);

        try {
            $runtimeResult = $this->platformAiAgentService->promptWithFailover($prompt, $instructions, 45, [
                'source' => 'platform_prompt_test',
                'feature_key' => $featureKey,
                'tenant_id' => null,
                'requester_user_id' => $actorId,
                'service_order_id' => null,
            ]);
            $response = $runtimeResult['response'] ?? null;
            $outputText = $this->extractResponseText($response);
            $parsedOutput = $this->extractJsonPayload($outputText);
            $promptTokens = max(0, (int) data_get($response, 'usage.promptTokens', 0));
            $completionTokens = max(0, (int) data_get($response, 'usage.completionTokens', 0));
            $totalTokens = $promptTokens + $completionTokens;

            $this->persistPromptTestRuntimePayload(
                featureKey: $featureKey,
                actorId: $actorId,
                runtimeResult: $runtimeResult,
                testInput: $testInput,
                outputText: $outputText,
                parsedOutput: $parsedOutput,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $totalTokens,
            );

            return [
                'ok' => true,
                'message' => 'Test output prompt berhasil dijalankan.',
                'result' => [
                    'feature_key' => $featureKey,
                    'feature_name' => (string) ($catalog['name'] ?? $featureKey),
                    'tested_at' => now()->toIso8601String(),
                    'input' => $testInput,
                    'output_text' => $outputText,
                    'output_json' => $parsedOutput,
                    'is_json_output' => is_array($parsedOutput),
                    'provider' => (string) ($runtimeResult['provider'] ?? ''),
                    'agent_model' => (string) ($runtimeResult['agent_model'] ?? ''),
                    'ai_agent_id' => (int) ($runtimeResult['agent_id'] ?? 0) > 0
                        ? (int) $runtimeResult['agent_id']
                        : null,
                    'token_usage' => [
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $totalTokens,
                    ],
                ],
            ];
        } catch (ValidationException $validationException) {
            $message = collect($validationException->errors())
                ->flatten()
                ->first();

            return [
                'ok' => false,
                'message' => is_string($message) && trim($message) !== ''
                    ? trim($message)
                    : 'Test output prompt gagal. Periksa konfigurasi AI agent.',
                'result' => null,
            ];
        } catch (\Throwable $throwable) {
            Log::warning('platform.ai-prompt-setting.test-output-failed', [
                'tenant_id' => null,
                'feature_key' => $featureKey,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Test output prompt gagal. Silakan coba lagi.',
                'result' => null,
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvePromptSettings(): array
    {
        $rows = [];

        foreach (self::PROMPT_CATALOG as $featureKey => $catalog) {
            $defaultConfig = $this->resolveDefaultFeaturePromptConfig($featureKey, $catalog);
            $defaultFeaturePrompt = $this->buildFeaturePromptFromConfig($featureKey, $defaultConfig);

            if (! Schema::hasTable('platform_ai_prompt_settings')) {
                $rows[] = [
                    'id' => null,
                    'feature_key' => $featureKey,
                    'name' => $catalog['name'],
                    'description' => $catalog['description'],
                    'test_input_template' => $catalog['test_input_template'],
                    'system_prompt' => $catalog['system_prompt'],
                    'feature_prompt' => $defaultFeaturePrompt,
                    'feature_prompt_config' => $defaultConfig,
                    'has_feature_prompt_config' => true,
                    'is_active' => true,
                    'updated_at' => null,
                ];

                continue;
            }

            $createAttributes = [
                'name' => (string) $catalog['name'],
                'system_prompt' => (string) $catalog['system_prompt'],
                'feature_prompt' => $defaultFeaturePrompt,
                'is_active' => true,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
            ];

            if ($this->supportsFeaturePromptConfig()) {
                $createAttributes['feature_prompt_config'] = $defaultConfig;
            }

            $setting = PlatformAiPromptSetting::query()->firstOrCreate(
                ['feature_key' => $featureKey],
                $createAttributes,
            );

            $rows[] = [
                'id' => (int) $setting->id,
                'feature_key' => $featureKey,
                'name' => $catalog['name'],
                'description' => $catalog['description'],
                'test_input_template' => $catalog['test_input_template'],
                'system_prompt' => $this->sanitizePrompt(
                    (string) ($setting->system_prompt ?? $catalog['system_prompt']),
                    (string) $catalog['system_prompt'],
                ),
                'feature_prompt' => $this->sanitizePrompt(
                    (string) ($setting->feature_prompt ?? ''),
                    $defaultFeaturePrompt,
                ),
                'feature_prompt_config' => $this->resolveStoredFeaturePromptConfig($featureKey, $setting, $defaultConfig),
                'has_feature_prompt_config' => $this->hasStoredFeaturePromptConfig($setting),
                'is_active' => (bool) ($setting->is_active ?? true),
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCatalogOrFail(string $featureKey): array
    {
        $normalizedFeatureKey = trim($featureKey);
        $catalog = self::PROMPT_CATALOG[$normalizedFeatureKey] ?? null;

        if (! is_array($catalog)) {
            throw ValidationException::withMessages([
                'feature_key' => 'Kategori prompt AI tidak valid.',
            ]);
        }

        return $catalog;
    }

    private function sanitizePrompt(string $value, string $fallback): string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : trim($fallback);
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function resolvePromptSettingForFeature(string $featureKey, array $catalog): array
    {
        $defaultConfig = $this->resolveDefaultFeaturePromptConfig($featureKey, $catalog);
        $defaultFeaturePrompt = $this->buildFeaturePromptFromConfig($featureKey, $defaultConfig);

        if (! Schema::hasTable('platform_ai_prompt_settings')) {
            return [
                'system_prompt' => $catalog['system_prompt'],
                'feature_prompt' => $defaultFeaturePrompt,
                'feature_prompt_config' => $defaultConfig,
                'is_active' => true,
            ];
        }

        $createAttributes = [
            'name' => (string) $catalog['name'],
            'system_prompt' => (string) $catalog['system_prompt'],
            'feature_prompt' => $defaultFeaturePrompt,
            'is_active' => true,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];

        if ($this->supportsFeaturePromptConfig()) {
            $createAttributes['feature_prompt_config'] = $defaultConfig;
        }

        $setting = PlatformAiPromptSetting::query()->firstOrCreate(
            ['feature_key' => $featureKey],
            $createAttributes,
        );

        $isActive = (bool) ($setting->is_active ?? true);

        return [
            'system_prompt' => $isActive
                ? $this->sanitizePrompt((string) ($setting->system_prompt ?? ''), (string) $catalog['system_prompt'])
                : (string) $catalog['system_prompt'],
            'feature_prompt' => $isActive
                ? $this->sanitizePrompt((string) ($setting->feature_prompt ?? ''), $defaultFeaturePrompt)
                : $defaultFeaturePrompt,
            'feature_prompt_config' => $this->resolveStoredFeaturePromptConfig($featureKey, $setting, $defaultConfig),
            'is_active' => $isActive,
        ];
    }

    /**
     * @param  mixed  $response
     */
    private function extractResponseText(mixed $response): string
    {
        if (! is_object($response)) {
            return '';
        }

        $candidates = [
            data_get($response, 'text'),
            data_get($response, 'raw.choices.0.message.content'),
            data_get($response, 'raw.choices.0.text'),
            data_get($response, 'raw.output_text'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = trim($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return trim((string) $response);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonPayload(string $outputText): ?array
    {
        $normalized = trim($outputText);
        if ($normalized === '') {
            return null;
        }

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $normalized, $matches) === 1) {
            $decodedFromBlock = json_decode((string) ($matches[1] ?? ''), true);
            if (is_array($decodedFromBlock)) {
                return $decodedFromBlock;
            }
        }

        $firstBrace = strpos($normalized, '{');
        $lastBrace = strrpos($normalized, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonCandidate = substr($normalized, $firstBrace, ($lastBrace - $firstBrace) + 1);
            $decodedFromSubstring = json_decode((string) $jsonCandidate, true);
            if (is_array($decodedFromSubstring)) {
                return $decodedFromSubstring;
            }
        }

        return null;
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function assertTableReady(): void
    {
        if (! Schema::hasTable('platform_ai_prompt_settings')) {
            throw ValidationException::withMessages([
                'system_prompt' => 'Tabel pengaturan prompt AI belum siap. Jalankan migrasi terlebih dahulu.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function resolveDefaultFeaturePromptConfig(string $featureKey, array $catalog): array
    {
        $defaultConfig = $catalog['default_feature_prompt_config'] ?? [];

        return $this->normalizeFeaturePromptConfig(
            $featureKey,
            is_array($defaultConfig) ? $defaultConfig : [],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeFeaturePromptConfig(string $featureKey, array $config): array
    {
        return match ($featureKey) {
            'service_estimate_v1' => [
                'max_items' => $this->normalizeInteger($config, 'max_items', 6, 1, 12),
                'prioritize_safety' => $this->normalizeBoolean($config, 'prioritize_safety', true),
                'include_confidence' => $this->normalizeBoolean($config, 'include_confidence', true),
                'include_risk_notes' => $this->normalizeBoolean($config, 'include_risk_notes', true),
                'include_advice' => $this->normalizeBoolean($config, 'include_advice', true),
                'include_item_reason' => $this->normalizeBoolean($config, 'include_item_reason', true),
                'include_disclaimer' => $this->normalizeBoolean($config, 'include_disclaimer', true),
                'disclaimer_text' => $this->normalizeText(
                    $config,
                    'disclaimer_text',
                    'Estimasi awal, final setelah inspeksi teknisi.',
                    240,
                ),
                'review_focus' => $this->normalizeText(
                    $config,
                    'review_focus',
                    'Utamakan item yang paling mungkin dibutuhkan agar approval customer lebih cepat.',
                    240,
                ),
            ],
            'sparepart_reorder_v1' => [
                'max_recommendations' => $this->normalizeInteger($config, 'max_recommendations', 5, 1, 12),
                'prioritize_fast_moving' => $this->normalizeBoolean($config, 'prioritize_fast_moving', true),
                'include_priority' => $this->normalizeBoolean($config, 'include_priority', true),
                'include_confidence' => $this->normalizeBoolean($config, 'include_confidence', true),
                'include_warnings' => $this->normalizeBoolean($config, 'include_warnings', true),
                'include_summary' => $this->normalizeBoolean($config, 'include_summary', true),
                'reorder_unit_label' => $this->normalizeText($config, 'reorder_unit_label', 'pcs', 40),
                'summary_focus' => $this->normalizeText(
                    $config,
                    'summary_focus',
                    'Utamakan item dengan risiko stockout tertinggi dan dampak operasional paling besar.',
                    240,
                ),
            ],
            'symptom_diagnosis_v1' => [
                'max_possible_causes' => $this->normalizeInteger($config, 'max_possible_causes', 3, 1, 6),
                'prioritize_safety_risk' => $this->normalizeBoolean($config, 'prioritize_safety_risk', true),
                'include_confidence' => $this->normalizeBoolean($config, 'include_confidence', true),
                'include_recommended_checks' => $this->normalizeBoolean($config, 'include_recommended_checks', true),
                'include_recommended_actions' => $this->normalizeBoolean($config, 'include_recommended_actions', true),
                'include_warnings' => $this->normalizeBoolean($config, 'include_warnings', true),
                'include_customer_advice' => $this->normalizeBoolean($config, 'include_customer_advice', true),
                'include_disclaimer' => $this->normalizeBoolean($config, 'include_disclaimer', true),
                'disclaimer_text' => $this->normalizeText(
                    $config,
                    'disclaimer_text',
                    'Diagnosa awal, hasil final setelah pemeriksaan teknisi.',
                    240,
                ),
                'diagnosis_focus' => $this->normalizeText(
                    $config,
                    'diagnosis_focus',
                    'Utamakan dugaan penyebab yang paling mungkin dan mudah dijelaskan ke customer.',
                    240,
                ),
            ],
            'monthly_business_report_v1' => [
                'max_highlights' => $this->normalizeInteger($config, 'max_highlights', 5, 1, 10),
                'include_financial_summary' => $this->normalizeBoolean($config, 'include_financial_summary', true),
                'include_operational_summary' => $this->normalizeBoolean($config, 'include_operational_summary', true),
                'include_risks' => $this->normalizeBoolean($config, 'include_risks', true),
                'include_recommendations' => $this->normalizeBoolean($config, 'include_recommendations', true),
                'include_next_month_focus' => $this->normalizeBoolean($config, 'include_next_month_focus', true),
                'include_disclaimer' => $this->normalizeBoolean($config, 'include_disclaimer', true),
                'disclaimer_text' => $this->normalizeText(
                    $config,
                    'disclaimer_text',
                    'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
                    240,
                ),
                'report_focus' => $this->normalizeText(
                    $config,
                    'report_focus',
                    'Sorot tren omzet, efisiensi order servis, dan tindakan prioritas untuk bulan berikutnya.',
                    240,
                ),
            ],
            default => $config,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildFeaturePromptFromConfig(string $featureKey, array $config): string
    {
        $normalizedConfig = $this->normalizeFeaturePromptConfig($featureKey, $config);

        return match ($featureKey) {
            'service_estimate_v1' => $this->buildServiceEstimateFeaturePrompt($normalizedConfig),
            'sparepart_reorder_v1' => $this->buildSparepartReorderFeaturePrompt($normalizedConfig),
            'symptom_diagnosis_v1' => $this->buildSymptomDiagnosisFeaturePrompt($normalizedConfig),
            'monthly_business_report_v1' => $this->buildMonthlyBusinessReportFeaturePrompt($normalizedConfig),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildServiceEstimateFeaturePrompt(array $config): string
    {
        $maxItems = (int) ($config['max_items'] ?? 6);
        $disclaimerText = (string) ($config['disclaimer_text'] ?? 'Estimasi awal, final setelah inspeksi teknisi.');
        $reviewFocus = (string) ($config['review_focus'] ?? 'Utamakan item yang paling mungkin dibutuhkan agar approval customer lebih cepat.');

        $lines = [
            'Buat draft estimasi awal berdasarkan data order servis, riwayat servis, dan katalog sparepart tenant.',
            'Tujuan hasil:',
            "- Maksimal {$maxItems} item kombinasi jasa dan sparepart.",
            "- Fokus review: {$reviewFocus}",
            ($config['prioritize_safety'] ?? true)
                ? '- Prioritaskan pekerjaan yang berdampak ke keselamatan dan potensi kerusakan lanjutan.'
                : '- Prioritaskan item yang paling relevan dengan keluhan utama customer.',
            ($config['include_confidence'] ?? true)
                ? '- Sertakan confidence 0-100 pada setiap item dan overall_confidence.'
                : '- Tetap sediakan field confidence, tetapi isi nilainya 0 agar struktur JSON konsisten.',
            ($config['include_item_reason'] ?? true)
                ? '- Isi description dan reason singkat untuk tiap item agar frontdesk mudah menjelaskan ke customer.'
                : '- Kosongkan description dan reason dengan null jika penjelasan per item tidak dibutuhkan.',
            ($config['include_risk_notes'] ?? true)
                ? '- Isi risk_notes dengan risiko atau perhatian yang perlu dikomunikasikan ke customer.'
                : '- Isi risk_notes dengan array kosong [].',
            ($config['include_advice'] ?? true)
                ? '- Isi advice dengan saran tindak lanjut atau poin review Service Advisor.'
                : '- Isi advice dengan array kosong [].',
            ($config['include_disclaimer'] ?? true)
                ? "- Isi disclaimer dengan kalimat ini: {$disclaimerText}"
                : '- Isi disclaimer dengan null.',
            'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
            '{',
            '  "items": [',
            '    {',
            '      "item_type": "service|sparepart",',
            '      "label": "string",',
            '      "description": "string|null",',
            '      "qty": 1,',
            '      "unit_label": "string|null",',
            '      "unit_price": 0,',
            '      "spare_part_name": "string|null",',
            '      "confidence": 0-100,',
            '      "reason": "string|null"',
            '    }',
            '  ],',
            '  "overall_confidence": 0-100,',
            '  "risk_notes": ["string"],',
            '  "advice": ["string"],',
            '  "disclaimer": "string|null"',
            '}',
            'Aturan:',
            '- Minimal 1 item jika ada dasar yang cukup dari input.',
            "- Maksimal {$maxItems} item.",
            '- Jangan tambahkan field di luar struktur.',
            '- Untuk item service, qty selalu 1.',
            '- Untuk item sparepart, qty boleh > 1.',
            '- Jika data belum cukup, tetap beri estimasi awal paling masuk akal dan jelaskan keterbatasan pada risk_notes atau advice.',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildSparepartReorderFeaturePrompt(array $config): string
    {
        $maxRecommendations = (int) ($config['max_recommendations'] ?? 5);
        $unitLabel = (string) ($config['reorder_unit_label'] ?? 'pcs');
        $summaryFocus = (string) ($config['summary_focus'] ?? 'Utamakan item dengan risiko stockout tertinggi dan dampak operasional paling besar.');

        $lines = [
            'Analisis histori pemakaian sparepart dan stok saat ini untuk menghasilkan rekomendasi reorder.',
            'Tujuan hasil:',
            "- Maksimal {$maxRecommendations} rekomendasi reorder.",
            "- Fokus ringkasan: {$summaryFocus}",
            ($config['prioritize_fast_moving'] ?? true)
                ? '- Prioritaskan item fast-moving dan item yang berisiko stockout dalam lead time terdekat.'
                : '- Seimbangkan item fast-moving dengan item kritikal yang berdampak ke operasional bengkel.',
            ($config['include_priority'] ?? true)
                ? '- Isi priority dengan high, medium, atau low.'
                : '- Isi priority dengan medium agar struktur tetap konsisten tanpa penekanan prioritas.',
            ($config['include_confidence'] ?? true)
                ? '- Isi confidence 0-100 untuk tiap rekomendasi.'
                : '- Isi confidence dengan 0 agar struktur tetap konsisten.',
            ($config['include_warnings'] ?? true)
                ? '- Isi warnings jika ada risiko cashflow, lead time, atau data yang kurang lengkap.'
                : '- Isi warnings dengan array kosong [].',
            ($config['include_summary'] ?? true)
                ? '- Isi summary satu kalimat singkat yang siap dibaca owner atau tim purchasing.'
                : '- Isi summary dengan string kosong.',
            "- Gunakan satuan {$unitLabel} saat menyarankan qty reorder.",
            'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
            '{',
            '  "recommendations": [',
            '    {',
            '      "spare_part_name": "string",',
            '      "current_stock": 0,',
            '      "suggested_reorder_qty": 0,',
            '      "priority": "high|medium|low",',
            '      "reason": "string",',
            '      "confidence": 0-100',
            '    }',
            '  ],',
            '  "warnings": ["string"],',
            '  "summary": "string"',
            '}',
            'Aturan:',
            '- Prioritaskan item dengan risiko stockout tinggi.',
            "- Maksimal {$maxRecommendations} rekomendasi.",
            '- Hindari rekomendasi qty berlebihan tanpa alasan.',
            '- Jangan tambahkan field di luar struktur.',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildSymptomDiagnosisFeaturePrompt(array $config): string
    {
        $maxPossibleCauses = (int) ($config['max_possible_causes'] ?? 3);
        $disclaimerText = (string) ($config['disclaimer_text'] ?? 'Diagnosa awal, hasil final setelah pemeriksaan teknisi.');
        $diagnosisFocus = (string) ($config['diagnosis_focus'] ?? 'Utamakan dugaan penyebab yang paling mungkin dan mudah dijelaskan ke customer.');

        $lines = [
            'Analisis keluhan, gejala, dan data kendaraan untuk menyusun diagnosa awal yang bisa dibaca frontdesk.',
            'Tujuan hasil:',
            "- Maksimal {$maxPossibleCauses} dugaan penyebab.",
            "- Fokus diagnosa: {$diagnosisFocus}",
            ($config['prioritize_safety_risk'] ?? true)
                ? '- Naikkan prioritas dugaan yang berdampak ke keselamatan atau risiko kerusakan lanjutan.'
                : '- Fokus pada dugaan yang paling mungkin sesuai pola gejala utama.',
            ($config['include_confidence'] ?? true)
                ? '- Isi confidence 0-100 pada tiap dugaan penyebab.'
                : '- Isi confidence dengan 0 agar struktur tetap konsisten.',
            ($config['include_recommended_checks'] ?? true)
                ? '- Isi recommended_checks untuk langkah inspeksi awal teknisi.'
                : '- Isi recommended_checks dengan array kosong [].',
            ($config['include_recommended_actions'] ?? true)
                ? '- Isi recommended_actions untuk tindakan awal yang layak disarankan.'
                : '- Isi recommended_actions dengan array kosong [].',
            ($config['include_warnings'] ?? true)
                ? '- Isi warnings untuk risiko, larangan pemakaian, atau tanda bahaya yang perlu segera disampaikan.'
                : '- Isi warnings dengan array kosong [].',
            ($config['include_customer_advice'] ?? true)
                ? '- Isi customer_advice dengan saran singkat yang mudah dipahami customer.'
                : '- Isi customer_advice dengan array kosong [].',
            ($config['include_disclaimer'] ?? true)
                ? "- Isi disclaimer dengan kalimat ini: {$disclaimerText}"
                : '- Isi disclaimer dengan null.',
            'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
            '{',
            '  "summary": "string",',
            '  "possible_causes": [',
            '    {',
            '      "label": "string",',
            '      "confidence": 0-100,',
            '      "severity": "high|medium|low",',
            '      "reason": "string",',
            '      "recommended_checks": ["string"],',
            '      "recommended_actions": ["string"]',
            '    }',
            '  ],',
            '  "warnings": ["string"],',
            '  "customer_advice": ["string"],',
            '  "disclaimer": "string|null"',
            '}',
            'Aturan:',
            '- Minimal 1 dugaan penyebab jika ada data gejala yang cukup.',
            "- Maksimal {$maxPossibleCauses} dugaan penyebab.",
            '- Jangan tambahkan field di luar struktur.',
            '- Jangan menyatakan hasil sebagai kepastian final.',
            '- Gunakan bahasa yang mudah dipahami frontdesk dan customer.',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildMonthlyBusinessReportFeaturePrompt(array $config): string
    {
        $maxHighlights = (int) ($config['max_highlights'] ?? 5);
        $disclaimerText = (string) ($config['disclaimer_text'] ?? 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.');
        $reportFocus = (string) ($config['report_focus'] ?? 'Sorot tren omzet, efisiensi order servis, dan tindakan prioritas untuk bulan berikutnya.');

        $lines = [
            'Buat ringkasan laporan bulanan bengkel berdasarkan data finansial dan operasional yang diberikan.',
            'Tujuan hasil:',
            "- Maksimal {$maxHighlights} poin highlights.",
            "- Fokus laporan: {$reportFocus}",
            ($config['include_financial_summary'] ?? true)
                ? '- Tampilkan ringkasan finansial inti: omzet total, omzet jasa, omzet sparepart, dan estimasi laba kotor.'
                : '- Isi blok finansial dengan angka 0 agar struktur tetap konsisten.',
            ($config['include_operational_summary'] ?? true)
                ? '- Tampilkan KPI operasional: total order, order selesai, order pending, dan customer baru.'
                : '- Isi KPI operasional dengan angka 0 agar struktur tetap konsisten.',
            ($config['include_risks'] ?? true)
                ? '- Isi risks dengan poin risiko utama yang perlu diwaspadai bulan depan.'
                : '- Isi risks dengan array kosong [].',
            ($config['include_recommendations'] ?? true)
                ? '- Isi recommendations dengan langkah tindak lanjut yang konkret dan bisa dieksekusi.'
                : '- Isi recommendations dengan array kosong [].',
            ($config['include_next_month_focus'] ?? true)
                ? '- Isi next_month_focus dengan prioritas kerja bulan berikutnya.'
                : '- Isi next_month_focus dengan array kosong [].',
            ($config['include_disclaimer'] ?? true)
                ? "- Isi disclaimer dengan kalimat ini: {$disclaimerText}"
                : '- Isi disclaimer dengan null.',
            'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
            '{',
            '  "period": "YYYY-MM",',
            '  "executive_summary": "string",',
            '  "highlights": ["string"],',
            '  "kpis": {',
            '    "total_revenue": 0,',
            '    "service_revenue": 0,',
            '    "sparepart_revenue": 0,',
            '    "gross_profit_estimate": 0,',
            '    "total_service_orders": 0,',
            '    "completed_service_orders": 0,',
            '    "new_customers": 0',
            '  },',
            '  "risks": ["string"],',
            '  "recommendations": ["string"],',
            '  "next_month_focus": ["string"],',
            '  "disclaimer": "string|null"',
            '}',
            'Aturan:',
            "- Maksimal {$maxHighlights} poin pada highlights.",
            '- Fokus pada insight yang action-oriented, bukan sekadar ulang angka mentah.',
            '- Jangan tambahkan field di luar struktur.',
            '- Jika data input kurang lengkap, tetap beri ringkasan awal dan sebutkan keterbatasan pada executive_summary atau risks.',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $runtimeResult
     * @param  array<string, mixed>|null  $parsedOutput
     */
    private function persistPromptTestRuntimePayload(
        string $featureKey,
        ?string $actorId,
        array $runtimeResult,
        string $testInput,
        string $outputText,
        ?array $parsedOutput,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
    ): void {
        if (! Schema::hasTable('ai_runtime_logs')) {
            return;
        }

        $agentId = (int) ($runtimeResult['agent_id'] ?? 0);
        $metaPayload = [
            'context' => 'platform_prompt_test_output',
            'feature_key' => $featureKey,
            'tested_at' => now()->toIso8601String(),
            'test_input' => $this->trimText($testInput, 12000),
            'output_text' => $this->trimText($outputText, 24000),
            'output_json' => is_array($parsedOutput) ? $parsedOutput : null,
        ];

        try {
            $runtimeLog = AiRuntimeLog::query()
                ->where('source', 'platform_prompt_test')
                ->where('feature_key', $featureKey)
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->when($agentId > 0, static function ($query) use ($agentId): void {
                    $query->where('ai_agent_id', $agentId);
                })
                ->when($actorId !== null, static function ($query) use ($actorId): void {
                    $query->where('requester_user_id', $actorId);
                }, static function ($query): void {
                    $query->whereNull('requester_user_id');
                })
                ->orderByDesc('created_at')
                ->first();

            if ($runtimeLog) {
                $existingMeta = is_array($runtimeLog->meta_payload) ? $runtimeLog->meta_payload : [];
                $runtimeLog->forceFill([
                    'meta_payload' => [
                        ...$existingMeta,
                        ...$metaPayload,
                    ],
                ])->save();

                return;
            }

            AiRuntimeLog::query()->create([
                'tenant_id' => null,
                'source' => 'platform_prompt_test',
                'feature_key' => $featureKey,
                'status' => 'success',
                'requester_user_id' => $actorId,
                'service_order_id' => null,
                'ai_agent_id' => $agentId > 0 ? $agentId : null,
                'provider' => trim((string) ($runtimeResult['provider'] ?? '')) ?: null,
                'agent_model' => trim((string) ($runtimeResult['agent_model'] ?? '')) ?: null,
                'prompt_tokens' => max($promptTokens, 0),
                'completion_tokens' => max($completionTokens, 0),
                'total_tokens' => max($totalTokens, 0),
                'latency_ms' => null,
                'error_message' => null,
                'meta_payload' => $metaPayload,
            ]);
        } catch (\Throwable $throwable) {
            Log::warning('platform.ai-prompt-setting.runtime-log-enrich-failed', [
                'tenant_id' => null,
                'feature_key' => $featureKey,
                'actor_user_id' => $actorId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function trimText(string $value, int $limit): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($normalized, 0, $limit)
            : substr($normalized, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function normalizeBoolean(array $config, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function normalizeInteger(
        array $config,
        string $key,
        int $default,
        int $min,
        int $max,
    ): int {
        if (! array_key_exists($key, $config)) {
            return $default;
        }

        $value = (int) $config[$key];

        return max($min, min($max, $value));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function normalizeText(array $config, string $key, string $default, int $maxLength): string
    {
        if (! array_key_exists($key, $config) || ! is_scalar($config[$key])) {
            return $default;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $config[$key]) ?? '');
        if ($value === '') {
            return $default;
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    /**
     * @param  array<string, mixed>  $defaultConfig
     * @return array<string, mixed>
     */
    private function resolveStoredFeaturePromptConfig(
        string $featureKey,
        PlatformAiPromptSetting $setting,
        array $defaultConfig,
    ): array {
        if (! $this->supportsFeaturePromptConfig()) {
            return $defaultConfig;
        }

        $storedConfig = $setting->getAttribute('feature_prompt_config');

        return $this->normalizeFeaturePromptConfig(
            $featureKey,
            is_array($storedConfig) ? $storedConfig : $defaultConfig,
        );
    }

    private function supportsFeaturePromptConfig(): bool
    {
        if ($this->featurePromptConfigColumnExists !== null) {
            return $this->featurePromptConfigColumnExists;
        }

        $this->featurePromptConfigColumnExists = Schema::hasTable('platform_ai_prompt_settings')
            && Schema::hasColumn('platform_ai_prompt_settings', 'feature_prompt_config');

        return $this->featurePromptConfigColumnExists;
    }

    private function hasStoredFeaturePromptConfig(PlatformAiPromptSetting $setting): bool
    {
        if (! $this->supportsFeaturePromptConfig()) {
            return false;
        }

        return is_array($setting->getAttribute('feature_prompt_config'));
    }
}
