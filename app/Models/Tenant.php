<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'name',
        'code',
        'subdomain',
        'phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'tenant_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkshopSubscription::class, 'tenant_id');
    }

    public function menuOverrides(): HasMany
    {
        return $this->hasMany(WorkshopMenuOverride::class, 'tenant_id');
    }
}
