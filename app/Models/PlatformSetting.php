<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'app_logo_path',
        'logo_background_enabled',
        'logo_background_color',
    ];

    protected $casts = [
        'logo_background_enabled' => 'boolean',
    ];
}
