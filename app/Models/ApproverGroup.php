<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApproverGroup extends Model
{
    protected $fillable = [
        'name',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'group_members', 'group_id', 'person_id')
            ->withTimestamps();
    }
}
