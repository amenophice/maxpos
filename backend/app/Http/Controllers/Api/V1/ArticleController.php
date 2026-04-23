<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Barcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Article::query()->with('barcodes');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }

        if ($request->query('only_active', 'true') === 'true') {
            $query->where('is_active', true);
        }

        $perPage = min((int) $request->query('per_page', 50), 200);
        $articles = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => ArticleResource::collection($articles)->collection,
            'meta' => [
                'current_page' => $articles->currentPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
                'last_page' => $articles->lastPage(),
            ],
        ]);
    }

    public function byBarcode(string $barcode): JsonResponse
    {
        $match = Barcode::with('article.barcodes')->where('barcode', $barcode)->first();

        if ($match === null || $match->article === null) {
            return response()->json([
                'data' => null,
                'meta' => ['message' => 'Niciun articol nu are acest cod de bare.'],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => new ArticleResource($match->article),
            'meta' => ['matched_barcode' => $match->barcode, 'type' => $match->type],
        ]);
    }
}
