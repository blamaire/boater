<?php

namespace App\Services\Cms;

use App\Models\Environment;
use App\Models\MediaAsset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Synchroniseert media-assets tussen twee RZVG-omgevingen. Bij een pagina-push
 * vraagt {@see PagePushService} deze service welke UUIDs op de target
 * ontbreken en welke daarom als binary meegestuurd moeten worden.
 */
class MediaSyncService
{
    /**
     * Vraag de target welke UUIDs uit `$uuids` daar ontbreken.
     *
     * @param  array<int, string>  $uuids
     * @return array<int, string>
     */
    public function probe(Environment $environment, array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        try {
            $response = Http::withToken($environment->api_token)
                ->acceptJson()
                ->timeout(30)
                ->post($environment->baseUrl().'/api/media/probe', ['uuids' => array_values($uuids)]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Kan omgeving [{$environment->name}] niet bereiken voor media-probe: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new RuntimeException("Media-probe op [{$environment->name}] mislukt (HTTP {$response->status()}): {$response->body()}");
        }

        /** @var array<int, string> $missing */
        $missing = $response->json('missing_uuids') ?? [];

        return array_values(array_map('strval', $missing));
    }

    /**
     * Upload één asset naar de target-omgeving en retourneer de nieuwe lokale
     * asset-ID die daar is aangemaakt.
     */
    public function upload(Environment $environment, MediaAsset $asset): int
    {
        $absolutePath = Storage::disk($asset->disk)->path($asset->path);
        if (! file_exists($absolutePath)) {
            throw new RuntimeException("Bestand voor asset [{$asset->uuid}] ontbreekt lokaal: {$absolutePath}");
        }

        $thumbnailPath = $asset->thumbnail_path !== null
            ? Storage::disk($asset->disk)->path($asset->thumbnail_path)
            : null;

        try {
            $request = Http::withToken($environment->api_token)
                ->acceptJson()
                ->timeout(120)
                ->attach('file', file_get_contents($absolutePath), $asset->original_name);

            if ($thumbnailPath !== null && file_exists($thumbnailPath)) {
                $request = $request->attach('thumbnail', file_get_contents($thumbnailPath), basename($thumbnailPath));
            }

            $response = $request->post($environment->baseUrl().'/api/media/upload', [
                'uuid' => $asset->uuid,
                'original_name' => $asset->original_name,
                'mime_type' => $asset->mime_type,
                'type' => $asset->type->value,
                'file_size' => $asset->file_size,
                'alt' => $asset->alt ?? '',
                'visibility' => $asset->visibility->value,
                'dimensions' => $asset->dimensions !== null ? json_encode($asset->dimensions) : null,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Kan omgeving [{$environment->name}] niet bereiken voor media-upload: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new RuntimeException("Media-upload naar [{$environment->name}] mislukt (HTTP {$response->status()}): {$response->body()}");
        }

        $id = (int) ($response->json('id') ?? 0);
        if ($id <= 0) {
            throw new RuntimeException("Media-upload naar [{$environment->name}] gaf geen bruikbare ID terug.");
        }

        return $id;
    }
}
