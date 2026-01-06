<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasFactory;

    protected $fillable=[
        'phone_number',
        'client_id',
        'counter_correct_number',
        'counter_incorrect_number'
    ];
    
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
