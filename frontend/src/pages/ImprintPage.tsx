import { LegalPage, LegalSection } from '../components/legal/LegalPage';
import { legalConfig } from '../config/legal';
import { useTranslation } from '../i18n';

export function ImprintPage() {
  const { t } = useTranslation();
  const fact = (value: string | null) => value ?? t('legal.pendingValue');
  return (
    <LegalPage title={t('legal.imprintTitle')} updated={t('legal.updated')}>
      <LegalSection title={t('legal.imprint.operatorTitle')}>
        <p>{t('legal.imprint.operatorIntro')}</p>
        <address className="not-italic">
          <strong>{fact(legalConfig.controllerName)}</strong>
          <br />
          {fact(legalConfig.postalAddress)}
        </address>
      </LegalSection>
      <LegalSection title={t('legal.imprint.contactTitle')}>
        <p>{fact(legalConfig.contactEmail)}</p>
      </LegalSection>
      {legalConfig.optionalCommercialDetails && (
        <LegalSection title={t('legal.imprint.additionalTitle')}>
          <p>{legalConfig.optionalCommercialDetails}</p>
        </LegalSection>
      )}
    </LegalPage>
  );
}
