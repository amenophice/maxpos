"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { formatInTimeZone } from "date-fns-tz";
import {
  ChevronDown,
  ChevronRight,
  ChevronLeft,
  ChevronRightIcon,
  Receipt,
  Search,
  X,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import { formatLei } from "@/lib/money";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface ReceiptItem {
  id: string;
  name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  unit: string;
  vat_rate: number;
}

interface ReceiptPayment {
  id: string;
  method: string;
  amount: string;
}

interface ReceiptData {
  id: string;
  number: string;
  created_at: string;
  issued_at: string;
  total: number;
  subtotal: number;
  vat_total: number;
  discount_total: number;
  status: string;
  items: ReceiptItem[];
  payments: ReceiptPayment[];
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function ReceiptsPage() {
  const t = useTranslations("receipts");

  // Filters — local inputs
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [search, setSearch] = useState("");

  // Applied filters (used as query keys to trigger refetch)
  const [appliedFilters, setAppliedFilters] = useState({
    dateFrom: "",
    dateTo: "",
    search: "",
  });
  const [page, setPage] = useState(1);

  // UI
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [sheetReceipt, setSheetReceipt] = useState<ReceiptData | null>(null);

  const { data, isLoading: loading } = useQuery({
    queryKey: ["receipts", page, appliedFilters],
    queryFn: async () => {
      const params = new URLSearchParams();
      params.set("page", String(page));
      params.set("per_page", "20");
      if (appliedFilters.dateFrom) params.set("date_from", appliedFilters.dateFrom);
      if (appliedFilters.dateTo) params.set("date_to", appliedFilters.dateTo);
      if (appliedFilters.search.trim()) params.set("search", appliedFilters.search.trim());

      const res = await api.get(`/pos/receipts?${params.toString()}`);
      return res.data as { data: ReceiptData[]; meta: PaginationMeta };
    },
  });

  const receipts = data?.data ?? [];
  const meta = data?.meta ?? null;

  const handleSearch = () => {
    setPage(1);
    setAppliedFilters({ dateFrom, dateTo, search });
  };

  const handleReset = () => {
    setDateFrom("");
    setDateTo("");
    setSearch("");
    setPage(1);
    setAppliedFilters({ dateFrom: "", dateTo: "", search: "" });
  };

  const toggleExpanded = (id: string) => {
    setExpandedId((prev) => (prev === id ? null : id));
  };

  const formatDate = (iso: string) =>
    formatInTimeZone(new Date(iso), "Europe/Bucharest", "dd.MM.yyyy HH:mm");

  const paymentLabel = (method: string): string => {
    const key = `paymentMethod.${method}` as const;
    try {
      return t(key);
    } catch {
      return method;
    }
  };

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Receipt className="h-6 w-6 text-primary" />
        <h1 className="font-serif text-3xl font-bold">{t("title")}</h1>
        {meta && (
          <Badge variant="secondary" className="ml-1">
            {t("totalCount", { count: meta.total })}
          </Badge>
        )}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-4">
        <div className="space-y-1">
          <label className="text-sm font-medium text-muted-foreground">
            {t("filters.dateFrom")}
          </label>
          <Input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            className="w-40"
          />
        </div>
        <div className="space-y-1">
          <label className="text-sm font-medium text-muted-foreground">
            {t("filters.dateTo")}
          </label>
          <Input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="w-40"
          />
        </div>
        <div className="space-y-1">
          <label className="text-sm font-medium text-muted-foreground">
            &nbsp;
          </label>
          <div className="relative">
            <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="text"
              placeholder={t("filters.search")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleSearch()}
              className="w-56 pl-8"
            />
          </div>
        </div>
        <Button onClick={handleSearch}>
          <Search className="h-4 w-4" />
          {t("filters.submit")}
        </Button>
        <Button variant="ghost" onClick={handleReset}>
          <X className="h-4 w-4" />
          {t("filters.reset")}
        </Button>
      </div>

      {/* Table */}
      <div className="overflow-hidden rounded-lg border bg-card">
        {/* Header row */}
        <div className="hidden border-b bg-muted/50 px-4 py-3 text-sm font-medium text-muted-foreground sm:grid sm:grid-cols-[3rem_1fr_1fr_0.7fr_1fr_0.8fr_6rem]">
          <span />
          <span>{t("columns.number")}</span>
          <span>{t("columns.date")}</span>
          <span>{t("columns.items")}</span>
          <span className="text-right">{t("columns.total")}</span>
          <span className="text-center">{t("columns.status")}</span>
          <span className="text-right">{t("columns.actions")}</span>
        </div>

        {/* Loading skeleton */}
        {loading &&
          Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex items-center gap-4 border-b px-4 py-4">
              <Skeleton className="h-4 w-4" />
              <Skeleton className="h-4 w-16" />
              <Skeleton className="h-4 w-32" />
              <Skeleton className="h-4 w-8" />
              <Skeleton className="ml-auto h-4 w-20" />
              <Skeleton className="h-5 w-16" />
              <Skeleton className="h-8 w-16" />
            </div>
          ))}

        {/* Empty state */}
        {!loading && receipts.length === 0 && (
          <div className="flex flex-col items-center justify-center gap-2 py-16 text-muted-foreground">
            <Receipt className="h-10 w-10" />
            <p>{t("emptyState")}</p>
          </div>
        )}

        {/* Data rows */}
        {!loading &&
          receipts.map((receipt) => (
            <div key={receipt.id} className="border-b last:border-b-0">
              {/* Main row */}
              <div
                className="grid cursor-pointer grid-cols-[3rem_1fr_1fr_0.7fr_1fr_0.8fr_6rem] items-center px-4 py-3 transition-colors hover:bg-muted/30 max-sm:grid-cols-[2rem_1fr_auto] max-sm:gap-1"
                onClick={() => toggleExpanded(receipt.id)}
              >
                <span className="text-muted-foreground">
                  {expandedId === receipt.id ? (
                    <ChevronDown className="h-4 w-4" />
                  ) : (
                    <ChevronRight className="h-4 w-4" />
                  )}
                </span>
                <span className="font-medium">#{receipt.number}</span>
                <span className="text-sm text-muted-foreground max-sm:hidden">
                  {formatDate(receipt.issued_at || receipt.created_at)}
                </span>
                <span className="text-sm text-muted-foreground max-sm:hidden">
                  {receipt.items.length}
                </span>
                <span className="text-right font-medium max-sm:hidden">
                  {formatLei(receipt.total)}
                </span>
                <span className="text-center max-sm:hidden">
                  <Badge
                    variant={
                      receipt.status === "completed" ? "default" : "destructive"
                    }
                    className={
                      receipt.status === "completed"
                        ? "bg-emerald-600 text-white hover:bg-emerald-600"
                        : ""
                    }
                  >
                    {t(`status.${receipt.status}` as "status.completed" | "status.voided")}
                  </Badge>
                </span>
                <span className="text-right max-sm:hidden">
                  <Button
                    variant="outline"
                    size="xs"
                    onClick={(e) => {
                      e.stopPropagation();
                      setSheetReceipt(receipt);
                    }}
                  >
                    {t("details")}
                  </Button>
                </span>

                {/* Mobile: compact info */}
                <span className="col-span-2 flex items-center gap-2 text-sm sm:hidden">
                  <span className="text-muted-foreground">
                    {formatDate(receipt.issued_at || receipt.created_at)}
                  </span>
                  <span className="font-medium">{formatLei(receipt.total)}</span>
                  <Badge
                    variant={
                      receipt.status === "completed" ? "default" : "destructive"
                    }
                    className={
                      receipt.status === "completed"
                        ? "bg-emerald-600 text-white hover:bg-emerald-600"
                        : ""
                    }
                  >
                    {t(`status.${receipt.status}` as "status.completed" | "status.voided")}
                  </Badge>
                </span>
              </div>

              {/* Expanded details (inline) */}
              {expandedId === receipt.id && (
                <div className="border-t bg-muted/20 px-4 py-4 sm:px-12">
                  <ReceiptDetails receipt={receipt} t={t} paymentLabel={paymentLabel} />
                  <div className="mt-3 sm:hidden">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setSheetReceipt(receipt)}
                    >
                      {t("details")}
                    </Button>
                  </div>
                </div>
              )}
            </div>
          ))}
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between">
          <Button
            variant="outline"
            size="sm"
            disabled={meta.current_page <= 1}
            onClick={() => setPage((p) => p - 1)}
          >
            <ChevronLeft className="h-4 w-4" />
            {t("pagination.previous")}
          </Button>
          <span className="text-sm text-muted-foreground">
            {t("pagination.page", {
              current: meta.current_page,
              last: meta.last_page,
            })}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={meta.current_page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            {t("pagination.next")}
            <ChevronRightIcon className="h-4 w-4" />
          </Button>
        </div>
      )}

      {/* Sheet (slide-over) for full details */}
      <Sheet
        open={!!sheetReceipt}
        onOpenChange={(open) => !open && setSheetReceipt(null)}
      >
        <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-md">
          <SheetHeader>
            <SheetTitle>
              {sheetReceipt &&
                t("detailsTitle", { number: sheetReceipt.number })}
            </SheetTitle>
            <SheetDescription>
              {sheetReceipt &&
                formatDate(sheetReceipt.issued_at || sheetReceipt.created_at)}
            </SheetDescription>
          </SheetHeader>
          {sheetReceipt && (
            <div className="space-y-6 px-4 pb-6">
              {/* Status */}
              <Badge
                variant={
                  sheetReceipt.status === "completed" ? "default" : "destructive"
                }
                className={
                  sheetReceipt.status === "completed"
                    ? "bg-emerald-600 text-white hover:bg-emerald-600"
                    : ""
                }
              >
                {t(`status.${sheetReceipt.status}` as "status.completed" | "status.voided")}
              </Badge>

              {/* Items table */}
              <div>
                <h3 className="mb-2 text-sm font-semibold">
                  {t("columns.items")} ({sheetReceipt.items.length})
                </h3>
                <div className="divide-y rounded-md border">
                  {sheetReceipt.items.map((item) => (
                    <div
                      key={item.id}
                      className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-medium">{item.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {item.quantity} {item.unit} &times;{" "}
                          {formatLei(item.unit_price)}
                        </p>
                      </div>
                      <span className="shrink-0 font-medium">
                        {formatLei(item.line_total)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Payments */}
              <div>
                <h3 className="mb-2 text-sm font-semibold">{t("payments")}</h3>
                <div className="divide-y rounded-md border">
                  {sheetReceipt.payments.map((payment) => (
                    <div
                      key={payment.id}
                      className="flex items-center justify-between px-3 py-2 text-sm"
                    >
                      <span>{paymentLabel(payment.method)}</span>
                      <span className="font-medium">
                        {formatLei(payment.amount)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Totals */}
              <div className="space-y-1 rounded-md border p-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">{t("subtotal")}</span>
                  <span>{formatLei(sheetReceipt.subtotal)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">{t("vat")}</span>
                  <span>{formatLei(sheetReceipt.vat_total)}</span>
                </div>
                <div className="flex justify-between border-t pt-1 font-semibold">
                  <span>{t("total")}</span>
                  <span>{formatLei(sheetReceipt.total)}</span>
                </div>
              </div>
            </div>
          )}
        </SheetContent>
      </Sheet>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Inline expanded details (reused in row expand)                     */
/* ------------------------------------------------------------------ */

function ReceiptDetails({
  receipt,
  t,
  paymentLabel,
}: {
  receipt: ReceiptData;
  t: ReturnType<typeof useTranslations<"receipts">>;
  paymentLabel: (m: string) => string;
}) {
  return (
    <div className="space-y-3 text-sm">
      {/* Items */}
      <table className="w-full text-left">
        <thead>
          <tr className="border-b text-xs text-muted-foreground">
            <th className="pb-1 font-medium">{t("itemName")}</th>
            <th className="pb-1 text-center font-medium">{t("itemQty")}</th>
            <th className="pb-1 text-right font-medium">{t("itemPrice")}</th>
            <th className="pb-1 text-right font-medium">{t("itemTotal")}</th>
          </tr>
        </thead>
        <tbody>
          {receipt.items.map((item) => (
            <tr key={item.id} className="border-b last:border-b-0">
              <td className="py-1.5">{item.name}</td>
              <td className="py-1.5 text-center">
                {item.quantity} {item.unit}
              </td>
              <td className="py-1.5 text-right">{formatLei(item.unit_price)}</td>
              <td className="py-1.5 text-right font-medium">
                {formatLei(item.line_total)}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* Payments */}
      <div className="flex flex-wrap gap-3">
        <span className="text-muted-foreground">{t("payments")}:</span>
        {receipt.payments.map((p) => (
          <span key={p.id}>
            {paymentLabel(p.method)} — {formatLei(p.amount)}
          </span>
        ))}
      </div>

      {/* Totals */}
      <div className="flex gap-6 text-xs text-muted-foreground">
        <span>
          {t("subtotal")}: {formatLei(receipt.subtotal)}
        </span>
        <span>
          {t("vat")}: {formatLei(receipt.vat_total)}
        </span>
        <span className="font-semibold text-foreground">
          {t("total")}: {formatLei(receipt.total)}
        </span>
      </div>
    </div>
  );
}
