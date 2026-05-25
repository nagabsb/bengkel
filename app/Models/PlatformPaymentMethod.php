<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'is_enabled',
        'midtrans_environment',
        'midtrans_merchant_id',
        'midtrans_server_key',
        'midtrans_client_key',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'midtrans_server_key' => 'encrypted',
        'midtrans_client_key' => 'encrypted',
    ];

    public function manualProviders(): HasMany
    {
        return $this->hasMany(PlatformManualPaymentProvider::class, 'payment_method_id');
    }
}
