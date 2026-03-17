import { useTranslation } from "react-i18next";
import { SettingLayout } from "@/layouts";
import ContentSection from "../components/content-section";
import SettingsProvider from "../context/settings-context";
import { DisplayForm } from "./display-form";

export default function SettingsDisplay() {
  const { t } = useTranslation();
  return (
    <SettingsProvider defaultTab="display">
      <SettingLayout title={t("settings.display.title")}>
        <ContentSection
          title={t("settings.display.title")}
          desc={t("settings.display.description")}
        >
          <DisplayForm />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  );
}
