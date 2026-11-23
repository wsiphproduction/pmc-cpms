<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One of the eight forms (A–H) inside a contractor evaluation: the criterion
 * ratings, the score they give, the evaluator's comments and required
 * corrective actions, plus the signed form and supporting evidence.
 */
class ContractorEvaluationCategory extends Model
{
    /** Attachment `type`s: the signed paper form, and the evidence behind the ratings. */
    public const FILE_FORM = 'evaluation_form';
    public const FILE_EVIDENCE = 'evaluation_evidence';

    protected $fillable = [
        'contractor_evaluation_id',
        'category',
        'ratings',
        'score',
        'comments',
        'corrective_actions',
    ];

    protected $casts = [
        'ratings' => 'array',
        'score'   => 'float',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ContractorEvaluation::class, 'contractor_evaluation_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'reference', 'reference_type', 'reference_id')->oldest('id');
    }
}
