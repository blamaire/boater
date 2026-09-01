<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opt-in/opt-out per persoon en categorie voor redactionele mail (§24.3).
 * Afwezigheid van een rij voor een categorie betekent niet opted-in — zie
 * App\Services\Communication\MessageDispatcher::isOptedIn().
 *
 * @property int $id
 * @property int $person_id
 * @property string $category
 * @property bool $opted_in
 * @property-read Person $person
 */
class CommunicationPreference extends Model
{
    protected $fillable = ['person_id', 'category', 'opted_in'];

    protected function casts(): array
    {
        return ['opted_in' => 'bool'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
