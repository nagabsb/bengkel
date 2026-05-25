<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformManualPaymentProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_method_id',
        'provider_name',
        'account_name',
        'account_number',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PlatformPaymentMethod::class, 'payment_method_id');
    }
}
