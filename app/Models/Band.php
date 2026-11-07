<?php

namespace App\Models;

use App\Enums\BandBackground;
use App\Enums\BandLayout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $page_version_id
 * @property int|null $origin_band_id
 * @property string $zone
 * @property BandLayout $layout
 * @property BandBackground $background
 * @property int $sort_order
 * @property-read PageVersion $pageVersion
 * @property-read Band|null $originBand
 * @property-read Collection<int, Block> $blocks
 */
class Band extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'background' => 'wit',
    ];

    protected $fillable = [
        'page_version_id',
        'origin_band_id',
        'zone',
        'layout',
        'background',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'layout' => BandLayout::class,
            'background' => BandBackground::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<PageVersion, $this> */
    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class);
    }

    /** @return BelongsTo<Band, $this> */
    public function originBand(): BelongsTo
    {
        return $this->belongsTo(Band::class, 'origin_band_id');
    }

    /** @return HasMany<Block, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('column_index')->orderBy('sort_order');
    }
}
