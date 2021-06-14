<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $table = "offers";

    protected $fillable = [
        'user_id',
        'offer',
        'is_accepted'
    ];

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
