import { languages, useTranslation } from '../i18n';

export function LanguageSwitcher() {
  const { language, setLanguage, t } = useTranslation();

  return (
    <div
      aria-label={t('common.language')}
      className="flex rounded-md border border-theme-border bg-theme-surface p-1"
      role="group"
    >
      {languages.map((option) => {
        const isSelected = option.code === language;

        return (
          <button
            aria-pressed={isSelected}
            className={`rounded px-2 py-1 text-xs font-semibold transition ${
              isSelected
                ? 'bg-theme-primary text-theme-primary-foreground'
                : 'text-theme-muted hover:bg-theme-muted-surface hover:text-theme-text'
            }`}
            key={option.code}
            onClick={() => setLanguage(option.code)}
            type="button"
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
