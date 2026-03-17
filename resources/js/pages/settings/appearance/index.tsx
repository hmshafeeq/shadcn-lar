import { useTranslation } from "react-i18next";
import { SettingLayout } from "@/layouts";
import ContentSection from "../components/content-section";
import SettingsProvider from "../context/settings-context";
import type { AppearanceFormValues } from "../data/schema";
import { AppearanceForm } from "./appearance-form";

interface Props {
  settings?: Partial<AppearanceFormValues>;
}

export default function SettingsAppearance({ settings }: Props) {
  const { t } = useTranslation();
  return (
    <SettingsProvider defaultTab="appearance">
      <SettingLayout title={t("settings.appearance.title")}>
        <ContentSection
          title={t("settings.appearance.title")}
          desc={t("settings.appearance.description")}
        >
          <AppearanceForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  );
}
