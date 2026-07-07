<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cms\PageImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PageImportController extends Controller
{
    public function __invoke(Request $request, PageImportService $importer): JsonResponse
    {
        $payload = $request->validate([
            'page' => ['required', 'array'],
            'page.slug' => ['required', 'string'],
            'page.title' => ['required', 'string'],
            'page.type' => ['required', 'string'],
            'page.visibility' => ['required', 'string'],
            'page.parent_slug' => ['nullable', 'string'],
            'page.template_name' => ['required', 'string'],
            'version' => ['required', 'array'],
            'version.bands' => ['required', 'array'],
            'media_uuid_map' => ['array'],
            'media_uuid_map.*' => ['string', 'uuid'],
        ]);

        try {
            $result = $importer->import($payload);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Onbekende importfout: '.$e->getMessage()], 500);
        }

        return response()->json($result, $result['created'] ? 201 : 200);
    }
}
