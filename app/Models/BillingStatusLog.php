<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingStatusLog extends Model
{
    protected $fillable = [
        'project_billing_id',
        'user_id',
        'status',
        'remarks',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(ProjectBilling::class, 'project_billing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
