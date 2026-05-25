<?php

namespace App\Http\Requests\Platform\AiAgent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.tenants.manage');
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini', 'groq', 'mistral', 'deepseek', 'kimi'])],
            'agent_model' => ['required', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'min:10', 'max:4096'],
            'remove_api_key' => ['nullable', 'boolean'],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:10000', Rule::unique('platform_ai_settings', 'priority_order')->ignore((int) $this->route('agent'))],
            'monthly_token_limit' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_failover_enabled' => ['nullable', 'boolean'],
        ];
    }
}



