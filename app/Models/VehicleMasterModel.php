<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMasterModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_master_brand_id',
        'name',
        'slug',
        'vehicle_type',
        'external_id',
        'source',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'vehicle_master_brand_id' => 'integer',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleMasterBrand::class, 'vehicle_master_brand_id');
    }
}

