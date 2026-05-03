import { useTranslations } from "next-intl";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export function Placeholder({ titleKey, bodyKey }: { titleKey: string; bodyKey: string }) {
  const t = useTranslations();
  return (
    <Card>
      <CardHeader>
        <CardTitle className="font-serif text-2xl">{t(titleKey)}</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-muted-foreground">{t(bodyKey)}</p>
      </CardContent>
    </Card>
  );
}
