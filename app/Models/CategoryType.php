<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryType extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = [
        "last_category_id", "category_type_id", "is_active"
    ];
    protected $table = "category_type";
    protected $primaryKey = ["last_category_id, category_type_id"];
}
