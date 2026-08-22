<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Onderwerp waaruit een bezoeker kan kiezen op het publieke contactformulier
 * (§1, "publiek contactformulier"). Platte, door een beheerder onderhouden
 * lijst — elk onderwerp heeft precies één verantwoordelijke persoon die
 * nieuwe verzoeken per mail ontvangt.
 *
 * @property int $id
 * @property string $name
 * @property int $responsible_person_id
 * @property int $sort_order
 * @property-read Person $responsible
 * @property-read Collection<int, ContactRequest> $requests
 */
class ContactTopic extends Model
{
    protected $fillable = [
        'name',
        'responsible_person_id',
        'sort_order',
    ];

    /** @return BelongsTo<Person, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'responsible_person_id');
    }

    /** @return HasMany<ContactRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ContactRequest::class);
    }
}
