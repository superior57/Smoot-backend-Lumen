<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'district_id', 'text', 'value', 'is_active',
    ];
}
