<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformAiSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'agent_model',
        'api_key',
        'priority_order',
        'is_active',
        'is_default',
        'is_failover_enabled',
        'monthly_token_limit',
        'used_token_count',
        'test_success_count',
        'test_failed_count',
        'last_test_status',
        'last_test_message',
        'last_test_prompt_tokens',
        'last_test_completion_tokens',
        'last_test_total_tokens',
        'last_known_quota_remaining',
        'last_tested_at',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_failover_enabled' => 'boolean',
        'monthly_token_limit' => 'integer',
        'used_token_count' => 'integer',
        'last_tested_at' => 'datetime',
    ];
}
