import { Coffee } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from '../i18n';
import type { FrontendConfig } from '../config/frontend';
import { frontendConfig, getDonationUrl } from '../config/frontend';

export function LegalFooter({ config = frontendConfig }: { config?: FrontendConfig }) {
  const { t } = useTranslation();
  const donationUrl = getDonationUrl(config);

  return (
    <footer className="border-t border-theme-border bg-theme-surface/70">
      <nav
        aria-label={t('legal.navigationLabel')}
        className="mx-auto flex max-w-5xl flex-wrap justify-center gap-x-6 gap-y-2 px-6 py-5 text-sm"
      >
        <Link className="text-theme-muted hover:text-theme-accent" to="/privacy">
          {t('legal.privacyTitle')}
        </Link>
        {config.impressumEnabled && (
          <Link className="text-theme-muted hover:text-theme-accent" to="/impressum">
            {t('legal.imprintTitle')}
          </Link>
        )}
        {donationUrl && (
          <a
            aria-label={t('donations.cta')}
            className="inline-flex items-center gap-1.5 rounded-full bg-[#ff5f5f] px-3 py-1 font-bold text-white transition hover:bg-[#e95555]"
            href={donationUrl}
            target="_blank"
            rel="noopener noreferrer"
          >
            <Coffee aria-hidden="true" size={16} strokeWidth={2.5} />
            <span>Ko-fi</span>
          </a>
        )}
      </nav>
    </footer>
  );
}
