<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CloseCashSessionRequest;
use App\Http\Requests\Api\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Models\Location;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CashSessionController extends Controller
{
    public function __construct(private readonly ReceiptService $service) {}

    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        $location = Location::findOrFail($request->validated('location_id'));
        $session = $this->service->openCashSession(
            $location,
            $request->user(),
            $request->validated('initial_cash'),
            $request->validated('notes'),
        );

        return response()->json(['data' => new CashSessionResource($session), 'meta' => []], Response::HTTP_CREATED);
    }

    public function close(CloseCashSessionRequest $request, string $id): JsonResponse
    {
        $session = CashSession::findOrFail($id);
        if ($session->user_id !== $request->user()->id) {
            throw new PosException('Doar utilizatorul care a deschis sesiunea o poate închide.', 403);
        }
        $session = $this->service->closeCashSession(
            $session,
            $request->validated('final_cash'),
            $request->validated('notes'),
        );

        return response()->json(['data' => new CashSessionResource($session), 'meta' => []]);
    }

    public function current(Request $request): JsonResponse
    {
        $session = CashSession::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return response()->json([
            'data' => $session ? new CashSessionResource($session) : null,
            'meta' => [],
        ]);
    }
}
