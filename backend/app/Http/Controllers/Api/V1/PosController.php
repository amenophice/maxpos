<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PosCheckoutRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\GroupResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Article;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Gestiune;
use App\Models\Group;
use App\Models\Location;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PosController extends Controller
{
    public function __construct(private readonly ReceiptService $service) {}

    /**
     * One-shot bootstrap: everything the POS UI needs for offline use in a
     * single payload. ?since=ISO8601 returns only rows updated after that
     * timestamp (incremental sync); omit for a full sync.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $sinceDate = $since ? \Illuminate\Support\Carbon::parse($since) : null;

        $articlesQuery = Article::query()
            ->with(['barcodes', 'stockLevels'])
            ->where('is_active', true);
        $groupsQuery = Group::query();
        $customersQuery = Customer::query();

        if ($sinceDate) {
            $articlesQuery->where('updated_at', '>', $sinceDate);
            $groupsQuery->where('updated_at', '>', $sinceDate);
            $customersQuery->where('updated_at', '>', $sinceDate);
        }

        return response()->json([
            'data' => [
                'articles' => ArticleResource::collection($articlesQuery->orderBy('name')->get()),
                'groups' => GroupResource::collection($groupsQuery->orderBy('display_order')->get()),
                'customers' => CustomerResource::collection($customersQuery->orderBy('name')->get()),
                'gestiuni' => Gestiune::query()->orderBy('name')->get()->map(fn ($g) => [
                    'id' => $g->id,
                    'location_id' => $g->location_id,
                    'name' => $g->name,
                    'type' => $g->type,
                ]),
            ],
            'meta' => [
                'server_time' => now()->toIso8601String(),
                'since' => $sinceDate?->toIso8601String(),
                // Union of all locations' configured prefixes for this tenant.
                // Frontend's scale-barcode parser reads these to decide which
                // 13-digit codes are scale-printed weight tickets.
                'scale_barcode_prefixes' => array_values(array_unique(array_merge(
                    ...Location::query()->get()->map(fn ($l) => $l->effectiveScalePrefixes())->all()
                ))) ?: Location::DEFAULT_SCALE_PREFIXES,
            ],
        ]);
    }

    /**
     * Single-transaction checkout. Replaces the 3-call
     * POST /receipts → POST /items → POST /complete dance so offline retries
     * are atomic. `client_local_id` provides idempotency: a second call with
     * the same local id for the same tenant returns the existing receipt
     * without creating a new one.
     */
    public function checkout(PosCheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $session = CashSession::findOrFail($data['cash_session_id']);

        // Idempotent retry: if a prior successful call already persisted this
        // local id, short-circuit and return the existing receipt.
        if (! empty($data['client_local_id'])) {
            $existing = Receipt::where('tenant_id', $session->tenant_id)
                ->where('client_local_id', $data['client_local_id'])
                ->first();
            if ($existing) {
                $existing->load(['items', 'payments']);

                return response()->json([
                    'data' => new ReceiptResource($existing),
                    'meta' => ['idempotent_replay' => true],
                ]);
            }
        }

        $customer = isset($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        $receipt = DB::transaction(function () use ($session, $customer, $data) {
            $receipt = $this->service->createDraftReceipt($session, $customer);

            if (! empty($data['client_local_id'])) {
                $receipt->client_local_id = $data['client_local_id'];
                $receipt->save();
            }

            foreach ($data['items'] as $row) {
                $article = Article::findOrFail($row['article_id']);
                $gestiune = isset($row['gestiune_id']) ? Gestiune::find($row['gestiune_id']) : null;
                $this->service->addItem(
                    $receipt,
                    $article,
                    $row['quantity'],
                    $gestiune,
                    $row['discount_amount'] ?? 0
                );
            }

            if (isset($data['receipt_discount']) && $data['receipt_discount'] > 0) {
                $this->service->applyDiscount($receipt->refresh(), $data['receipt_discount']);
            }

            $this->service->completeReceipt($receipt->refresh(), $data['payments']);

            return $receipt->refresh();
        });

        $receipt->load(['items', 'payments']);

        return response()->json([
            'data' => new ReceiptResource($receipt),
            'meta' => ['idempotent_replay' => false],
        ], Response::HTTP_CREATED);
    }
}
