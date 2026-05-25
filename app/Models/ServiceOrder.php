<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ServiceOrder extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'customer_vehicle_id',
        'code',
        'service_date',
        'status',
        'started_at',
        'completed_at',
        'complaint',
        'completion_notes',
        'vehicle_condition',
        'estimated_days',
        'estimated_finish_date',
        'odometer',
        'service_fee',
        'total_amount',
        'created_by_user_id',
    ];

    protected $casts = [
        'service_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_days' => 'integer',
        'estimated_finish_date' => 'date',
        'odometer' => 'integer',
        'service_fee' => 'integer',
        'total_amount' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function mechanics(): HasMany
    {
        return $this->hasMany(ServiceOrderMechanic::class, 'service_order_id');
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(ServiceOrderSparePart::class, 'service_order_id');
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(ServiceOrderEstimate::class, 'service_order_id');
    }

    public function latestEstimate(): HasOne
    {
        return $this->hasOne(ServiceOrderEstimate::class, 'service_order_id')
            ->latestOfMany('created_at');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'service_order_id');
    }
}
