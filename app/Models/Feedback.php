<?php

namespace App\Models;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Terugkoppeling die een ingelogde gebruiker vanaf willekeurig welke pagina
 * kan indienen (zie `FeedbackWidget`). Legt behalve het bericht ook de
 * context vast waarin het is ingediend: URL, applicatieversie, en — als het
 * om een publieke CMS-pagina ging — welke `Page`/`PageVersion` daadwerkelijk
 * werd bekeken.
 *
 * @property int $id
 * @property int $person_id
 * @property FeedbackCategory $category
 * @property string $message
 * @property string $url
 * @property string|null $app_version
 * @property int|null $page_id
 * @property int|null $page_version_id
 * @property FeedbackStatus $status
 * @property-read Person $person
 * @property-read Page|null $page
 * @property-read PageVersion|null $pageVersion
 */
class Feedback extends Model
{
    protected $table = 'feedback_items';

    protected $fillable = [
        'person_id',
        'category',
        'message',
        'url',
        'app_version',
        'page_id',
        'page_version_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'category' => FeedbackCategory::class,
            'status' => FeedbackStatus::class,
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<PageVersion, $this> */
    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class);
    }
}
