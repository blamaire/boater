<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $menu
 * @property int|null $page_id
 * @property int|null $parent_id
 * @property string|null $label
 * @property string|null $href
 * @property int $sort_order
 * @property bool $visible
 * @property-read Page|null $page
 * @property-read NavItem|null $parent
 * @property-read Collection<int, NavItem> $children
 */
class NavItem extends Model
{
    protected $fillable = [
        'menu',
        'page_id',
        'parent_id',
        'label',
        'href',
        'sort_order',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<NavItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavItem::class, 'parent_id');
    }

    /** @return HasMany<NavItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(NavItem::class, 'parent_id')->orderBy('sort_order');
    }
}
