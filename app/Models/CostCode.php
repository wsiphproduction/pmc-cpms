<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'division',
        'cost_center',
        'activity',
        'expense_description',
        'agu_per_class',
        'agu_per_stat',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
