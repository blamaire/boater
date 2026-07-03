<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ICE-contact (In Case of Emergency) van een lid. Elke persoon mag eigen
 * ICE-contacten beheren; vrijwilligers met permissie ice_contacts.view
 * kunnen deze tijdens activiteiten inzien.
 *
 * @property int $id
 * @property int $person_id
 * @property string $name
 * @property string $relation
 * @property string $phone
 * @property string|null $email
 * @property string|null $notes
 * @property-read Person $person
 */
class IceContact extends Model
{
    protected $fillable = [
        'person_id',
        'name',
        'relation',
        'phone',
        'email',
        'notes',
    ];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
