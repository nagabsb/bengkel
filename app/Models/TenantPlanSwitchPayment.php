<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TenantPlanSwitchPayment extends Model
{
    use HasFactory;
    use HasUlids;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'requested_by_user_id',
        'current_plan_price_id',
        'target_plan_price_id',
        'payment_method',
        'status',
        'amount',
        'currency',
        'payment_gateway',
        'payment_gateway_reference',
        'payment_url',
        'manual_provider_id',
        'manual_provider_name',
        'manual_account_name',
        'manual_account_number',
        'notes',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function currentPlanPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'current_plan_price_id');
    }

    public function targetPlanPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'target_plan_price_id');
    }

    public function manualProvider(): BelongsTo
    {
        return $this->belongsTo(PlatformManualPaymentProvider::class, 'manual_provider_id');
    }
}
