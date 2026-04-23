<?php

namespace App\Services;

use App\Exceptions\PosException;
use App\Models\Article;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Gestiune;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ReceiptService — all POS write-paths live here.
 *
 * Money is manipulated as decimal strings via bcmath (scale 2). Quantities use
 * scale 3. Controllers pass floats for convenience but the first thing we do is
 * normalize to a fixed-scale string.
 *
 * Per-location numbering is made gapless by:
 *   1. Locking the row in receipt_number_counters (SELECT ... FOR UPDATE).
 *   2. Incrementing last_number and inserting the receipt in the same transaction.
 *
 * MySQL gives real row-level locking here. SQLite's `lockForUpdate()` is a
 * no-op at the driver level but transactions still serialize writes, so for
 * dev + tests (SQLite :memory:) the effect is equivalent under normal load.
 * In production (MySQL 8) this is truly atomic.
 */
class ReceiptService
{
    private const MONEY_SCALE = 2;

    private const QTY_SCALE = 3;

    public function openCashSession(Location $location, User $user, float|string $initialCash, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($location, $user, $initialCash, $notes) {
            $alreadyOpen = CashSession::where('user_id', $user->id)
                ->where('location_id', $location->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw new PosException('Utilizatorul are deja o sesiune de casă deschisă pentru această locație.', 409);
            }

            return CashSession::create([
                'tenant_id' => $location->tenant_id,
                'location_id' => $location->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'initial_cash' => $this->money($initialCash),
                'status' => 'open',
                'notes' => $notes,
            ]);
        });
    }

    public function closeCashSession(CashSession $session, float|string $finalCash, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($session, $finalCash, $notes) {
            $session->refresh();

            if (! $session->isOpen()) {
                throw new PosException('Sesiunea de casă este deja închisă.', 409);
            }

            $draftCount = Receipt::where('cash_session_id', $session->id)
                ->where('status', 'draft')
                ->count();

            if ($draftCount > 0) {
                throw new PosException('Există bonuri în ciornă pentru această sesiune. Finalizați sau anulați-le înainte de închidere.', 409);
            }

            $cashIn = Payment::whereIn('receipt_id', Receipt::where('cash_session_id', $session->id)
                ->where('status', 'completed')->pluck('id'))
                ->where('method', 'cash')
                ->sum('amount');

            $expected = bcadd($session->initial_cash, (string) $cashIn, self::MONEY_SCALE);

            $session->fill([
                'closed_at' => now(),
                'final_cash' => $this->money($finalCash),
                'expected_cash' => $expected,
                'status' => 'closed',
                'notes' => $notes !== null ? $notes : $session->notes,
            ])->save();

            return $session;
        });
    }

    public function createDraftReceipt(CashSession $session, ?Customer $customer = null): Receipt
    {
        if (! $session->isOpen()) {
            throw new PosException('Nu se poate crea un bon pe o sesiune închisă.', 409);
        }

        return DB::transaction(function () use ($session, $customer) {
            $counter = DB::table('receipt_number_counters')
                ->where('tenant_id', $session->tenant_id)
                ->where('location_id', $session->location_id)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                DB::table('receipt_number_counters')->insert([
                    'tenant_id' => $session->tenant_id,
                    'location_id' => $session->location_id,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $counter = DB::table('receipt_number_counters')
                    ->where('tenant_id', $session->tenant_id)
                    ->where('location_id', $session->location_id)
                    ->lockForUpdate()
                    ->first();
            }

            $nextNumber = $counter->last_number + 1;

            DB::table('receipt_number_counters')
                ->where('tenant_id', $session->tenant_id)
                ->where('location_id', $session->location_id)
                ->update(['last_number' => $nextNumber, 'updated_at' => now()]);

            return Receipt::create([
                'tenant_id' => $session->tenant_id,
                'location_id' => $session->location_id,
                'cash_session_id' => $session->id,
                'number' => $nextNumber,
                'customer_id' => $customer?->id,
                'status' => 'draft',
                'subtotal' => '0.00',
                'vat_total' => '0.00',
                'discount_total' => '0.00',
                'total' => '0.00',
            ]);
        });
    }

    public function addItem(Receipt $receipt, Article $article, float|string $quantity, ?Gestiune $gestiune = null, float|string $discountAmount = 0): ReceiptItem
    {
        $this->assertDraft($receipt);

        return DB::transaction(function () use ($receipt, $article, $quantity, $gestiune, $discountAmount) {
            $gestiune ??= $article->defaultGestiune;

            if ($gestiune === null) {
                throw new PosException('Articolul nu are o gestiune implicită și nu s-a specificat una.', 422);
            }

            $qty = $this->qty($quantity);
            $unitPrice = $this->money($article->price);
            $vatRate = $this->money($article->vat_rate);
            $discount = $this->money($discountAmount);

            // line_subtotal = unit_price * quantity - discount
            // line_vat = line_subtotal * (vat_rate / (100 + vat_rate))   -- VAT-inclusive prices
            $gross = bcmul($unitPrice, $qty, self::MONEY_SCALE);
            $lineSubtotal = bcsub($gross, $discount, self::MONEY_SCALE);
            $lineVat = $this->roundMoney(bcdiv(bcmul($lineSubtotal, $vatRate, 6), bcadd('100', $vatRate, 6), 6));
            $lineTotal = $lineSubtotal;
            $lineNet = bcsub($lineSubtotal, $lineVat, self::MONEY_SCALE);

            $item = ReceiptItem::create([
                'receipt_id' => $receipt->id,
                'article_id' => $article->id,
                'gestiune_id' => $gestiune->id,
                'article_name_snapshot' => $article->name,
                'sku_snapshot' => $article->sku,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'discount_amount' => $discount,
                'line_subtotal' => $lineNet,
                'line_vat' => $lineVat,
                'line_total' => $lineTotal,
            ]);

            $this->recalculateReceipt($receipt);

            return $item;
        });
    }

    public function removeItem(ReceiptItem $item): void
    {
        $receipt = $item->receipt;
        $this->assertDraft($receipt);

        DB::transaction(function () use ($item, $receipt) {
            $item->delete();
            $this->recalculateReceipt($receipt);
        });
    }

    public function updateItemQuantity(ReceiptItem $item, float|string $newQuantity): ReceiptItem
    {
        $receipt = $item->receipt;
        $this->assertDraft($receipt);

        return DB::transaction(function () use ($item, $newQuantity, $receipt) {
            $qty = $this->qty($newQuantity);
            $unitPrice = $this->money($item->unit_price);
            $vatRate = $this->money($item->vat_rate);
            $discount = $this->money($item->discount_amount);

            $gross = bcmul($unitPrice, $qty, self::MONEY_SCALE);
            $lineSubtotal = bcsub($gross, $discount, self::MONEY_SCALE);
            $lineVat = $this->roundMoney(bcdiv(bcmul($lineSubtotal, $vatRate, 6), bcadd('100', $vatRate, 6), 6));
            $lineTotal = $lineSubtotal;
            $lineNet = bcsub($lineSubtotal, $lineVat, self::MONEY_SCALE);

            $item->update([
                'quantity' => $qty,
                'line_subtotal' => $lineNet,
                'line_vat' => $lineVat,
                'line_total' => $lineTotal,
            ]);

            $this->recalculateReceipt($receipt);

            return $item->refresh();
        });
    }

    public function applyDiscount(Receipt $receipt, float|string $amount): Receipt
    {
        $this->assertDraft($receipt);

        return DB::transaction(function () use ($receipt, $amount) {
            $receipt->discount_total = $this->money($amount);
            $receipt->save();
            $this->recalculateReceipt($receipt);

            return $receipt->refresh();
        });
    }

    public function completeReceipt(Receipt $receipt, array $paymentsPayload): Receipt
    {
        $this->assertDraft($receipt);

        if (empty($paymentsPayload)) {
            throw new PosException('Bonul necesită cel puțin o metodă de plată.', 422);
        }

        return DB::transaction(function () use ($receipt, $paymentsPayload) {
            $receipt->load('items');

            if ($receipt->items->isEmpty()) {
                throw new PosException('Bonul nu are nicio linie.', 422);
            }

            $paid = '0.00';
            foreach ($paymentsPayload as $p) {
                $paid = bcadd($paid, $this->money($p['amount']), self::MONEY_SCALE);
            }

            if (bccomp($paid, $this->money($receipt->total), self::MONEY_SCALE) !== 0) {
                throw new PosException(
                    "Suma plăților ({$paid}) nu corespunde totalului bonului ({$receipt->total}).",
                    422
                );
            }

            foreach ($receipt->items as $item) {
                $this->adjustStock($item->article_id, $item->gestiune_id, $receipt->tenant_id, bcmul('-1', $item->quantity, self::QTY_SCALE));
            }

            foreach ($paymentsPayload as $p) {
                Payment::create([
                    'receipt_id' => $receipt->id,
                    'method' => $p['method'],
                    'amount' => $this->money($p['amount']),
                    'reference' => $p['reference'] ?? null,
                    'created_at' => now(),
                ]);
            }

            $receipt->status = 'completed';
            $receipt->save();

            return $receipt->refresh();
        });
    }

    public function voidReceipt(Receipt $receipt, string $reason): Receipt
    {
        if ($receipt->fiscal_printed_at !== null) {
            throw new PosException('Bonul a fost deja tipărit fiscal și nu mai poate fi anulat din POS. Folosiți stornare.', 409);
        }

        if ($receipt->isVoided()) {
            throw new PosException('Bonul este deja anulat.', 409);
        }

        return DB::transaction(function () use ($receipt, $reason) {
            if ($receipt->isCompleted()) {
                $receipt->load('items');
                foreach ($receipt->items as $item) {
                    $this->adjustStock($item->article_id, $item->gestiune_id, $receipt->tenant_id, $item->quantity);
                }
            }

            $receipt->status = 'voided';
            $receipt->voided_at = now();
            $receipt->void_reason = $reason;
            $receipt->save();

            return $receipt->refresh();
        });
    }

    private function recalculateReceipt(Receipt $receipt): void
    {
        $receipt->refresh();
        $items = $receipt->items()->get();

        $subtotal = '0.00';
        $vat = '0.00';
        foreach ($items as $i) {
            $subtotal = bcadd($subtotal, $i->line_total, self::MONEY_SCALE);
            $vat = bcadd($vat, $i->line_vat, self::MONEY_SCALE);
        }

        $discount = $this->money($receipt->discount_total);
        $total = bcsub($subtotal, $discount, self::MONEY_SCALE);

        $receipt->fill([
            'subtotal' => $subtotal,
            'vat_total' => $vat,
            'total' => $total,
        ])->save();
    }

    private function adjustStock(string $articleId, string $gestiuneId, string $tenantId, string $delta): void
    {
        $row = StockLevel::where('article_id', $articleId)
            ->where('gestiune_id', $gestiuneId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            StockLevel::create([
                'tenant_id' => $tenantId,
                'article_id' => $articleId,
                'gestiune_id' => $gestiuneId,
                'quantity' => bcadd('0.000', $delta, self::QTY_SCALE),
            ]);

            return;
        }

        $row->quantity = bcadd((string) $row->quantity, $delta, self::QTY_SCALE);
        $row->save();
    }

    private function assertDraft(Receipt $receipt): void
    {
        if (! $receipt->isDraft()) {
            throw new PosException('Operația este permisă doar pe bonuri în ciornă.', 409);
        }
    }

    private function money(float|string|int $value): string
    {
        return bcadd('0', (string) $value, self::MONEY_SCALE);
    }

    private function qty(float|string|int $value): string
    {
        return bcadd('0', (string) $value, self::QTY_SCALE);
    }

    /**
     * Half-away-from-zero rounding to MONEY_SCALE. bcdiv/bcmul truncate — POS
     * receipts need proper cent rounding so line VAT totals add up cleanly.
     */
    private function roundMoney(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $abs = ltrim($value, '-');
        $rounded = bcadd($abs, '0.005', self::MONEY_SCALE);

        return ($negative ? '-' : '').$rounded;
    }
}
