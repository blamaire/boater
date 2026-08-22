<?php

namespace App\Models;

use App\Enums\ContactPreferredMethod;
use App\Enums\ContactRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een via het publieke contactformulier ingediend verzoek om teruggebeld of
 * -gemaild te worden. Anoniem ingediend (geen Person-actor) — de audit-log
 * begint pas bij een statuswijziging door een beheerder.
 *
 * @property int $id
 * @property int $contact_topic_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property ContactPreferredMethod $preferred_contact_method
 * @property string $message
 * @property ContactRequestStatus $status
 * @property string|null $ip_address
 * @property-read ContactTopic $topic
 */
class ContactRequest extends Model
{
    protected $fillable = [
        'contact_topic_id',
        'name',
        'phone',
        'email',
        'preferred_contact_method',
        'message',
        'status',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'preferred_contact_method' => ContactPreferredMethod::class,
            'status' => ContactRequestStatus::class,
        ];
    }

    /** @return BelongsTo<ContactTopic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ContactTopic::class, 'contact_topic_id');
    }
}
