<?php

namespace App\Http\Controllers;

use App\Services\Search\SearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $query = trim((string) $request->query('q', ''));
        $groups = $search->search($query, $request->user());

        return view('search.results', [
            'query' => $query,
            'groups' => $groups,
            'total' => array_sum(array_map(fn (array $group): int => count($group['results']), $groups)),
        ]);
    }

    public function suggestions(Request $request, SearchService $search): JsonResponse
    {
        return response()->json([
            'suggestions' => $search->suggestTerms((string) $request->query('q', ''), $request->user()),
        ]);
    }
}
