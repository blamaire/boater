<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton met de beheersbare site-brede instellingen (footer-contactblok,
 * sociale-media-URL's, verwijzingen naar disclaimer/AVG-pagina's).
 *
 * @property int $id
 * @property string|null $contact_name
 * @property string|null $contact_address
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $youtube_url
 * @property int|null $privacy_page_id
 * @property int|null $terms_page_id
 * @property-read Page|null $privacyPage
 * @property-read Page|null $termsPage
 */
class SiteSettings extends Model
{
    protected $fillable = [
        'contact_name',
        'contact_address',
        'contact_email',
        'contact_phone',
        'facebook_url',
        'instagram_url',
        'youtube_url',
        'privacy_page_id',
        'terms_page_id',
    ];

    /**
     * Haal (of maak) het singleton-record op.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    /** @return BelongsTo<Page, $this> */
    public function privacyPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'privacy_page_id');
    }

    /** @return BelongsTo<Page, $this> */
    public function termsPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'terms_page_id');
    }

    public function hasSocials(): bool
    {
        return $this->facebook_url !== null || $this->instagram_url !== null || $this->youtube_url !== null;
    }
}
