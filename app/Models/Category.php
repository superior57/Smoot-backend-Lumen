<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'master_category_id', 'text', 'value', 'is_active'
    ];
}

