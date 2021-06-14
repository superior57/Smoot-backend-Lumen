<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $timestamps = false;
    protected $table = "products";
    protected $fillable = [
        'user_id',
        'last_category_id',
        'category_type_id',
        'title',
        'description',
        'is_blocked',
    ];


    public function image()
    {
        return $this->hasMany('App\Models\ProductImage');
    }

    public function sale_product()
    {
        return $this->hasOne('App\Models\SaleProduct', 'product_id', 'id');
    }

    public function free_product()
    {
        return $this->hasOne('App\Models\FreeProduct', 'product_id', 'id');
    }

    public function offer()
    {
        return $this->hasMany('App\Models\Offer');
    }

    public function specific_field_value()
    {
        return $this->hasMany('App\Models\ProductSpecificFieldValue');
    }

    public function deal_methods()
    {
        return $this->hasMany('App\Models\ProductDealMethod');
    }
}
