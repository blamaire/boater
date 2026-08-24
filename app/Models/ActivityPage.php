<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een activiteitenpagina: een thematische, periodiek terugkerende happening
 * (bv. "Zomerkamp", "Clubkampioenschap") waar losse activiteiten bij kunnen
 * horen. Los van ACTIVITY_SERIES (§17.3, nog niet gebouwd) — dat is een
 * strak herhaalpatroon dat voorkomens genereert; dit is een los
 * containerbegrip met een eigen CMS-infopagina.
 *
 * @property int $id
 * @property int $page_id
 * @property int|null $created_by_person_id
 * @property-read Page $page
 * @property-read Person|null $createdBy
 * @property-read Collection<int, Activity> $activities
 */
class ActivityPage extends Model
{
    protected $fillable = [
        'page_id',
        'created_by_person_id',
    ];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
