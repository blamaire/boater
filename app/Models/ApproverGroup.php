<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property-read Collection<int, Person> $members
 */
class ApproverGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    /** @return BelongsToMany<Person, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'group_members', 'group_id', 'person_id')
            ->withTimestamps();
    }
}
