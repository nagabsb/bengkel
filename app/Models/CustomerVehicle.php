<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CustomerVehicle extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'vehicle_master_brand_id',
        'vehicle_master_model_id',
        'vehicle_type',
        'brand',
        'model',
        'variant',
        'plate_number',
        'year',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'vehicle_master_brand_id' => 'integer',
        'vehicle_master_model_id' => 'integer',
        'is_active' => 'boolean',
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

    public function masterBrand(): BelongsTo
    {
        return $this->belongsTo(VehicleMasterBrand::class, 'vehicle_master_brand_id');
    }

    public function masterModel(): BelongsTo
    {
        return $this->belongsTo(VehicleMasterModel::class, 'vehicle_master_model_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'customer_vehicle_id');
    }
}
