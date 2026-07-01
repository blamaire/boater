<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $original_name
 * @property string $mime_type
 * @property MediaType $type
 * @property int $file_size
 * @property string|null $alt
 * @property array<string, int>|null $dimensions
 * @property PageVisibility $visibility
 * @property int|null $uploaded_by_person_id
 * @property-read Person|null $uploadedBy
 * @property-read Collection<int, MediaTag> $tags
 */
class MediaAsset extends Model
{
    protected $fillable = [
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'type',
        'file_size',
        'alt',
        'dimensions',
        'visibility',
        'uploaded_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'visibility' => PageVisibility::class,
            'dimensions' => 'array',
            'file_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'uploaded_by_person_id');
    }

    /** @return BelongsToMany<MediaTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_asset_tag');
    }

    public function isPublic(): bool
    {
        return $this->visibility === PageVisibility::Public;
    }

    /**
     * URL waar de asset bereikbaar is. Publiek → direct van de disk; niet-publiek
     * → een 60 min geldige signed download-route die zichtbaarheid afdwingt.
     */
    public function displayUrl(): string
    {
        if ($this->isPublic()) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return URL::signedRoute('media.download', ['asset' => $this->id], now()->addMinutes(60));
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path === null) {
            return null;
        }

        if ($this->isPublic()) {
            return Storage::disk($this->disk)->url($this->thumbnail_path);
        }

        return URL::signedRoute('media.download', ['asset' => $this->id, 'thumb' => 1], now()->addMinutes(60));
    }

    /**
     * Geef de URL van een asset (uit bibliotheek) of val terug op een handmatige URL.
     * Gebruikt door blok-previews die zowel media_asset_id als url ondersteunen.
     */
    public static function resolveUrl(?int $assetId, ?string $fallbackUrl): ?string
    {
        if ($assetId !== null) {
            $asset = static::query()->find($assetId);
            if ($asset !== null) {
                return $asset->displayUrl();
            }
        }

        return $fallbackUrl !== '' ? $fallbackUrl : null;
    }
}
