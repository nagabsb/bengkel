<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ServiceOrderEstimateAiLog extends Model
{
    use HasFactory;
    use HasUlids;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'service_order_id',
        'ai_agent_id',
        'feature_key',
        'status',
        'generated_by_user_id',
        'input_payload',
        'prompt_snapshot',
        'output_payload',
        'error_message',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'prompt_snapshot' => 'array',
        'output_payload' => 'array',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'latency_ms' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(PlatformAiSetting::class, 'ai_agent_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
