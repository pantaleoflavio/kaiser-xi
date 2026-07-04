import { languages, useTranslation } from '../i18n';

export function LanguageSwitcher() {
  const { language, setLanguage, t } = useTranslation();

  return (
    <div aria-label={t('common.language')} className="flex rounded-md border border-slate-700 bg-slate-900 p-1" role="group">
      {languages.map((option) => {
        const isSelected = option.code === language;

        return (
          <button
            aria-pressed={isSelected}
            className={`rounded px-2 py-1 text-xs font-semibold transition ${
              isSelected ? 'bg-emerald-400 text-slate-950' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
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