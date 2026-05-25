<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMasterBrand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'vehicle_type',
        'external_id',
        'source',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(VehicleMasterModel::class, 'vehicle_master_brand_id');
    }
}

