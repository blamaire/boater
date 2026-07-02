<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $person_id
 * @property string $field_key
 * @property bool $visible_to_members
 * @property-read Person $person
 */
class PersonFieldVisibility extends Model
{
    protected $fillable = [
        'person_id',
        'field_key',
        'visible_to_members',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_members' => 'boolean',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
