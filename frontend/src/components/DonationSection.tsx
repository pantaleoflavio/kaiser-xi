import { Coffee } from 'lucide-react';
import type { FrontendConfig } from '../config/frontend';
import { frontendConfig, getDonationUrl } from '../config/frontend';
import { useTranslation } from '../i18n';

export function DonationSection({ config = frontendConfig }: { config?: FrontendConfig }) {
  const { t } = useTranslation();

  const donationUrl = getDonationUrl(config);

  if (!donationUrl) return null;

  return (
    <section className="mx-auto max-w-3xl px-6 pt-6 text-center" aria-labelledby="donations-title">
      <h2 id="donations-title" className="text-base font-semibold text-theme-text">
        {t('donations.title')}
      </h2>
      <p className="mt-2 text-sm leading-6 text-theme-muted">{t('donations.description')}</p>
      <a
        className="mt-4 inline-flex items-center gap-2 rounded-lg border border-theme-border px-4 py-2 text-sm font-semibold text-theme-text transition hover:border-theme-accent hover:text-theme-accent"
        href={donationUrl}
        target="_blank"
        rel="noopener noreferrer"
      >
        <Coffee aria-hidden="true" size={18} />
        {t('donations.cta')}
      </a>
    </section>
  );
}
