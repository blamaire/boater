<?php

namespace App\Http\Controllers\Api;

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Cms\MediaSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Ontvangt een media-asset (binary + metadata) van een andere RZVG-omgeving.
 * Gebruikt door {@see MediaSyncService::upload()} om
 * ontbrekende media naar een target te synchroniseren voordat een pagina-push
 * de verwijzingen kan resolven.
 *
 * Als een asset met dezelfde UUID al bestaat wordt de bestaande ID
 * teruggegeven zonder overschrijven (idempotent).
 */
class MediaUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'uuid'],
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'file_size' => ['required', 'integer', 'min:0'],
            'alt' => ['nullable', 'string'],
            'visibility' => ['required', 'string'],
            'dimensions' => ['nullable', 'string'],
            'file' => ['required', 'file'],
            'thumbnail' => ['nullable', 'file'],
        ]);

        $uuid = $data['uuid'];

        $existing = MediaAsset::query()->where('uuid', $uuid)->first();
        if ($existing !== null) {
            return response()->json([
                'id' => $existing->id,
                'uuid' => $existing->uuid,
                'existed' => true,
            ]);
        }

        $type = MediaType::from($data['type']);
        $visibility = PageVisibility::from($data['visibility']);
        $dimensions = null;
        if (! empty($data['dimensions'])) {
            $decoded = json_decode($data['dimensions'], true);
            if (is_array($decoded)) {
                $dimensions = $decoded;
            }
        }

        $mediaDisk = 'media';
        $extension = $this->extensionFor($data['original_name']);
        $storedFilename = $uuid.($extension !== '' ? '.'.$extension : '');
        $storedPath = $request->file('file')->storeAs('', $storedFilename, ['disk' => $mediaDisk]);
        if ($storedPath === false) {
            throw new RuntimeException('Opslaan van bestand mislukt.');
        }

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbFile = $request->file('thumbnail');
            $thumbExt = $this->extensionFor($thumbFile->getClientOriginalName());
            $thumbFilename = 'thumb_'.$uuid.($thumbExt !== '' ? '.'.$thumbExt : '');
            $stored = $thumbFile->storeAs('', $thumbFilename, ['disk' => $mediaDisk]);
            if ($stored !== false) {
                $thumbnailPath = $stored;
            }
        }

        $asset = MediaAsset::query()->create([
            'uuid' => $uuid,
            'disk' => $mediaDisk,
            'path' => $storedPath,
            'thumbnail_path' => $thumbnailPath,
            'original_name' => $data['original_name'],
            'mime_type' => $data['mime_type'],
            'type' => $type,
            'file_size' => (int) $data['file_size'],
            'alt' => $data['alt'] ?? null,
            'dimensions' => $dimensions,
            'visibility' => $visibility,
            'uploaded_by_person_id' => null,
        ]);

        return response()->json([
            'id' => $asset->id,
            'uuid' => $asset->uuid,
            'existed' => false,
        ], 201);
    }

    private function extensionFor(string $filename): string
    {
        return pathinfo($filename, PATHINFO_EXTENSION) ?: '';
    }
}
