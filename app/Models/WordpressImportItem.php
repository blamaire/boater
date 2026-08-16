<?php

namespace App\Models;

use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eén gestaged item (pagina of bericht) uit een WordPress WXR-export (§25).
 * Landt hier los van `pages`; een beheerder beslist per item of het wordt
 * overgenomen (echte CMS-`Page`, als concept) of gearchiveerd.
 *
 * @property int $id
 * @property int $wordpress_id
 * @property WordpressContentType $wordpress_type
 * @property string $title
 * @property string $slug
 * @property string $content_html
 * @property string|null $excerpt
 * @property Carbon|null $wordpress_published_at
 * @property WordpressImportStatus $status
 * @property int|null $page_id
 * @property array<string, mixed>|null $raw_meta
 * @property-read Page|null $page
 */
class WordpressImportItem extends Model
{
    protected $fillable = [
        'wordpress_id',
        'wordpress_type',
        'title',
        'slug',
        'content_html',
        'excerpt',
        'wordpress_published_at',
        'status',
        'page_id',
        'raw_meta',
    ];

    protected function casts(): array
    {
        return [
            'wordpress_type' => WordpressContentType::class,
            'status' => WordpressImportStatus::class,
            'wordpress_published_at' => 'datetime',
            'raw_meta' => 'array',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
