<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaryEmail extends Model
{
    protected $fillable = [
        'email', 'verification_code', 'is_verified'
    ];

    protected $table = "primary_emails";

    public function user() {
        return $this->hasOne('App\Models\User', 'primary_email_id');
    }
}
