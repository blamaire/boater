<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $fillable = [
        'name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country',
    ];

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
