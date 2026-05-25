<?php

namespace App\Services\Platform;

use App\Models\AiRuntimeLog;
use App\Models\PlatformAiSetting;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\AiManager;

use function Laravel\Ai\agent;

class PlatformAiAgentService
{
    private const DEFAULT_PROVIDER = 'openai';

    private const DEFAULT_MODEL = 'gpt-5';

    /**
     * @var array<int, string>
     */
    private const RUNTIME_LOG_SOURCES = [
        'owner_service_runtime',
        'platform_prompt_test',
        'platform_connection_test',
        'runtime_general',
    ];

    /**
     * @var array<string, string>
     */
    private const PROVIDER_LABELS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'gemini' => 'Google Gemini',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'deepseek' => 'DeepSeek',
        'kimi' => 'Kimi (Moonshot)',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const AGENT_MODELS = [
        'openai' => [
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
        ],
        'anthropic' => [
            'claude-opus-4.6',
            'claude-sonnet-4.6',
            'claude-haiku-4.5',
        ],
        'gemini' => [
            'gemini-3.1-pro',
            'gemini-3.1-flash',
            'gemini-2.5-flash-lite',
        ],
        'groq' => [
            'llama-3.3-70b-versatile',
            'llama-3.1-8b-instant',
            'mixtral-8x7b-32768',
        ],
        'mistral' => [
            'mistral-large-latest',
            'mistral-medium-latest',
            'mistral-small-latest',
        ],
        'deepseek' => [
            'deepseek-chat',
            'deepseek-reasoner',
        ],
        'kimi' => [
            'kimi-k2.5',
            'kimi-k2-0905-preview',
            'kimi-k2-0711-preview',
            'kimi-k2-turbo-preview',
            'kimi-k2-thinking-turbo',
            'kimi-k2-thinking',
            'moonshot-v1-8k',
            'moonshot-v1-32k',
            'moonshot-v1-128k',
            'moonshot-v1-auto',
            'moonshot-v1-8k-vision-preview',
            'moonshot-v1-32k-vision-preview',
            'moonshot-v1-128k-vision-preview',
        ],
    ];

    /**
     * Model alias fallback saat provider belum menerima model ID terbaru.
     *
     * @var array<string, array<string, string>>
     */
    private const MODEL_FALLBACKS = [
        'gemini' => [
            'gemini-3.1-pro' => 'gemini-3.1-pro-preview',
            'gemini-3.1-flash' => 'gemini-3-flash-preview',
            'gemini-2.5-flash-lite' => 'gemini-2.5-flash-lite-preview',
        ],
    ];

    /**
     * Provider alias runtime untuk provider yang memakai endpoint kompatibel OpenAI.
     *
     * @var array<string, string>
     */
    private const PROVIDER_RUNTIME_MAP = [];

