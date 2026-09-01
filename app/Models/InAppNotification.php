<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * In-app melding voor het notificatiecentrum (§24.2) — bewust niet
 * `Notification` genoemd (naamsbotsing met `Illuminate\Notifications\Notification`).
 * Geen `updated_at`: het enige veld dat na aanmaken wijzigt is `read_at`.
 *
 * @property int $id
 * @property int $person_id
 * @property string $type
 * @property string $subject
 * @property string|null $body
 * @property string|null $link
 * @property Carbon|null $read_at
 * @property-read Person $person
 */
class InAppNotification extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['person_id', 'type', 'subject', 'body', 'link'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->read_at = now();
            $this->save();
        }
    }
}
