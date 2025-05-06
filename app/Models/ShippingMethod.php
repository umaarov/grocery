<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cost',
        'estimated_days',
        'description',
        'is_active'
    ];

    protected $casts = [
        'cost' => 'float',
        'estimated_days' => 'integer',
        'is_active' => 'boolean',
    ];
}
