<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Model;

/**
 * Sjabloon voor transactionele of redactionele communicatie (§24.4). Code
 * verwijst naar een sjabloon via `key`; `MessageDispatcher` substitueert
 * `{{variabele}}`-tokens in `subject`/`body` en verstuurt via `TemplatedMail`.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property MessageType $type
 */
class MessageTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
        ];
    }
}
