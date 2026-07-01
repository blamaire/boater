<?php

namespace App\Models;

use App\Enums\BandLayout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $page_version_id
 * @property string $zone
 * @property BandLayout $layout
 * @property int $sort_order
 * @property-read PageVersion $pageVersion
 * @property-read Collection<int, Block> $blocks
 */
class Band extends Model
{
    protected $fillable = [
        'page_version_id',
        'zone',
        'layout',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'layout' => BandLayout::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<PageVersion, $this> */
    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class);
    }

    /** @return HasMany<Block, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('column_index')->orderBy('sort_order');
    }
}
