<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $minor_person_id
 * @property int $guardian_person_id
 * @property bool $is_payer
 * @property bool $may_act_on_behalf
 * @property Carbon|null $consent_at
 * @property-read Person $minor
 * @property-read Person $guardian
 */
class Guardianship extends Model
{
    protected $fillable = [
        'minor_person_id',
        'guardian_person_id',
        'is_payer',
        'may_act_on_behalf',
        'consent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_payer' => 'bool',
            'may_act_on_behalf' => 'bool',
            'consent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function minor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'minor_person_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'guardian_person_id');
    }
}
