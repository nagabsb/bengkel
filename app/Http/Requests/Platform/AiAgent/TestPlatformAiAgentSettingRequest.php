<?php

namespace App\Http\Requests\Platform\AiAgent;

use Illuminate\Foundation\Http\FormRequest;

class TestPlatformAiAgentSettingRequest extends FormRequest
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
            'api_key' => ['nullable', 'string', 'min:10', 'max:4096'],
        ];
    }
}
