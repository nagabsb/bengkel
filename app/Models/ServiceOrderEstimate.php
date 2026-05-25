<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ServiceOrderEstimate extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'service_order_id',
        'code',
        'revision',
        'status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'subtotal_service',
        'subtotal_sparepart',
        'total_amount',
        'valid_until',
        'approval_requested_at',
        'approved_at',
        'rejected_at',
        'expired_at',
        'approval_token_hash',
        'approved_by_name',
        'approved_by_phone',
        'approved_signature_path',
        'approved_ip',
        'approved_user_agent',
        'approval_note',
        'rejection_reason',
        'internal_note',
        'requested_by_user_id',
        'approval_payload',
    ];

    protected $casts = [
        'revision' => 'integer',
        'subtotal_service' => 'integer',
        'subtotal_sparepart' => 'integer',
        'total_amount' => 'integer',
        'valid_until' => 'datetime',
        'approval_requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expired_at' => 'datetime',
        'approval_payload' => 'array',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderEstimateItem::class, 'service_order_estimate_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}

