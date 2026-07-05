<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expliciete koppeling tussen twee personen (bv. ouder van jeugdlid).
 * `type` is een string uit een klein vocabulaire: 'ouder_van', 'verzorger_van'.
 *
 * @property int $id
 * @property int $person_id
 * @property int $related_person_id
 * @property string $type
 * @property-read Person $person
 * @property-read Person $relatedPerson
 */
class PersonRelation extends Model
{
    protected $fillable = [
        'person_id',
        'related_person_id',
        'type',
    ];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }
}
