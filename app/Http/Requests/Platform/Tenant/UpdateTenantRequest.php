<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.tenants.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->route('tenant');
        $subdomainRules = ['nullable', 'string', 'max:63', 'regex:/^(?!-)[A-Za-z0-9-]+(?<!-)$/'];

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'subdomain')) {
            $subdomainRules[] = Rule::unique('tenants', 'subdomain')->ignore($tenantId);
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('tenants', 'code')->ignore($tenantId),
            ],
            'subdomain' => $subdomainRules,
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'plan_price_id' => ['nullable', 'integer', 'exists:plan_prices,id'],
            'plan_started_at' => ['nullable', 'date_format:Y-m-d', 'required_with:plan_price_id'],
        ];
    }
}
