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

    /**
     * Dropdown label: code, then the owning department (cost centre), then the
     * expense description — the department is what tells two same-sounding
     * descriptions apart. Missing parts are simply left out.
     */
    public function optionLabel(): string
    {
        return collect([$this->name, $this->cost_center, $this->description])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->implode(' — ');
    }
}
