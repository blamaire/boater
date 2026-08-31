<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Vastlegging van een contactmoment (§30.2) — in deze fase alleen automatisch
 * geschreven vanuit `MessageDispatcher` bij elke verstuurde e-mail. Een
 * handmatige-invoer-beheerscherm (telefoon/gesprek/brief) is een apart,
 * later onderwerp.
 *
 * @property int $id
 * @property int|null $person_id
 * @property string|null $email
 * @property CommunicationChannel $channel
 * @property CommunicationDirection $direction
 * @property string $subject
 * @property string|null $notes
 * @property int|null $logged_by_person_id
 * @property Carbon $occurred_at
 * @property string|null $related_type
 * @property int|null $related_id
 * @property-read Person|null $person
 * @property-read Person|null $loggedBy
 */
class CommunicationLog extends Model
{
    protected $fillable = [
        'person_id',
        'email',
        'channel',
        'direction',
        'subject',
        'notes',
        'logged_by_person_id',
        'occurred_at',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'direction' => CommunicationDirection::class,
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'logged_by_person_id');
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
