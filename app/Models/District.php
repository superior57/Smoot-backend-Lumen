<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = [
        'country_id', 'text', 'value', 'is_active',
    ];
}
