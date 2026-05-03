<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceiptResource;
use App\Models\Article;
use App\Models\Gestiune;
use App\Models\Group;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function upsertArticles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'groups' => 'sometimes|array',
            'groups.*.code' => 'required|string|max:16',
            'groups.*.name' => 'required|string|max:255',

            'gestiuni' => 'sometimes|array',
            'gestiuni.*.code' => 'required|string|max:4',
            'gestiuni.*.name' => 'required|string|max:255',
            'gestiuni.*.location_id' => 'sometimes|nullable|uuid|exists:locations,id',
            'gestiuni.*.type' => 'sometimes|string|in:global-valoric,cantitativ-valoric',

            'articles' => 'sometimes|array',
            'articles.*.sku' => 'required|string|max:255',
            'articles.*.name' => 'required|string|max:255',
            'articles.*.price' => 'required|numeric|min:0',
            'articles.*.vat_rate' => 'sometimes|numeric|min:0|max:100',
            'articles.*.unit' => 'sometimes|string|max:10',
            'articles.*.group_saga_cod' => 'sometimes|nullable|string|max:16',
            'articles.*.gestiune_saga_cod' => 'sometimes|nullable|string|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        if (! $tenantId) {
            return response()->json([
                'errors' => ['tenant' => ['Sync requires a tenant-scoped user, not super-admin.']],
            ], 403);
        }

        $counts = ['groups' => 0, 'gestiuni' => 0, 'articles' => 0];
        $created = 0;
        $updated = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($request, $tenantId, &$counts, &$created, &$updated, &$errors) {
                // Upsert groups
                foreach ($request->input('groups', []) as $row) {
                    Group::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'saga_cod' => $row['code']],
                        ['name' => $row['name']],
                    );
                    $counts['groups']++;
                }

                // Upsert gestiuni
                foreach ($request->input('gestiuni', []) as $row) {
                    $attrs = [
                        'name' => $row['name'],
                        'location_id' => $row['location_id'] ?? \App\Models\Location::withoutGlobalScopes()->where('tenant_id', $tenantId)->value('id'),
                    ];
                    if (isset($row['type'])) {
                        $attrs['type'] = $row['type'];
                    }

                    Gestiune::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'saga_cod' => $row['code']],
                        $attrs,
                    );
                    $counts['gestiuni']++;
                }

                // Upsert articles
                foreach ($request->input('articles', []) as $i => $row) {
                    try {
                        $groupId = null;
                        if (! empty($row['group_saga_cod'])) {
                            $groupId = Group::withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)
                                ->where('saga_cod', $row['group_saga_cod'])
                                ->value('id');
                        }

                        $gestiuneId = null;
                        if (! empty($row['gestiune_saga_cod'])) {
                            $gestiuneId = Gestiune::withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)
                                ->where('saga_cod', $row['gestiune_saga_cod'])
                                ->value('id');
                        }

                        $attrs = [
                            'name' => $row['name'],
                            'price' => $row['price'],
                        ];

                        if (isset($row['vat_rate'])) {
                            $attrs['vat_rate'] = $row['vat_rate'];
                        }
                        if (isset($row['unit'])) {
                            $attrs['unit'] = $row['unit'];
                        }
                        if ($groupId !== null) {
                            $attrs['group_id'] = $groupId;
                        }
                        if ($gestiuneId !== null) {
                            $attrs['default_gestiune_id'] = $gestiuneId;
                        }

                        $article = Article::withoutGlobalScopes()->updateOrCreate(
                            ['tenant_id' => $tenantId, 'sku' => $row['sku']],
                            $attrs,
                        );

                        if ($article->wasRecentlyCreated) {
                            $created++;
                        } else {
                            $updated++;
                        }
                        $counts['articles']++;
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'index' => $i,
                            'sku' => $row['sku'] ?? null,
                            'error' => $e->getMessage(),
                        ];
                        \Illuminate\Support\Facades\Log::error('Sync article failed', [
                            'index' => $i,
                            'sku' => $row['sku'] ?? null,
                            'error' => $e->getMessage(),
                            'tenant_id' => $tenantId,
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Sync transaction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'errors' => ['transaction' => [$e->getMessage()]],
            ], 500);
        }

        $articlesInDb = Article::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        $response = [
            'data' => $counts,
            'meta' => ['message' => 'Sincronizare completă.'],
            'debug' => [
                'received' => count($request->input('articles', [])),
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'articles_in_db' => $articlesInDb,
                'tenant_id' => $tenantId,
                'first' => $request->input('articles.0'),
            ],
        ];

        if ($errors) {
            \Illuminate\Support\Facades\Log::warning('Sync completed with errors', [
                'tenant_id' => $tenantId,
                'total' => count($request->input('articles', [])),
                'created' => $created,
                'updated' => $updated,
                'error_count' => count($errors),
            ]);
        }

        return response()->json($response);
    }

    public function pendingReceipts(): JsonResponse
    {
        $receipts = Receipt::with(['items.gestiune', 'items.article', 'payments', 'customer'])
            ->where('status', 'completed')
            ->whereNull('saga_synced_at')
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => ReceiptResource::collection($receipts),
        ]);
    }

    public function markSynced(string $receiptId): JsonResponse
    {
        $receipt = Receipt::where('id', $receiptId)
            ->where('status', 'completed')
            ->whereNull('saga_synced_at')
            ->firstOrFail();

        $receipt->update(['saga_synced_at' => now()]);

        return response()->json([
            'data' => ['saga_synced_at' => $receipt->fresh()->saga_synced_at->toIso8601String()],
        ]);
    }
}
