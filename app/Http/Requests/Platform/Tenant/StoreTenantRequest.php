<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class StoreTenantRequest extends FormRequest
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
        $subdomainRules = ['nullable', 'string', 'max:63', 'regex:/^(?!-)[A-Za-z0-9-]+(?<!-)$/'];

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'subdomain')) {
            $subdomainRules[] = 'unique:tenants,subdomain';
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', 'unique:tenants,code'],
            'subdomain' => $subdomainRules,
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'plan_price_id' => ['nullable', 'integer', 'exists:plan_prices,id'],
            'plan_started_at' => ['nullable', 'date_format:Y-m-d', 'required_with:plan_price_id'],
        ];
    }
}
