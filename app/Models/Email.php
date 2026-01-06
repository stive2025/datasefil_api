<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = [
        'direction',
        'client_id',
        'active',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
