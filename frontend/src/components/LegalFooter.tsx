import { Link } from 'react-router-dom';
import { useTranslation } from '../i18n';

export function LegalFooter() {
  const { t } = useTranslation();
  return (
    <footer className="border-t border-theme-border bg-theme-surface/70">
      <nav
        aria-label={t('legal.navigationLabel')}
        className="mx-auto flex max-w-5xl flex-wrap justify-center gap-x-6 gap-y-2 px-6 py-5 text-sm"
      >
        <Link className="text-theme-muted hover:text-theme-accent" to="/privacy">
          {t('legal.privacyTitle')}
        </Link>
        <Link className="text-theme-muted hover:text-theme-accent" to="/impressum">
          {t('legal.imprintTitle')}
        </Link>
      </nav>
    </footer>
  );
}
