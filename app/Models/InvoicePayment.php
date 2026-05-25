<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class InvoicePayment extends Model
{
    use HasFactory;
    use HasUlids;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'workshop_id',
        'invoice_id',
        'paid_at',
        'amount',
        'method',
        'reference_number',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

