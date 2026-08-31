<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Model;

/**
 * Sjabloon voor transactionele of redactionele communicatie (§24.4). Code
 * verwijst naar een sjabloon via `key`; `MessageBlockRenderer` substitueert
 * `{{variabele}}`-tokens in elk block van `body` en rendert e-mail-veilige
 * HTML; `MessageDispatcher` verstuurt die via `TemplatedMail`.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property array<int, array{type: string, content: array<string, mixed>}> $body
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
            'body' => 'array',
        ];
    }
}
