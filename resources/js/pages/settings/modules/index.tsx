import { useTranslation } from "react-i18next";
import { SettingLayout } from "@/layouts";
import type { Module } from "@/types";
import ContentSection from "../components/content-section";
import SettingsProvider from "../context/settings-context";
import { ModulesForm } from "./modules-form";

interface Props {
  modules: Module[];
}

export default function SettingsModules({ modules }: Props) {
  const { t } = useTranslation();
  return (
    <SettingsProvider defaultTab="modules">
      <SettingLayout title={t("settings.modules.title")}>
        <ContentSection
          title={t("settings.modules.title")}
          desc={t("settings.modules.description")}
        >
          <ModulesForm modules={modules} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  );
}
