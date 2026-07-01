<?php

namespace App\Models;

use App\Enums\PageVersionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $page_id
 * @property int $version_no
 * @property PageVersionStatus $status
 * @property int|null $base_version_id
 * @property int|null $created_by_person_id
 * @property-read Page $page
 * @property-read PageVersion|null $baseVersion
 * @property-read Person|null $createdBy
 * @property-read Collection<int, Band> $bands
 */
class PageVersion extends Model
{
    protected $fillable = [
        'page_id',
        'version_no',
        'status',
        'base_version_id',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PageVersionStatus::class,
            'version_no' => 'integer',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<PageVersion, $this> */
    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'base_version_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    /** @return HasMany<Band, $this> */
    public function bands(): HasMany
    {
        return $this->hasMany(Band::class)->orderBy('sort_order');
    }
}
