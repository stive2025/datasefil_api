<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    /** @use HasFactory<\Database\Factories\AddressFactory> */
    use HasFactory;
    protected $fillable=[
        'address',
        'type',
        'province',
        'city',
        'is_valid',
        'client_id'
    ];
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
