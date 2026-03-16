import { useTranslation } from "react-i18next";
import { SettingLayout } from "@/layouts";
import ContentSection from "../components/content-section";
import SettingsProvider from "../context/settings-context";
import type { ProfileFormValues } from "../data/schema";
import ProfileForm from "./profile-form";

interface Props {
  settings?: Partial<ProfileFormValues>;
}

export default function SettingsProfile({ settings }: Props) {
  const { t } = useTranslation();
  return (
    <SettingsProvider defaultTab="profile">
      <SettingLayout title={t("settings.profile.title")}>
        <ContentSection
          title={t("settings.profile.title")}
          desc={t("settings.profile.description")}
        >
          <ProfileForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  );
}
