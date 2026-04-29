"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { AxiosError } from "axios";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

interface ChangePasswordForm {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export default function SettingsPage() {
  const t = useTranslations("settings.security");
  const [saving, setSaving] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<ChangePasswordForm>();

  const onSubmit = async (data: ChangePasswordForm) => {
    setSaving(true);
    try {
      await api.put("/auth/password", data);
      toast.success(t("successToast"));
      reset();
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 422) {
        const serverErrors = err.response.data?.errors as
          | Record<string, string[]>
          | undefined;
        if (serverErrors) {
          for (const [field, messages] of Object.entries(serverErrors)) {
            if (field === "current_password" || field === "password" || field === "password_confirmation") {
              setError(field, { message: messages[0] });
            }
          }
        }
      } else if (err instanceof AxiosError && err.response?.data?.meta?.error) {
        toast.error(err.response.data.meta.error as string);
      } else {
        toast.error(
          err instanceof AxiosError
            ? (err.response?.data?.message as string) ?? err.message
            : String(err),
        );
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="mx-auto max-w-xl py-8 px-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("title")}</CardTitle>
          <CardDescription>{t("changePassword")}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="grid gap-4">
            <div className="grid gap-2">
              <Label htmlFor="current_password">{t("currentPassword")}</Label>
              <Input
                id="current_password"
                type="password"
                autoComplete="current-password"
                {...register("current_password", { required: true })}
              />
              {errors.current_password && (
                <p className="text-sm text-destructive">
                  {errors.current_password.message}
                </p>
              )}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">{t("newPassword")}</Label>
              <Input
                id="password"
                type="password"
                autoComplete="new-password"
                {...register("password", { required: true, minLength: 8 })}
              />
              <p className="text-xs text-muted-foreground">{t("newPasswordHint")}</p>
              {errors.password && (
                <p className="text-sm text-destructive">
                  {errors.password.message}
                </p>
              )}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">{t("confirmPassword")}</Label>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                {...register("password_confirmation", { required: true })}
              />
              {errors.password_confirmation && (
                <p className="text-sm text-destructive">
                  {errors.password_confirmation.message}
                </p>
              )}
            </div>

            <Button type="submit" disabled={saving} className="w-full">
              {saving ? t("saving") : t("saveButton")}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
