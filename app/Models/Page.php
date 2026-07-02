<?php

namespace App\Models;

use App\Enums\PageType;
use App\Enums\PageVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property PageType $type
 * @property PageVisibility $visibility
 * @property int|null $parent_id
 * @property int $template_id
 * @property int|null $published_version_id
 * @property-read Template $template
 * @property-read Page|null $parent
 * @property-read Collection<int, Page> $children
 * @property-read Collection<int, PageVersion> $versions
 * @property-read PageVersion|null $publishedVersion
 */
class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'type',
        'visibility',
        'parent_id',
        'template_id',
        'published_version_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => PageType::class,
            'visibility' => PageVisibility::class,
        ];
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    /** @return HasMany<PageVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc('version_no');
    }

    /** @return BelongsTo<PageVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    /**
     * Full slug from root: "ouder/kind/kleinkind".
     */
    public function path(): string
    {
        $segments = collect();
        $current = $this;
        while ($current !== null) {
            $segments->prepend($current->slug);
            $current = $current->parent;
        }

        return $segments->implode('/');
    }

    /**
     * Publieke URL waaronder deze pagina bereikbaar is. De systeem-home
     * (type=system, slug=home, root-level) is bereikbaar op '/'; alle andere
     * pagina's op '/pagina/<path>' — inclusief een eventuele content-pagina
     * die ook 'home' heet.
     */
    public function publicUrl(): string
    {
        if ($this->parent_id === null && $this->slug === 'home' && $this->type === PageType::System) {
            return '/';
        }

        return '/pagina/'.$this->path();
    }
}
