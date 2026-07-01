<?php

namespace App\Http\Controllers;

use App\Enums\PageVisibility;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, MediaAsset $asset): StreamedResponse
    {
        $this->guardVisibility($asset, $request);

        $path = $request->boolean('thumb') && $asset->thumbnail_path !== null
            ? $asset->thumbnail_path
            : $asset->path;

        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($path), 404);

        return $disk->response(
            $path,
            $asset->original_name,
            ['Content-Type' => $asset->mime_type],
        );
    }

    private function guardVisibility(MediaAsset $asset, Request $request): void
    {
        if ($asset->visibility === PageVisibility::Public) {
            return;
        }

        $user = $request->user();
        abort_unless($user !== null, 403, 'Deze media zijn alleen beschikbaar voor ingelogde leden.');

        if ($asset->visibility === PageVisibility::Restricted) {
            abort_unless($user->can('media.view'), 403);
        }
    }
}
