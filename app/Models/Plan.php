<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'max_workshops',
        'max_users_per_ws',
        'has_ai_feature',
        'has_notification',
        'has_loyalty',
        'has_trial',
        'trial_duration_days',
        'is_active',
    ];

    protected $casts = [
        'max_workshops' => 'integer',
        'max_users_per_ws' => 'integer',
        'has_ai_feature' => 'boolean',
        'has_notification' => 'boolean',
        'has_loyalty' => 'boolean',
        'has_trial' => 'boolean',
        'trial_duration_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'plan_menu', 'plan_id', 'menu_id');
    }
}

