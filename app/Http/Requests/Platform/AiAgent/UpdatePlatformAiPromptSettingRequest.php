<?php

namespace App\Http\Requests\Platform\AiAgent;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformAiPromptSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.tenants.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'system_prompt' => ['required', 'string', 'max:30000'],
            'feature_prompt' => ['nullable', 'string', 'max:30000'],
            'feature_prompt_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
