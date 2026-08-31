<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sjabloon voor transactionele of redactionele communicatie (§24.4). Code
 * verwijst naar een sjabloon via `key`; `MessageBlockRenderer` substitueert
 * `{{variabele}}`-tokens in elk block van `body` en rendert e-mail-veilige
 * HTML; `MessageDispatcher` verstuurt die via `TemplatedMail`. `type` wordt
 * bij aanmaken via de beheer-UI afgeleid van de root van `folder` (niet meer
 * los ingevoerd) — zie App\Livewire\Admin\MessageTemplateBeheer.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property array<int, array{type: string, content: array<string, mixed>}> $body
 * @property MessageType $type
 * @property int $message_template_folder_id
 * @property-read MessageTemplateFolder $folder
 */
class MessageTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'type',
        'message_template_folder_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'body' => 'array',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MessageTemplateFolder::class, 'message_template_folder_id');
    }
}
