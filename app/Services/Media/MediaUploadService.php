<?php

namespace App\Services\Media;

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use App\Models\MediaAsset;
use App\Models\MediaTag;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class MediaUploadService
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    public const int THUMB_SIZE = 300;

    /** @var array<int, string> */
    public const array ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    /** @var array<int, string> */
    public const array ALLOWED_DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
    ];

    /**
     * @param  array<int, string>  $tagNames
     */
    public function store(
        UploadedFile $file,
        ?Person $uploadedBy = null,
        PageVisibility $visibility = PageVisibility::Public,
        ?string $alt = null,
        array $tagNames = [],
    ): MediaAsset {
        $this->validate($file);

        $disk = config('filesystems.disks.media') !== null ? 'media' : 'public';
        $extension = strtolower($file->getClientOriginalExtension() ?: $this->extensionFromMime($file->getMimeType() ?? ''));
        $filename = Str::random(24).'.'.$extension;
        $path = 'assets/'.now()->format('Y/m').'/'.$filename;

        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path), [
            'visibility' => $visibility === PageVisibility::Public ? 'public' : 'private',
        ]);

        if ($stored === false) {
            throw new FileException('Kon bestand niet opslaan op disk ['.$disk.'].');
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $type = MediaType::fromMime($mime);
        $dimensions = null;
        $thumbnailPath = null;

        if ($type === MediaType::Image && $mime !== 'image/svg+xml') {
            $absolute = Storage::disk($disk)->path($path);
            $info = @getimagesize($absolute);
            if ($info !== false) {
                $dimensions = ['width' => $info[0], 'height' => $info[1]];
            }
            $thumbnailPath = $this->generateThumbnail($disk, $path, $filename, $visibility);
        }

        $asset = MediaAsset::create([
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'type' => $type,
            'file_size' => $file->getSize() ?: 0,
            'alt' => $alt,
            'dimensions' => $dimensions,
            'visibility' => $visibility,
            'uploaded_by_person_id' => $uploadedBy?->id,
        ]);

        if ($tagNames !== []) {
            $this->syncTags($asset, $tagNames);
        }

        return $asset;
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    public function syncTags(MediaAsset $asset, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->map(fn ($name) => MediaTag::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)],
            )->id)
            ->all();

        $asset->tags()->sync($tagIds);
    }

    private function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new FileException('Bestand kon niet worden ontvangen.');
        }

        $size = $file->getSize() ?: 0;
        if ($size > self::MAX_BYTES) {
            throw new FileException('Bestand is groter dan 10 MB.');
        }

        $mime = $file->getMimeType() ?? '';
        $allowed = array_merge(self::ALLOWED_IMAGE_MIMES, self::ALLOWED_DOCUMENT_MIMES);
        if (! in_array($mime, $allowed, true)) {
            throw new FileException('Bestandstype ['.$mime.'] wordt niet ondersteund.');
        }
    }

    private function generateThumbnail(string $disk, string $sourcePath, string $filename, PageVisibility $visibility): ?string
    {
        try {
            $manager = new ImageManager(new GdDriver);
            $absolute = Storage::disk($disk)->path($sourcePath);
            $image = $manager->decodePath($absolute)->scaleDown(self::THUMB_SIZE, self::THUMB_SIZE);
            $encoded = $image->encode(new WebpEncoder(quality: 80));

            $thumbPath = 'thumbnails/'.now()->format('Y/m').'/'.pathinfo($filename, PATHINFO_FILENAME).'.webp';
            Storage::disk($disk)->put($thumbPath, (string) $encoded, [
                'visibility' => $visibility === PageVisibility::Public ? 'public' : 'private',
            ]);

            return $thumbPath;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/csv' => 'csv',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
