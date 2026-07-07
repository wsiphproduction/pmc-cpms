<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalFeedback extends Model
{
    protected $table = 'technical_feedback';

    protected $fillable = [
        'project_request_id',
        'user_id',
        'disciplines',
        'permits',
        'priority',
        'remarks',
    ];

    protected $casts = [
        'disciplines' => 'array',
        'permits'     => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projectRequest(): BelongsTo
    {
        return $this->belongsTo(ProjectRequest::class);
    }
}
