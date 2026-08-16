<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén bijlage (`wp:post_type=attachment`) uit een WordPress WXR-export (§25),
 * gekoppeld aan het `WordpressImportItem` waar ze bij hoort. Een beheerder
 * bepaalt via `selected` of de bijlage bij het overnemen gedownload en als
 * `MediaAsset` toegevoegd wordt. `selected` is bewust nullable: `null` betekent
 * onbeslist (nog geen bewuste keuze gemaakt), `true`/`false` een expliciete
 * "overnemen"/"niet overnemen"-keuze.
 *
 * @property int $id
 * @property int $wordpress_import_item_id
 * @property int $wordpress_id
 * @property string $title
 * @property string $url
 * @property string|null $mime_type
 * @property bool|null $selected
 * @property int|null $media_asset_id
 * @property string|null $download_error
 * @property-read WordpressImportItem $importItem
 * @property-read MediaAsset|null $mediaAsset
 */
class WordpressImportMediaItem extends Model
{
    protected $fillable = [
        'wordpress_import_item_id',
        'wordpress_id',
        'title',
        'url',
        'mime_type',
        'selected',
        'media_asset_id',
        'download_error',
    ];

    protected function casts(): array
    {
        return [
            'selected' => 'boolean',
        ];
    }

    /** @return BelongsTo<WordpressImportItem, $this> */
    public function importItem(): BelongsTo
    {
        return $this->belongsTo(WordpressImportItem::class, 'wordpress_import_item_id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
