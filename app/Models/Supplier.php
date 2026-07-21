<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'company',
        'accredited',
        'email',
        'telephone_no',
        'mobile_no',
        'is_active',
    ];

    protected $casts = [
        'accredited' => 'boolean',
        'is_active'  => 'boolean',
    ];
}
