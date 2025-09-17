<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Relationship extends Model
{
    /** @use HasFactory<\Database\Factories\RelationshipFactory> */
    use HasFactory;

    protected $fillable=[
        'type',
        'relationship_client_id',
        'client_id'
    ];

    protected $hidden = [
        'client',
        'relatedClient'
    ];

    // Incluye el nombre del cliente como atributo adicional
    protected $appends = [
        'name',
        'identification',
        'birth',
        'gender',
        'state_civil',
        'death',
        'age'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function relatedClient()
{
    return $this->belongsTo(Client::class, 'relationship_client_id');
}

    public function name(): Attribute
    {
        return Attribute::get(fn () => $this->relatedClient?->name);
    }

    public function identification(): Attribute
    {
        return Attribute::get(fn () => $this->relatedClient?->identification);
    }

    public function birth(): Attribute
    {
        return Attribute::get(fn () => $this->relatedClient?->birth);
    }

    public function stateCivil(): Attribute
    {
        return Attribute::get(fn () => $this->relatedClient?->state_civil);
    }

    public function gender(): Attribute
    {
        return Attribute::get(fn () => $this->relatedClient?->gender);
    }

    public function death(): Attribute
    {
        return Attribute::get(fn () => $this->relatedClient?->death);
    }
    
    public function getAgeAttribute()
    {
        return Carbon::parse($this->birth)->age;
    }
}