    /**
     * Override konfigurasi runtime provider.
     *
     * @var array<string, array<string, string>>
     */
    private const PROVIDER_RUNTIME_CONFIG_OVERRIDES = [];

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(Request $request): array
    {
        $search = trim((string) $request->query('ai_agent_search', ''));
        $sortBy = $this->resolveSortBy((string) $request->query('ai_agent_sort_by', 'priority_order'));
        $sortDir = $this->resolveSortDirection((string) $request->query('ai_agent_sort_dir', 'asc'));
        $perPage = $this->resolvePerPage((int) $request->query('ai_agent_per_page', 10));
        $cursor = trim((string) $request->query('ai_agent_cursor', ''));

        $payload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $perPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $summary = [
            'total_agents' => 0,
            'active_agents' => 0,
            'default_agent_id' => null,
            'next_priority_order' => 1,
        ];

        $failoverOrder = [];

        if (Schema::hasTable('platform_ai_settings')) {
            $baseQuery = PlatformAiSetting::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($nestedQuery) use ($search): void {
                        $nestedQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('provider', 'like', "%{$search}%")
                            ->orWhere('agent_model', 'like', "%{$search}%");
                    });
                });

            $total = (int) (clone $baseQuery)->count();

            $sortableColumn = [
                'priority_order' => 'platform_ai_settings.priority_order',
                'name' => 'platform_ai_settings.name',
                'provider' => 'platform_ai_settings.provider',
                'is_active' => 'platform_ai_settings.is_active',
                'is_default' => 'platform_ai_settings.is_default',
                'last_tested_at' => 'platform_ai_settings.last_tested_at',
                'created_at' => 'platform_ai_settings.created_at',
            ][$sortBy] ?? 'platform_ai_settings.priority_order';

            $query = (clone $baseQuery)
                ->select('platform_ai_settings.*')
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('platform_ai_settings.id', $sortDir);

            $paginator = $this->cursorPaginateWithFallback(
                $query,
                $perPage,
                ['*'],
                $cursor,
                'ai_agent_cursor',
            );

            $effectiveDefaultId = $this->resolveEffectiveDefaultAgentId();

            $rows = collect($paginator->items())
                ->map(fn (PlatformAiSetting $agent): array => $this->buildAgentPayloadFromModel($agent, $effectiveDefaultId))
                ->values();

            $payload = [
                'mode' => 'cursor',
                'data' => $rows->all(),
                'per_page' => $paginator->perPage(),
                'total' => $total,
                'from' => $rows->isEmpty() ? 0 : 1,
                'to' => $rows->count(),
                'current_cursor' => $paginator->cursor()?->encode(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more_pages' => $paginator->hasMorePages(),
            ];

            $activeAgents = (int) PlatformAiSetting::query()->where('is_active', true)->count();
            $summary = [
                'total_agents' => $total,
                'active_agents' => $activeAgents,
                'default_agent_id' => $effectiveDefaultId,
                'next_priority_order' => $this->resolveNextPriorityOrder(),
            ];

            $failoverOrder = $this->resolveFailoverOrder($effectiveDefaultId);
        }

        return [
            'agents' => $payload,
            'agentFilters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
                'cursor' => $payload['current_cursor'],
            ],
            'agentSummary' => $summary,
            'failoverOrder' => $failoverOrder,
            'providerOptions' => $this->providerOptions(),
            'agentOptionsByProvider' => $this->agentOptionsByProvider(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createAgent(array $validated): void
    {
        $this->assertTableReady();

        $rawProvider = (string) ($validated['provider'] ?? self::DEFAULT_PROVIDER);
        $rawAgentModel = (string) ($validated['agent_model'] ?? self::DEFAULT_MODEL);
        $provider = $this->resolveCanonicalProvider($rawProvider, $rawAgentModel);
        $agentModel = $this->sanitizeModel($provider, $rawAgentModel);
        $agentName = $this->resolveUniqueAutomaticAgentName($provider, $agentModel);

        $apiKey = $this->normalizeApiKey((string) ($validated['api_key'] ?? ''));
        $this->ensureApiKeyIsUniqueAcrossAgents($apiKey);
        $isDefault = (bool) ($validated['is_default'] ?? false);
        $isActive = (bool) ($validated['is_active'] ?? true);
        if ($isDefault) {
            $isActive = true;
        }

        $priorityOrder = max(1, (int) ($validated['priority_order'] ?? $this->resolveNextPriorityOrder()));
        $this->ensurePriorityOrderIsUnique($priorityOrder);
        $isFailoverEnabled = (bool) ($validated['is_failover_enabled'] ?? true);
        $monthlyTokenLimit = array_key_exists('monthly_token_limit', $validated) && $validated['monthly_token_limit'] !== null
            ? max(0, (int) $validated['monthly_token_limit'])
            : null;

        DB::transaction(function () use (
            $agentName,
            $provider,
            $agentModel,
            $apiKey,
            $priorityOrder,
            $isActive,
            $isDefault,
            $isFailoverEnabled,
            $monthlyTokenLimit,
        ): void {
            if ($isDefault) {
                PlatformAiSetting::query()->update(['is_default' => false]);
            }

            $agent = PlatformAiSetting::query()->create([
                'name' => $agentName,
                'provider' => $provider,
                'agent_model' => $agentModel,
                'api_key' => $apiKey !== '' ? $apiKey : null,
                'priority_order' => $priorityOrder,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'is_failover_enabled' => $isFailoverEnabled,
                'monthly_token_limit' => $monthlyTokenLimit,
                'used_token_count' => 0,
                'test_success_count' => 0,
                'test_failed_count' => 0,
                'last_test_status' => null,
                'last_test_message' => null,
                'last_test_prompt_tokens' => 0,
                'last_test_completion_tokens' => 0,
                'last_test_total_tokens' => 0,
                'last_known_quota_remaining' => null,
                'last_tested_at' => null,
            ]);

            if (! $isDefault) {
                $this->ensureDefaultAgentExists((int) $agent->id);
            }
        });

        Log::info('Platform AI agent created', [
            'tenant_id' => null,
            'provider' => $provider,
            'agent_model' => $agentModel,
            'is_default' => $isDefault,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateAgent(int $agentId, array $validated): void
    {
        $this->assertTableReady();

        $agent = $this->findAgentOrFail($agentId, 'update_agent', 'Agent tidak ditemukan.');

        $rawProvider = (string) ($validated['provider'] ?? $agent->provider);
        $rawAgentModel = (string) ($validated['agent_model'] ?? $agent->agent_model);
        $provider = $this->resolveCanonicalProvider($rawProvider, $rawAgentModel);
        $agentModel = $this->sanitizeModel($provider, $rawAgentModel);
        $agentName = $this->resolveUniqueAutomaticAgentName($provider, $agentModel, (int) $agent->id);

        $incomingApiKey = $this->normalizeApiKey((string) ($validated['api_key'] ?? ''));
        $removeApiKey = (bool) ($validated['remove_api_key'] ?? false);

        $nextApiKey = $agent->api_key;
        if ($removeApiKey) {
            $nextApiKey = null;
        } elseif ($incomingApiKey !== '') {
            $nextApiKey = $incomingApiKey;
        }

        $this->ensureApiKeyIsUniqueAcrossAgents($nextApiKey, (int) $agent->id);

        $isDefault = array_key_exists('is_default', $validated)
            ? (bool) $validated['is_default']
            : (bool) $agent->is_default;

        $isActive = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : (bool) $agent->is_active;

        if ($isDefault) {
            $isActive = true;
        }

        $priorityOrder = array_key_exists('priority_order', $validated)
            ? max(1, (int) $validated['priority_order'])
            : max(1, (int) $agent->priority_order);
        $this->ensurePriorityOrderIsUnique($priorityOrder, (int) $agent->id);

        $isFailoverEnabled = array_key_exists('is_failover_enabled', $validated)
            ? (bool) $validated['is_failover_enabled']
            : (bool) $agent->is_failover_enabled;

        $monthlyTokenLimit = array_key_exists('monthly_token_limit', $validated)
            ? ($validated['monthly_token_limit'] !== null ? max(0, (int) $validated['monthly_token_limit']) : null)
            : $agent->monthly_token_limit;

        DB::transaction(function () use (
            $agent,
            $agentName,
            $provider,
            $agentModel,
            $nextApiKey,
            $priorityOrder,
            $isActive,
            $isDefault,
            $isFailoverEnabled,
            $monthlyTokenLimit,
        ): void {
            if ($isDefault) {
                PlatformAiSetting::query()->where('id', '!=', (int) $agent->id)->update(['is_default' => false]);
            }

            $agent->forceFill([
                'name' => $agentName,
                'provider' => $provider,
                'agent_model' => $agentModel,
                'api_key' => $nextApiKey,
                'priority_order' => $priorityOrder,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'is_failover_enabled' => $isFailoverEnabled,
                'monthly_token_limit' => $monthlyTokenLimit,
            ])->save();

            $this->ensureDefaultAgentExists();
        });

        Log::info('Platform AI agent updated', [
            'tenant_id' => null,
            'agent_id' => $agentId,
            'provider' => $provider,
            'agent_model' => $agentModel,
            'is_default' => $isDefault,
            'is_active' => $isActive,
        ]);
    }

    public function updateAgentStatus(int $agentId, bool $isActive): void
    {
        $this->assertTableReady();

        $agent = $this->findAgentOrFail($agentId, 'status_agent', 'Agent tidak ditemukan.');

        DB::transaction(function () use ($agent, $isActive): void {
            $wasDefault = (bool) $agent->is_default;

            $agent->forceFill([
                'is_active' => $isActive,
                'is_default' => $wasDefault && $isActive,
            ])->save();

            if (! $isActive && $wasDefault) {
                $this->ensureDefaultAgentExists();
                return;
            }

            if ($isActive) {
                $this->ensureDefaultAgentExists((int) $agent->id);
            }
        });
    }

    public function setDefaultAgent(int $agentId): void
    {
        $this->assertTableReady();

        $agent = $this->findAgentOrFail($agentId, 'default_agent', 'Agent tidak ditemukan.');

        DB::transaction(function () use ($agent): void {
            PlatformAiSetting::query()->update(['is_default' => false]);

            $agent->forceFill([
                'is_default' => true,
                'is_active' => true,
            ])->save();
        });
    }

    public function deleteAgent(int $agentId): void
    {
        $this->assertTableReady();

        $agent = $this->findAgentOrFail($agentId, 'delete_agent', 'Agent tidak ditemukan.');
        $wasDefault = (bool) $agent->is_default;

        DB::transaction(function () use ($agent, $wasDefault): void {
            $agent->delete();

            if ($wasDefault) {
                $this->ensureDefaultAgentExists();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{ok: bool, message: string}
     */
    public function testConnection(int $agentId, array $validated = []): array
    {
        $this->assertTableReady();

        $agent = $this->findAgentOrFail($agentId, 'test_agent', 'Agent tidak ditemukan.');
        $actorUserId = $this->normalizeRuntimeNullableId($validated['actor_user_id'] ?? null);

        $incomingApiKey = $this->normalizeApiKey((string) ($validated['api_key'] ?? ''));
        $apiKey = $incomingApiKey !== ''
            ? $incomingApiKey
            : $this->normalizeApiKey((string) ($agent->api_key ?? ''));

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'api_key' => 'Masukkan API key terlebih dahulu sebelum menjalankan test.',
            ]);
        }

        try {
            $response = $this->runAgentHealthCheck(
                (string) $agent->provider,
                (string) $agent->agent_model,
                $apiKey,
            );

            $promptTokens = max(0, (int) ($response->usage->promptTokens ?? 0));
            $completionTokens = max(0, (int) ($response->usage->completionTokens ?? 0));
            $totalTokens = $promptTokens + $completionTokens;

            $quotaRemaining = $this->extractQuotaRemainingFromResponse($response);

            $agentPayload = [
                'test_success_count' => (int) $agent->test_success_count + 1,
                'last_test_status' => 'success',
                'last_test_message' => 'Test API key berhasil dijalankan.',
                'last_test_prompt_tokens' => $promptTokens,
                'last_test_completion_tokens' => $completionTokens,
                'last_test_total_tokens' => $totalTokens,
                'used_token_count' => max(0, (int) $agent->used_token_count) + $totalTokens,
                'last_tested_at' => now(),
            ];

            if ($quotaRemaining !== null) {
                $agentPayload['last_known_quota_remaining'] = $quotaRemaining;
            }

            $agent->forceFill($agentPayload)->save();

            $this->logRuntimeEntry([
                'tenant_id' => null,
                'source' => 'platform_connection_test',
                'feature_key' => 'connection_test_v1',
                'status' => 'success',
                'requester_user_id' => $actorUserId,
                'service_order_id' => null,
                'ai_agent_id' => (int) $agent->id,
                'provider' => (string) $agent->provider,
                'agent_model' => (string) $agent->agent_model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'latency_ms' => null,
                'error_message' => null,
                'meta_payload' => [
                    'context' => 'platform_connection_test',
                    'quota_remaining' => $quotaRemaining,
                ],
            ]);

            $message = 'Test API key berhasil.';
            if ($totalTokens > 0) {
                $message .= " Penggunaan token: {$totalTokens}.";
            }

            if ($quotaRemaining !== null) {
                $message .= ' Kuota provider berhasil dibaca.';
            } else {
                $message .= ' Kuota provider belum tersedia dari endpoint ini, sistem memakai estimasi internal.';
            }

            return [
                'ok' => true,
                'message' => $message,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $friendlyErrorMessage = $this->sanitizeExceptionMessage(
                (string) $agent->provider,
                $exception->getMessage(),
                (string) $agent->agent_model,
            );

            $agent->forceFill([
                'test_failed_count' => (int) $agent->test_failed_count + 1,
                'last_test_status' => 'failed',
                'last_test_message' => $friendlyErrorMessage,
                'last_test_prompt_tokens' => 0,
                'last_test_completion_tokens' => 0,
                'last_test_total_tokens' => 0,
                'last_tested_at' => now(),
            ])->save();

            $this->logRuntimeEntry([
                'tenant_id' => null,
                'source' => 'platform_connection_test',
                'feature_key' => 'connection_test_v1',
                'status' => 'failed',
                'requester_user_id' => $actorUserId,
                'service_order_id' => null,
                'ai_agent_id' => (int) $agent->id,
                'provider' => (string) $agent->provider,
                'agent_model' => (string) $agent->agent_model,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'latency_ms' => null,
                'error_message' => $friendlyErrorMessage,
                'meta_payload' => [
                    'context' => 'platform_connection_test',
                    'raw_error' => Str::limit((string) $exception->getMessage(), 1000, ''),
                ],
            ]);

            Log::warning('Platform AI test failed', [
                'tenant_id' => null,
                'agent_id' => (int) $agent->id,
                'provider' => (string) $agent->provider,
                'agent_model' => (string) $agent->agent_model,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Test API key gagal: '.$friendlyErrorMessage,
            ];
        }
    }

    /**
     * @return array{response: object, agent_id: int, provider: string, agent_model: string}
     */
    public function promptWithFailover(
        string $prompt,
        string $instructions = 'Jawab singkat dan jelas.',
        int $timeout = 30,
        array $context = [],
    ): array
    {
        $this->assertTableReady();

        $chain = $this->resolveFailoverChain();
        if ($chain->isEmpty()) {
            throw ValidationException::withMessages([
                'provider' => 'Belum ada agent aktif untuk menjalankan permintaan AI.',
            ]);
        }

        $lastErrorMessage = 'Semua agent gagal merespons permintaan.';
        $runtimeContext = $this->normalizeRuntimeLogContext($context);
        $attemptIndex = 0;

        /** @var PlatformAiSetting $agent */
        foreach ($chain as $agent) {
            $attemptIndex++;
            $apiKey = $this->normalizeApiKey((string) ($agent->api_key ?? ''));
            if ($apiKey === '') {
                $lastErrorMessage = sprintf(
                    'Agent %s tidak memiliki API key.',
                    (string) $this->resolveAutomaticAgentName($this->resolveCanonicalProvider((string) $agent->provider, (string) $agent->agent_model), (string) $agent->agent_model),
                );

                $this->logRuntimeEntry([
                    ...$runtimeContext,
                    'status' => 'failed',
                    'ai_agent_id' => (int) $agent->id,
                    'provider' => (string) $agent->provider,
                    'agent_model' => (string) $agent->agent_model,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'latency_ms' => null,
                    'error_message' => $lastErrorMessage,
                    'meta_payload' => [
                        'context' => 'failover_missing_api_key',
                        'attempt_index' => $attemptIndex,
                    ],
                ]);
                continue;
            }

            try {
                $response = $this->runAgentPrompt(
                    (string) $agent->provider,
                    (string) $agent->agent_model,
                    $apiKey,
                    $prompt,
                    $instructions,
                    $timeout,
                );

                $promptTokens = max(0, (int) ($response->usage->promptTokens ?? 0));
                $completionTokens = max(0, (int) ($response->usage->completionTokens ?? 0));
                $totalTokens = $promptTokens + $completionTokens;

                $quotaRemaining = $this->extractQuotaRemainingFromResponse($response);

                $agentPayload = [
                    'test_success_count' => (int) $agent->test_success_count + 1,
                    'last_test_status' => 'success',
                    'last_test_message' => 'Agent berhasil dipakai melalui failover runtime.',
                    'last_test_prompt_tokens' => $promptTokens,
                    'last_test_completion_tokens' => $completionTokens,
                    'last_test_total_tokens' => $totalTokens,
                    'used_token_count' => max(0, (int) $agent->used_token_count) + $totalTokens,
                    'last_tested_at' => now(),
                ];

                if ($quotaRemaining !== null) {
                    $agentPayload['last_known_quota_remaining'] = $quotaRemaining;
                }

                $agent->forceFill($agentPayload)->save();

                $this->logRuntimeEntry([
                    ...$runtimeContext,
                    'status' => 'success',
                    'ai_agent_id' => (int) $agent->id,
                    'provider' => (string) $agent->provider,
                    'agent_model' => (string) $agent->agent_model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                    'latency_ms' => null,
                    'error_message' => null,
                    'meta_payload' => [
                        'context' => 'failover_runtime',
                        'attempt_index' => $attemptIndex,
                        'quota_remaining' => $quotaRemaining,
                    ],
                ]);

                return [
                    'response' => $response,
                    'agent_id' => (int) $agent->id,
                    'provider' => (string) $agent->provider,
                    'agent_model' => (string) $agent->agent_model,
                ];
            } catch (\Throwable $exception) {
                $friendlyErrorMessage = $this->sanitizeExceptionMessage((string) $agent->provider, $exception->getMessage(), (string) $agent->agent_model);

                $agent->forceFill([
                    'test_failed_count' => (int) $agent->test_failed_count + 1,
                    'last_test_status' => 'failed',
                    'last_test_message' => $friendlyErrorMessage,
                    'last_test_prompt_tokens' => 0,
                    'last_test_completion_tokens' => 0,
                    'last_test_total_tokens' => 0,
                    'last_tested_at' => now(),
                ])->save();

                $lastErrorMessage = $friendlyErrorMessage;

                $this->logRuntimeEntry([
                    ...$runtimeContext,
                    'status' => 'failed',
                    'ai_agent_id' => (int) $agent->id,
                    'provider' => (string) $agent->provider,
                    'agent_model' => (string) $agent->agent_model,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'latency_ms' => null,
                    'error_message' => $friendlyErrorMessage,
                    'meta_payload' => [
                        'context' => 'failover_runtime',
                        'attempt_index' => $attemptIndex,
                        'raw_error' => Str::limit((string) $exception->getMessage(), 1000, ''),
                    ],
                ]);

                Log::warning('Platform AI failover step failed', [
                    'tenant_id' => null,
                    'agent_id' => (int) $agent->id,
                    'provider' => (string) $agent->provider,
                    'agent_model' => (string) $agent->agent_model,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw ValidationException::withMessages([
            'provider' => $lastErrorMessage,
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function providerOptions(): array
    {
        return collect(self::PROVIDER_LABELS)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function agentOptionsByProvider(): array
    {
        return collect(self::AGENT_MODELS)
            ->mapWithKeys(fn (array $models, string $provider): array => [
                $provider => collect($models)
                    ->map(fn (string $model): array => [
                        'value' => $model,
                        'label' => strtoupper($model),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAgentPayloadFromModel(PlatformAiSetting $agent, ?int $effectiveDefaultId): array
    {
        $providerKey = $this->resolveCanonicalProvider((string) $agent->provider, (string) $agent->agent_model);
        $providerLabel = self::PROVIDER_LABELS[$providerKey] ?? strtoupper($providerKey);
        $isDefault = (bool) $agent->is_default || ((int) $agent->id === (int) ($effectiveDefaultId ?? 0));

        $monthlyTokenLimit = $agent->monthly_token_limit !== null
            ? max(0, (int) $agent->monthly_token_limit)
            : null;

        $usedTokenCount = max(0, (int) $agent->used_token_count);

        $quotaRemaining = null;
        $quotaLabel = 'Tidak diatur';

        if ($agent->last_known_quota_remaining !== null) {
            $quotaRemaining = max(0, (int) $agent->last_known_quota_remaining);
            $quotaLabel = number_format($quotaRemaining, 0, ',', '.').' (provider)';
        } elseif ($monthlyTokenLimit !== null) {
            $quotaRemaining = max(0, $monthlyTokenLimit - $usedTokenCount);
            $quotaLabel = sprintf(
                '%s / %s (estimasi)',
                number_format($quotaRemaining, 0, ',', '.'),
                number_format($monthlyTokenLimit, 0, ',', '.'),
            );
        }

        return [
            'id' => (int) $agent->id,
            'name' => $this->resolveAutomaticAgentName($this->resolveCanonicalProvider((string) $agent->provider, (string) $agent->agent_model), (string) $agent->agent_model),
            'provider' => $providerKey,
            'provider_label' => $providerLabel,
            'agent_model' => (string) $agent->agent_model,
            'priority_order' => max(1, (int) $agent->priority_order),
            'is_active' => (bool) $agent->is_active,
            'is_default' => $isDefault,
            'is_failover_enabled' => (bool) $agent->is_failover_enabled,
            'has_api_key' => filled($agent->api_key),
            'api_key_value' => (string) ($agent->api_key ?? ''),
            'masked_api_key' => $this->maskApiKey($agent->api_key),
            'monthly_token_limit' => $monthlyTokenLimit,
            'used_token_count' => $usedTokenCount,
            'quota_remaining' => $quotaRemaining,
            'quota_label' => $quotaLabel,
            'test_success_count' => (int) $agent->test_success_count,
            'test_failed_count' => (int) $agent->test_failed_count,
            'last_test_status' => $agent->last_test_status !== null ? (string) $agent->last_test_status : null,
            'last_test_message' => $agent->last_test_message !== null ? (string) $agent->last_test_message : null,
            'last_test_prompt_tokens' => (int) $agent->last_test_prompt_tokens,
            'last_test_completion_tokens' => (int) $agent->last_test_completion_tokens,
            'last_test_total_tokens' => (int) $agent->last_test_total_tokens,
            'last_tested_at' => $agent->last_tested_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, provider_label: string, agent_model: string, priority_order: int}>
     */
    private function resolveFailoverOrder(?int $effectiveDefaultId): array
    {
        $activeAgents = PlatformAiSetting::query()
            ->where('is_active', true)
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();

        if ($activeAgents->isEmpty()) {
            return [];
        }

        $defaultAgent = $activeAgents->firstWhere('id', (int) ($effectiveDefaultId ?? 0));
        if (! $defaultAgent) {
            $defaultAgent = $activeAgents->firstWhere('is_default', true) ?? $activeAgents->first();
        }

        $fallbackAgents = $activeAgents
            ->reject(fn (PlatformAiSetting $agent): bool => (int) $agent->id === (int) $defaultAgent->id)
            ->filter(fn (PlatformAiSetting $agent): bool => (bool) $agent->is_failover_enabled)
            ->values();

        return collect([$defaultAgent])
            ->merge($fallbackAgents)
            ->map(function (PlatformAiSetting $agent): array {
                $providerKey = $this->resolveCanonicalProvider((string) $agent->provider, (string) $agent->agent_model);

                return [
                    'id' => (int) $agent->id,
                    'name' => $this->resolveAutomaticAgentName($this->resolveCanonicalProvider((string) $agent->provider, (string) $agent->agent_model), (string) $agent->agent_model),
                    'provider_label' => self::PROVIDER_LABELS[$providerKey] ?? strtoupper($providerKey),
                    'agent_model' => (string) $agent->agent_model,
                    'priority_order' => max(1, (int) $agent->priority_order),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PlatformAiSetting>
     */
    private function resolveFailoverChain(): Collection
    {
        $activeAgents = PlatformAiSetting::query()
            ->where('is_active', true)
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();

        if ($activeAgents->isEmpty()) {
            return collect();
        }

        $effectiveDefaultId = $this->resolveEffectiveDefaultAgentId();
        $defaultAgent = $activeAgents->firstWhere('id', (int) ($effectiveDefaultId ?? 0));
        if (! $defaultAgent) {
            $defaultAgent = $activeAgents->firstWhere('is_default', true) ?? $activeAgents->first();
        }

        $fallbackAgents = $activeAgents
            ->reject(fn (PlatformAiSetting $agent): bool => (int) $agent->id === (int) $defaultAgent->id)
            ->filter(fn (PlatformAiSetting $agent): bool => (bool) $agent->is_failover_enabled)
            ->values();

        return collect([$defaultAgent])
            ->merge($fallbackAgents)
            ->values();
    }

    private function resolveEffectiveDefaultAgentId(): ?int
    {
        $defaultId = PlatformAiSetting::query()
            ->where('is_default', true)
            ->value('id');

        if ($defaultId !== null) {
            return (int) $defaultId;
        }

        $activeId = PlatformAiSetting::query()
            ->where('is_active', true)
            ->orderBy('priority_order')
            ->orderBy('id')
            ->value('id');

        if ($activeId !== null) {
            return (int) $activeId;
        }

        $firstId = PlatformAiSetting::query()
            ->orderBy('priority_order')
            ->orderBy('id')
            ->value('id');

        return $firstId !== null ? (int) $firstId : null;
    }

    private function ensureDefaultAgentExists(?int $preferredAgentId = null): void
    {
        $hasDefault = PlatformAiSetting::query()->where('is_default', true)->exists();
        if ($hasDefault) {
            return;
        }

        $candidate = null;
        if ($preferredAgentId !== null && $preferredAgentId > 0) {
            $candidate = PlatformAiSetting::query()
                ->where('id', $preferredAgentId)
                ->where('is_active', true)
                ->first();
        }

        if (! $candidate) {
            $candidate = PlatformAiSetting::query()
                ->where('is_active', true)
                ->orderBy('priority_order')
                ->orderBy('id')
                ->first();
        }

        if (! $candidate) {
            $candidate = PlatformAiSetting::query()
                ->orderBy('priority_order')
                ->orderBy('id')
                ->first();
        }

        if (! $candidate) {
            return;
        }

        $candidate->forceFill([
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    private function resolveNextPriorityOrder(): int
    {
        $maxPriority = (int) PlatformAiSetting::query()->max('priority_order');

        if ($maxPriority < 1) {
            return 1;
        }

        return min(10000, $maxPriority + 1);
    }

    private function resolveAutomaticAgentName(string $provider, string $model): string
    {
        $providerLabel = self::PROVIDER_LABELS[$provider] ?? strtoupper($provider);
        $fallbackName = sprintf('%s - %s', $providerLabel, strtoupper($model));

        return mb_substr(trim($fallbackName), 0, 100);
    }

    private function resolveUniqueAutomaticAgentName(string $provider, string $model, ?int $ignoreAgentId = null): string
    {
        $baseName = $this->resolveAutomaticAgentName($provider, $model);

        $query = PlatformAiSetting::query();
        if ($ignoreAgentId !== null && $ignoreAgentId > 0) {
            $query->where('id', '!=', $ignoreAgentId);
        }

        $reservedNames = $query
            ->where(function ($nestedQuery) use ($baseName): void {
                $nestedQuery
                    ->where('name', $baseName)
                    ->orWhere('name', 'like', $baseName.' - %');
            })
            ->pluck('name')
            ->map(fn ($name): string => (string) $name)
            ->all();

        if (! in_array($baseName, $reservedNames, true)) {
            return $baseName;
        }

        $suffix = 2;
        while ($suffix <= 10000) {
            $candidateName = $this->buildAutoNameWithSuffix($baseName, $suffix);
            if (! in_array($candidateName, $reservedNames, true)) {
                return $candidateName;
            }

            $suffix++;
        }

        return $this->buildAutoNameWithSuffix($baseName, 10001);
    }

    private function buildAutoNameWithSuffix(string $baseName, int $suffix): string
    {
        $suffixText = ' - '.max(2, $suffix);
        $maxBaseLength = max(1, 100 - mb_strlen($suffixText));

        return mb_substr($baseName, 0, $maxBaseLength).$suffixText;
    }

    private function ensurePriorityOrderIsUnique(int $priorityOrder, ?int $ignoreAgentId = null): void
    {
        $normalizedPriority = max(1, $priorityOrder);

        $query = PlatformAiSetting::query()->where('priority_order', $normalizedPriority);
        if ($ignoreAgentId !== null && $ignoreAgentId > 0) {
            $query->where('id', '!=', $ignoreAgentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'priority_order' => 'Nilai prioritas sudah dipakai agent lain. Gunakan angka berbeda.',
            ]);
        }
    }

    private function ensureApiKeyIsUniqueAcrossAgents(?string $apiKey, ?int $ignoreAgentId = null): void
    {
        $normalizedKey = trim((string) ($apiKey ?? ''));
        if ($normalizedKey === '') {
            return;
        }

        $query = PlatformAiSetting::query()->whereNotNull('api_key');
        if ($ignoreAgentId !== null && $ignoreAgentId > 0) {
            $query->where('id', '!=', $ignoreAgentId);
        }

        $hasDuplicate = $query
            ->get()
            ->contains(function (PlatformAiSetting $setting) use ($normalizedKey): bool {
                $storedKey = trim((string) ($setting->api_key ?? ''));
                return $storedKey !== '' && hash_equals($storedKey, $normalizedKey);
            });

        if ($hasDuplicate) {
            throw ValidationException::withMessages([
                'api_key' => 'API key sudah digunakan oleh agent lain. Gunakan API key berbeda.',
            ]);
        }
    }

    private function extractQuotaRemainingFromResponse(object $response): ?int
    {
        $directPaths = [
            'usage.remainingTokens',
            'usage.remaining_tokens',
            'usage.quotaRemaining',
            'usage.quota_remaining',
            'usage.remaining',
            'quota.remainingTokens',
            'quota.remaining_tokens',
            'quota.remaining',
            'meta.quota.remainingTokens',
            'meta.quota.remaining_tokens',
            'meta.usage.remainingTokens',
            'meta.usage.remaining_tokens',
        ];

        foreach ($directPaths as $path) {
            $value = data_get($response, $path);
            if (is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        $limitCandidates = [
            data_get($response, 'usage.limitTokens'),
            data_get($response, 'usage.limit_tokens'),
            data_get($response, 'usage.totalLimitTokens'),
            data_get($response, 'usage.total_limit_tokens'),
        ];
        $usedCandidates = [
            data_get($response, 'usage.totalTokens'),
            data_get($response, 'usage.total_tokens'),
        ];

        $limit = collect($limitCandidates)->first(fn ($value): bool => is_numeric($value));
        $used = collect($usedCandidates)->first(fn ($value): bool => is_numeric($value));

        if (is_numeric($limit) && is_numeric($used)) {
            return max(0, (int) $limit - (int) $used);
        }

        return null;
    }

    private function sanitizeProvider(string $provider): string
    {
        $normalized = strtolower(trim($provider));

        return array_key_exists($normalized, self::PROVIDER_LABELS)
            ? $normalized
            : self::DEFAULT_PROVIDER;
    }

    private function sanitizeModel(string $provider, string $model): string
    {
        $normalizedProvider = $this->resolveCanonicalProvider($provider, $model);
        $availableModels = self::AGENT_MODELS[$normalizedProvider] ?? self::AGENT_MODELS[self::DEFAULT_PROVIDER];
        $normalizedModel = $this->normalizeModelAlias($normalizedProvider, $model);

        if ($normalizedModel === '') {
            return $availableModels[0];
        }

        if (! in_array($normalizedModel, $availableModels, true)) {
            return $availableModels[0];
        }

        return $normalizedModel;
    }

    private function resolveCanonicalProvider(string $provider, ?string $model = null): string
    {
        $normalizedProvider = $this->sanitizeProvider($provider);
        $modelKey = mb_strtolower(trim((string) ($model ?? '')));

        if ($normalizedProvider === 'deepseek' && $this->isKimiModel($modelKey)) {
            return 'kimi';
        }

        return $normalizedProvider;
    }

    private function normalizeModelAlias(string $provider, string $model): string
    {
        $normalizedModel = trim($model);
        if ($normalizedModel === '') {
            return '';
        }

        $providerKey = $this->sanitizeProvider($provider);
        $modelKey = mb_strtolower($normalizedModel);

        if ($providerKey === 'kimi') {
            if ($modelKey === 'kimi-k2_5') {
                return 'kimi-k2.5';
            }

            if (str_starts_with($modelKey, 'moonshot/')) {
                $withoutPrefix = trim(substr($normalizedModel, 9));
                if ($withoutPrefix !== '') {
                    return $withoutPrefix;
                }
            }
        }

        return $normalizedModel;
    }

    private function isKimiModel(string $model): bool
    {
        if ($model === '') {
            return false;
        }

        return str_starts_with($model, 'kimi-') || str_starts_with($model, 'moonshot/kimi-');
    }

    private function resolveRuntimeProvider(string $provider): string
    {
        $normalizedProvider = $this->sanitizeProvider($provider);

        return self::PROVIDER_RUNTIME_MAP[$normalizedProvider] ?? $normalizedProvider;
    }

    /**
     * @return array<string, string>
     */
    private function resolveRuntimeProviderConfigOverrides(string $provider): array
    {
        $normalizedProvider = $this->sanitizeProvider($provider);

        return self::PROVIDER_RUNTIME_CONFIG_OVERRIDES[$normalizedProvider] ?? [];
    }

    private function runAgentHealthCheck(string $provider, string $model, string $apiKey): object
    {
        return $this->runAgentPrompt(
            $provider,
            $model,
            $apiKey,
            'Tes koneksi API.',
            'Kamu adalah asisten untuk test koneksi API. Balas singkat dengan kata OK.',
            30,
        );
    }

    private function runAgentPrompt(
        string $provider,
        string $model,
        string $apiKey,
        string $prompt,
        string $instructions,
        int $timeout,
    ): object {
        $providerKey = $this->resolveCanonicalProvider($provider, $model);
        $normalizedModel = $this->sanitizeModel($providerKey, $model);
        $normalizedApiKey = $this->normalizeApiKey($apiKey);

        if ($providerKey === 'kimi') {
            return $this->runKimiPrompt($normalizedModel, $normalizedApiKey, $prompt, $instructions, $timeout);
        }

        $runtimeProvider = $this->resolveRuntimeProvider($providerKey);
        $runtimeConfigPath = "ai.providers.{$runtimeProvider}";
        $originalProviderConfig = config($runtimeConfigPath);

        $runtimeOverrides = $this->resolveRuntimeProviderConfigOverrides($providerKey);
        $runtimeConfig = is_array($originalProviderConfig) ? $originalProviderConfig : [];
        $runtimeConfig = array_merge($runtimeConfig, $runtimeOverrides, [
            'key' => $normalizedApiKey,
        ]);

        config([$runtimeConfigPath => $runtimeConfig]);
        app(AiManager::class)->forgetInstance($runtimeProvider);

        try {
            try {
                return $this->dispatchPrompt($runtimeProvider, $normalizedModel, $prompt, $instructions, $timeout);
            } catch (\Throwable $exception) {
                $fallbackModel = $this->resolveFallbackModelAlias($providerKey, $normalizedModel);

                if (
                    $fallbackModel !== null
                    && $fallbackModel !== $normalizedModel
                    && $this->isRetryableModelNotFoundError($exception->getMessage())
                ) {
                    return $this->dispatchPrompt($runtimeProvider, $fallbackModel, $prompt, $instructions, $timeout);
                }

                throw $exception;
            }
        } finally {
            config([$runtimeConfigPath => $originalProviderConfig]);
            app(AiManager::class)->forgetInstance($runtimeProvider);
        }
    }

    private function runKimiPrompt(
        string $model,
        string $apiKey,
        string $prompt,
        string $instructions,
        int $timeout,
    ): object {
        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'api_key' => 'API key Kimi wajib diisi.',
            ]);
        }

        $payload = [
            'model' => trim($model) !== '' ? trim($model) : 'kimi-k2.5',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $instructions,
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false,
        ];

        $response = Http::baseUrl('https://api.moonshot.ai/v1')
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout(max(5, $timeout))
            ->post('chat/completions', $payload)
            ->throw();

        $data = $response->json();

        return (object) [
            'usage' => (object) [
                'promptTokens' => (int) data_get($data, 'usage.prompt_tokens', 0),
                'completionTokens' => (int) data_get($data, 'usage.completion_tokens', 0),
            ],
            'raw' => $data,
            'meta' => (object) [
                'provider' => 'kimi',
                'model' => (string) data_get($data, 'model', $payload['model']),
            ],
        ];
    }

    private function dispatchPrompt(
        string $provider,
        string $model,
        string $prompt,
        string $instructions,
        int $timeout,
    ): object {
        $anonymousAgent = agent(instructions: $instructions);
        $promptMethod = new \ReflectionMethod($anonymousAgent, 'prompt');

        if ($promptMethod->getNumberOfParameters() >= 5) {
            return $anonymousAgent->prompt($prompt, [], $provider, $model, $timeout);
        }

        return $anonymousAgent->prompt($prompt, [], $provider, $model);
    }

    private function resolveFallbackModelAlias(string $provider, string $model): ?string
    {
        $providerKey = $this->sanitizeProvider($provider);
        $modelKey = trim($model);

        if ($modelKey === '') {
            return null;
        }

        return self::MODEL_FALLBACKS[$providerKey][$modelKey] ?? null;
    }

    private function isRetryableModelNotFoundError(string $message): bool
    {
        $lowerMessage = mb_strtolower(trim($message));

        return $this->containsAny($lowerMessage, [
            'model not found',
            'unknown model',
            'unsupported model',
            'not support model',
            'is not found for api version',
            'not supported for generatecontent',
            'call listmodels',
            'not_found',
        ]);
    }

    private function sanitizeExceptionMessage(string $provider, string $message, ?string $model = null): string
    {
        $normalized = trim($message);
        $providerKey = $this->resolveCanonicalProvider($provider, $model);
        $providerLabel = self::PROVIDER_LABELS[$providerKey] ?? 'provider AI';
        $modelKey = trim((string) ($model ?? ''));
        $modelHint = $modelKey !== '' ? " ({$modelKey})" : '';

        if ($normalized === '') {
            return "Koneksi ke {$providerLabel} gagal. Silakan coba beberapa saat lagi.";
        }

        $lowerMessage = mb_strtolower($normalized);

        if (
            $providerKey === 'kimi'
            && $this->containsAny($lowerMessage, ['ai provider [deepseek] has insufficient credits or quota', 'deepseek error [402]'])
        ) {
            return 'Koneksi Kimi gagal karena respons quota tidak valid untuk akun ini. Pastikan API key Moonshot aktif dan memiliki quota.';
        }

        if (
            $providerKey === 'kimi'
            && $this->containsAny($lowerMessage, [
                'exceeded_current_quota_error',
                'insufficient balance',
                'suspended due to insufficient balance',
                'is suspended',
                'account org-',
                'recharge your account',
            ])
        ) {
            return 'Akun Kimi Anda sedang ditangguhkan karena kuota atau saldo sudah habis. Silakan isi saldo/aktifkan paket Moonshot, lalu coba lagi.';
        }

        if (
            $this->containsAny($lowerMessage, ['api key not valid', 'invalid api key', 'incorrect api key', 'invalid_authentication_error', 'invalid authentication'])
            || ($this->containsAny($lowerMessage, ['invalid_argument', 'unauthorized', 'authentication']) && str_contains($lowerMessage, 'key'))
        ) {
            return "API key {$providerLabel} tidak valid. Periksa kembali key dari dashboard {$providerLabel}, lalu simpan ulang.";
        }

        if ($this->containsAny($lowerMessage, ['insufficient_quota', 'quota exceeded', 'billing', 'credit balance', 'payment required'])) {
            return "Kuota {$providerLabel} tidak mencukupi. Periksa billing atau upgrade paket provider lalu coba lagi.";
        }

        if ($this->containsAny($lowerMessage, ['rate limit', 'too many requests', 'status [429]', ' error 429'])) {
            return "Permintaan ke {$providerLabel} terlalu sering. Tunggu beberapa saat lalu coba lagi.";
        }

        if ($this->containsAny($lowerMessage, [
            'model not found',
            'unknown model',
            'unsupported model',
            'not support model',
            'is not found for api version',
            'not supported for generatecontent',
            'call listmodels',
            'not_found',
        ])) {
            return "Model agent{$modelHint} belum tersedia di {$providerLabel} untuk endpoint saat ini. Coba model lain atau varian preview.";
        }

        if ($this->containsAny($lowerMessage, ['forbidden', 'permission denied', 'not allowed', 'access denied'])) {
            return "Akses ke {$providerLabel} ditolak. Pastikan API key memiliki izin yang diperlukan.";
        }

        if ($this->containsAny($lowerMessage, ['timeout', 'timed out', 'could not resolve host', 'connection', 'network'])) {
            return "Koneksi ke {$providerLabel} sedang bermasalah. Coba lagi dalam beberapa saat.";
        }

        if (mb_strlen($normalized) > 180) {
            return "Terjadi kendala saat menghubungkan ke {$providerLabel}. Periksa API key dan model, lalu coba lagi.";
        }

        return "Terjadi kendala saat menghubungkan ke {$providerLabel}: {$normalized}";
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeApiKey(?string $apiKey): string
    {
        $value = trim((string) ($apiKey ?? ''));

        if ($value === '') {
            return '';
        }

        return preg_replace('/^bearer\\s+/i', '', $value) ?? $value;
    }

    private function maskApiKey(?string $apiKey): string
    {
        $value = trim((string) ($apiKey ?? ''));
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($value, 0, 4);
        $suffix = substr($value, -4);

        return $prefix.str_repeat('*', max(1, $length - 8)).$suffix;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['priority_order', 'name', 'provider', 'is_active', 'is_default', 'last_tested_at', 'created_at'], true)
            ? $sortBy
            : 'priority_order';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeRuntimeLogContext(array $context): array
    {
        return [
            'tenant_id' => $this->normalizeRuntimeNullableId($context['tenant_id'] ?? null),
            'source' => $this->resolveRuntimeLogSource($context['source'] ?? null),
            'feature_key' => $this->normalizeRuntimeNullableString($context['feature_key'] ?? null, 80),
            'requester_user_id' => $this->normalizeRuntimeNullableId($context['requester_user_id'] ?? null),
            'service_order_id' => $this->normalizeRuntimeNullableId($context['service_order_id'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logRuntimeEntry(array $payload): void
    {
        if (! Schema::hasTable('ai_runtime_logs')) {
            return;
        }

        try {
            AiRuntimeLog::query()->create([
                'tenant_id' => $this->normalizeRuntimeNullableId($payload['tenant_id'] ?? null),
                'source' => $this->resolveRuntimeLogSource($payload['source'] ?? null),
                'feature_key' => $this->normalizeRuntimeNullableString($payload['feature_key'] ?? null, 80),
                'status' => $this->normalizeRuntimeNullableString($payload['status'] ?? 'success', 20) ?? 'success',
                'requester_user_id' => $this->normalizeRuntimeNullableId($payload['requester_user_id'] ?? null),
                'service_order_id' => $this->normalizeRuntimeNullableId($payload['service_order_id'] ?? null),
                'ai_agent_id' => (int) ($payload['ai_agent_id'] ?? 0) > 0 ? (int) $payload['ai_agent_id'] : null,
                'provider' => $this->normalizeRuntimeNullableString($payload['provider'] ?? null, 30),
                'agent_model' => $this->normalizeRuntimeNullableString($payload['agent_model'] ?? null, 120),
                'prompt_tokens' => max((int) ($payload['prompt_tokens'] ?? 0), 0),
                'completion_tokens' => max((int) ($payload['completion_tokens'] ?? 0), 0),
                'total_tokens' => max((int) ($payload['total_tokens'] ?? 0), 0),
                'latency_ms' => array_key_exists('latency_ms', $payload) && $payload['latency_ms'] !== null
                    ? max((int) $payload['latency_ms'], 0)
                    : null,
                'error_message' => $this->normalizeRuntimeNullableString($payload['error_message'] ?? null, 1000),
                'meta_payload' => is_array($payload['meta_payload'] ?? null) ? $payload['meta_payload'] : null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('platform.ai-runtime-log.write-failed', [
                'tenant_id' => null,
                'source' => (string) ($payload['source'] ?? ''),
                'feature_key' => (string) ($payload['feature_key'] ?? ''),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveRuntimeLogSource(mixed $source): string
    {
        $normalized = strtolower(trim((string) $source));

        return in_array($normalized, self::RUNTIME_LOG_SOURCES, true)
            ? $normalized
            : 'runtime_general';
    }

    private function normalizeRuntimeNullableId(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeRuntimeNullableString(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return function_exists('mb_substr')
            ? mb_substr($normalized, 0, $maxLength)
            : substr($normalized, 0, $maxLength);
    }

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
        string $cursorName,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, $cursorName, $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, $cursorName.'_fallback', null)
                ->withQueryString();
        }
    }

    private function findAgentOrFail(int $agentId, string $errorKey, string $message): PlatformAiSetting
    {
        $agent = PlatformAiSetting::query()->find($agentId);
        if (! $agent) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }

        return $agent;
    }

    private function assertTableReady(): void
    {
        if (! Schema::hasTable('platform_ai_settings')) {
            throw ValidationException::withMessages([
                'provider' => 'Tabel pengaturan AI belum siap. Jalankan migrasi terlebih dahulu.',
            ]);
        }
    }
}









