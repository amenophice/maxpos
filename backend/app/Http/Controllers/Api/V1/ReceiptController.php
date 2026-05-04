<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddReceiptItemRequest;
use App\Http\Requests\Api\ApplyDiscountRequest;
use App\Http\Requests\Api\CompleteReceiptRequest;
use App\Http\Requests\Api\CreateReceiptRequest;
use App\Http\Requests\Api\UpdateReceiptItemRequest;
use App\Http\Requests\Api\VoidReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Article;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Gestiune;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $service) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = Receipt::with(['items', 'payments'])
            ->whereIn('status', ['completed', 'voided'])
            ->orderByDesc('created_at');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $query->where('number', $request->query('search'));
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => ReceiptResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(CreateReceiptRequest $request): JsonResponse
    {
        $session = CashSession::findOrFail($request->validated('cash_session_id'));
        $customer = $request->validated('customer_id')
            ? Customer::find($request->validated('customer_id'))
            : null;

        $receipt = $this->service->createDraftReceipt($session, $customer);
        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []], Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $receipt = Receipt::with(['items', 'payments'])->findOrFail($id);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []]);
    }

    public function addItem(AddReceiptItemRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $article = Article::findOrFail($request->validated('article_id'));
        $gestiune = $request->validated('gestiune_id')
            ? Gestiune::find($request->validated('gestiune_id'))
            : null;

        $this->service->addItem(
            $receipt,
            $article,
            $request->validated('quantity'),
            $gestiune,
            $request->validated('discount_amount') ?? 0,
        );

        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []], Response::HTTP_CREATED);
    }

    public function updateItem(UpdateReceiptItemRequest $request, string $id, string $itemId): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $item = ReceiptItem::where('receipt_id', $receipt->id)->findOrFail($itemId);

        $this->service->updateItemQuantity($item, $request->validated('quantity'));
        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []]);
    }

    public function removeItem(string $id, string $itemId): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $item = ReceiptItem::where('receipt_id', $receipt->id)->findOrFail($itemId);
        $this->service->removeItem($item);
        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []]);
    }

    public function applyDiscount(ApplyDiscountRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $this->service->applyDiscount($receipt, $request->validated('amount'));
        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []]);
    }

    public function complete(CompleteReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $this->service->completeReceipt($receipt, $request->validated('payments'));
        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []]);
    }

    public function void(VoidReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $this->service->voidReceipt($receipt, $request->validated('reason'));
        $receipt->load(['items', 'payments']);

        return response()->json(['data' => new ReceiptResource($receipt), 'meta' => []]);
    }
}
