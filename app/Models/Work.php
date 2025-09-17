<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    /** @use HasFactory<\Database\Factories\WorkFactory> */
    use HasFactory;
    protected $fillable=[
        'type',
        'address',
        'province',
        'ruc',
        'activities_start_date',
        'suspension_request_date',
        'legal_name',
        'activities_restart_date',
        'phone',
        'taxpayer_status',
        'email',
        'economic_activity',
        'business_name',
        'client_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

}
