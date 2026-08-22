<?php

namespace App\Models;

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
 * @property bool $contact_by_phone
 * @property bool $contact_by_email
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
        'contact_by_phone',
        'contact_by_email',
        'message',
        'status',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'contact_by_phone' => 'boolean',
            'contact_by_email' => 'boolean',
            'status' => ContactRequestStatus::class,
        ];
    }

    /** @return BelongsTo<ContactTopic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ContactTopic::class, 'contact_topic_id');
    }

    public function contactMethodLabel(): string
    {
        return match (true) {
            $this->contact_by_phone && $this->contact_by_email => 'Telefonisch en per e-mail',
            $this->contact_by_phone => 'Telefonisch',
            $this->contact_by_email => 'Per e-mail',
            default => '—',
        };
    }
}
