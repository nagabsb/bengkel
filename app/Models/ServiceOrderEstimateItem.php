<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ServiceOrderEstimateItem extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'service_order_estimate_id',
        'item_type',
        'spare_part_id',
        'label',
        'unit_label',
        'description',
        'qty',
        'unit_price',
        'subtotal',
        'meta',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'integer',
        'subtotal' => 'integer',
        'meta' => 'array',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderEstimate::class, 'service_order_estimate_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }
}

