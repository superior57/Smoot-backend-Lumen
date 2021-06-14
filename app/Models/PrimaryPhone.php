<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaryPhone extends Model
{
    protected $fillable = [
        'phone', 'verification_code', 'is_verified'
    ];

    protected $table = "primary_phones";
}
