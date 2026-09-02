<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRfqItem extends Model
{
    protected $fillable = [
        'project_rfq_id',
        'project_rfq_quotation_id',
        'seq',
        'description',
        'qty',
        'unit',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'qty'        => 'decimal:2',
        'unit_cost'  => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProjectRfq::class, 'project_rfq_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(ProjectRfqQuotation::class, 'project_rfq_quotation_id');
    }
}
