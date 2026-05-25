<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRuntimeLog extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'id',
        'tenant_id',
        'source',
        'feature_key',
        'status',
        'requester_user_id',
        'service_order_id',
        'ai_agent_id',
        'provider',
        'agent_model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'error_message',
        'meta_payload',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'latency_ms' => 'integer',
        'meta_payload' => 'array',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(PlatformAiSetting::class, 'ai_agent_id');
    }
}
