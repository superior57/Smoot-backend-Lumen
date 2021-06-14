<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreeProduct extends Model
{
    protected $table = "free_products";

    protected $fillable = [
        'product_id',
        'product_status_id',
        'product_condition_id',
    ];

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id', 'id');
    }
}
