<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDealMethod extends Model
{
    public $timestamps = false;
    public $primaryKey = 'product_id';
    protected $table = "product_deal_methods";
    protected $fillable = [
        'product_id',
        'deal_method_id',
        'country_id',
        'district_id',
        'city_id',
        'description',
    ];

    public function product_deal_methods()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
