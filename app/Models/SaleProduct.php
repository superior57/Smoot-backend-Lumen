<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleProduct extends Model
{
    protected $table = "sale_products";

    protected $fillable = [
        'product_id',
        'product_status_id',
        'product_condition_id',
        'price',
    ];

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id', 'id');
    }
}
