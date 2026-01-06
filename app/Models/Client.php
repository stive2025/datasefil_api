<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;
    protected $fillable=[
        'identification',
        'name',
        'email',
        'micro_activa',
        'birth',
        'death',
        'gender',
        'state_civil',
        'economic_activity',
        'economic_area',
        'nationality',
        'profession',
        'place_birth',
        'salary'
    ];

    protected $appends = ['age'];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function works()
    {
        return $this->hasMany(Work::class);
    }

    public function address()
    {
        return $this->hasMany(Address::class);
    }

    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    public function parents()
    {
        return $this->hasMany(Relationship::class);
    }

    public function financials()
    {
        return $this->hasMany(Financial::class);
    }
    
    public function getAgeAttribute()
    {
        return Carbon::parse($this->birth)->age;
    }
}
