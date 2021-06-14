<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecificFieldValue extends Model
{
    protected $table = "product_specific_field_values";

    protected $fillable = [
        'product_id',
        'category_based_field_id',
        'category_based_field_lookup_value_id',
        'text',
    ];

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
