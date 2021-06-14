<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStatus extends Model
{
    public $timestamps = false;
    protected $table = "product_statuses";
    protected $fillable = [
        'text',
        'value',
        'is_active',
    ];
}
