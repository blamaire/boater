<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Cms\MediaSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vergelijk een lijst media-UUIDs met wat lokaal beschikbaar is en retourneer
 * de UUIDs die ontbreken. Wordt gebruikt door {@see MediaSyncService::probe()}
 * voordat een pagina-push binaries meestuurt.
 */
class MediaProbeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuids' => ['required', 'array'],
            'uuids.*' => ['required', 'string', 'uuid'],
        ]);

        $requested = collect($data['uuids'])->unique()->values();
        $existing = MediaAsset::query()
            ->whereIn('uuid', $requested->all())
            ->pluck('uuid')
            ->all();

        $missing = $requested->reject(fn (string $uuid): bool => in_array($uuid, $existing, true))->values()->all();

        return response()->json(['missing_uuids' => $missing]);
    }
}
