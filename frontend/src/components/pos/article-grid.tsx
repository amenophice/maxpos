"use client";

import { useLiveQuery } from "dexie-react-hooks";
import { useTranslations } from "next-intl";
import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { db, getAllGroups, searchArticles, type ArticleRow } from "@/lib/db";
import { formatLei } from "@/lib/money";
import { usePosStore } from "@/stores/pos-store";

export function ArticleGrid({
  search,
  groupId,
  onGroupChange,
  onAfterAdd,
  syncing,
}: {
  search: string;
  groupId: string | null;
  onGroupChange: (id: string | null) => void;
  onAfterAdd: () => void;
  syncing: boolean;
}) {
  const t = useTranslations("pos");
  const addItem = usePosStore((s) => s.addItem);

  const groups = useLiveQuery(() => (db ? getAllGroups() : Promise.resolve([])), []);
  const articles = useLiveQuery(
    () => (db ? searchArticles(search, groupId) : Promise.resolve([])),
    [search, groupId],
  );

  // Auto-add when search filter narrows to a single match (fast scan flow).
  useEffect(() => {
    if (!search.trim()) return;
    if (articles && articles.length === 1) {
      addItem(articles[0], 1);
      onAfterAdd();
    }
  }, [search, articles, addItem, onAfterAdd]);

  if (!articles || !groups) {
    return (
      <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 gap-2">
        {Array.from({ length: 10 }).map((_, i) => (
          <Skeleton key={i} className="h-32" />
        ))}
      </div>
    );
  }

  if (articles.length === 0 && !syncing) {
    if (!search.trim() && !groupId) {
      return (
        <div className="border border-dashed border-border rounded-md p-8 text-center text-sm">
          <div className="font-medium mb-1">{t("emptyCatalogTitle")}</div>
          <div className="text-muted-foreground">{t("emptyCatalogBody")}</div>
        </div>
      );
    }
    return <div className="text-sm text-muted-foreground">Niciun articol găsit.</div>;
  }

  return (
    <div className="flex flex-col gap-3 min-h-0 flex-1">
      <div className="flex gap-2 overflow-x-auto pb-1">
        <Button
          size="sm"
          variant={groupId === null ? "default" : "outline"}
          onClick={() => onGroupChange(null)}
          className="shrink-0"
        >
          {t("allGroups")}
        </Button>
        {groups.map((g) => (
          <Button
            key={g.id}
            size="sm"
            variant={groupId === g.id ? "default" : "outline"}
            onClick={() => onGroupChange(g.id)}
            className="shrink-0"
          >
            {g.name}
          </Button>
        ))}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2 overflow-y-auto">
        {articles.map((a) => (
          <ArticleCard
            key={a.id}
            article={a}
            onClick={() => {
              addItem(a, 1);
              onAfterAdd();
            }}
          />
        ))}
      </div>
    </div>
  );
}

function ArticleCard({
  article,
  onClick,
}: {
  article: ArticleRow;
  onClick: () => void;
}) {
  return (
    <Card
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onClick();
        }
      }}
      className="h-32 p-3 flex flex-col justify-between gap-2 cursor-pointer hover:border-primary transition-colors focus-visible:ring-2 focus-visible:ring-ring outline-none"
    >
      <div className="text-xs font-medium leading-tight line-clamp-2">{article.name}</div>
      <div className="flex items-baseline justify-between gap-1">
        <span className="font-serif text-lg font-bold text-primary">
          {formatLei(article.price)}
        </span>
        <span className="text-[10px] text-muted-foreground uppercase">{article.unit}</span>
      </div>
    </Card>
  );
}
