<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class WorkshopMenuOverride extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'menu_id',
        'custom_label',
        'custom_route',
        'custom_icon',
        'sort_order',
        'is_visible',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function workshop(): BelongsTo
    {
        return $this->tenant();
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
