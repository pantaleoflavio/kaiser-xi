import { legalConfig } from '../config/legal';
import { LegalPage, LegalSection } from '../components/legal/LegalPage';
import { useTranslation } from '../i18n';

export function PrivacyPage() {
  const { t } = useTranslation();
  const fact = (value: string | null) => value ?? t('legal.pendingValue');
  const sections = [
    'scope',
    'data',
    'purposes',
    'game',
    'storage',
    'recipients',
    'retention',
    'deletion',
    'rights',
    'decisions',
    'transfers',
  ] as const;
  return (
    <LegalPage title={t('legal.privacyTitle')} updated={t('legal.updated')}>
      <LegalSection title={t('legal.controllerTitle')}>
        <p>{t('legal.controllerIntro')}</p>
        <address className="not-italic">
          <strong>{fact(legalConfig.controllerName)}</strong>
          <br />
          {fact(legalConfig.postalAddress)}
          <br />
          {fact(legalConfig.contactEmail)}
        </address>
      </LegalSection>
      {sections.map((section) => (
        <LegalSection key={section} title={t(`legal.privacy.${section}.title`)}>
          <p>{t(`legal.privacy.${section}.body`)}</p>
          {section === 'recipients' && (
            <ul className="list-disc space-y-1 pl-6">
              <li>
                {t('legal.hosting')}: {fact(legalConfig.hostingProvider)}
              </li>
              <li>
                {t('legal.database')}: {fact(legalConfig.databaseProvider)}
              </li>
              <li>
                {t('legal.email')}: {fact(legalConfig.emailProvider)}
              </li>
            </ul>
          )}
          {section === 'rights' && (
            <p>
              {t('legal.authority')}: {fact(legalConfig.supervisoryAuthority)}
            </p>
          )}
        </LegalSection>
      ))}
    </LegalPage>
  );
}
