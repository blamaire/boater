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
 * @property int|null $wordpress_id
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

    /**
     * Komt deze bijlage daadwerkelijk voor in de gegeven content (bv. het
     * content_html van een item — niet per se het item waar deze bijlage zelf
     * aan gekoppeld is via wp:post_parent, want WordPress laat pagina's vaak
     * elkaars geüploade afbeeldingen hergebruiken)? WordPress zet in de tekst
     * meestal een geschaalde variant van de bestandsnaam neer (`-300x200.jpg`,
     * eventueel na een `-scaled`-achtervoegsel dat WordPress zelf al aan grote
     * originelen hangt) i.p.v. de kale `wp:attachment_url`, dus die
     * achtervoegsels worden genegeerd bij het vergelijken.
     */
    public function isReferencedIn(string $contentHtml): bool
    {
        $path = parse_url($this->url, PHP_URL_PATH) ?: '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $baseFilename = self::normalizedBaseFilename($this->url);

        if ($baseFilename === '' || $extension === '') {
            return false;
        }

        $pattern = '/'.preg_quote($baseFilename, '/').'(-scaled)?(-\d+x\d+)?\.'.preg_quote($extension, '/').'/i';

        return preg_match($pattern, $contentHtml) === 1;
    }

    /**
     * Kale, vergelijkbare bestandsnaam (zonder pad/extensie) voor een
     * bijlage-URL: haalt een eventueel `-scaled`- en/of `-WxH`-achtervoegsel
     * eraf, ongeacht de volgorde waarin WordPress ze toevoegt. Gebruikt om
     * eenzelfde bestand te herkennen ongeacht welke variant (origineel,
     * geschaald, thumbnail) er precies naar verwezen wordt.
     */
    public static function normalizedBaseFilename(string $url): string
    {
        $filename = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
        $filename = preg_replace('/-\d+x\d+$/', '', $filename) ?? $filename;
        $filename = preg_replace('/-scaled$/i', '', $filename) ?? $filename;

        return strtolower($filename);
    }
}
