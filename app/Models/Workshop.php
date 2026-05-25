<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workshop extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'workshop_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'tenant_id', 'tenant_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkshopSubscription::class, 'tenant_id', 'tenant_id');
    }

    public function menuOverrides(): HasMany
    {
        return $this->hasMany(WorkshopMenuOverride::class, 'tenant_id', 'tenant_id');
    }
}
