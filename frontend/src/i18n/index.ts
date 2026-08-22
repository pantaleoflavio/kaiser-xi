import { useSyncExternalStore } from 'react';
import de from './de.json';
import en from './en.json';
import it from './it.json';

const messages = { en, de, it } as const;
const LANGUAGE_STORAGE_KEY = 'fantameister_language';

export type Language = keyof typeof messages;

type TranslationParams = Record<string, string | number | undefined>;

export const languages: Array<{ code: Language; label: string }> = [
  { code: 'en', label: 'EN' },
  { code: 'de', label: 'DE' },
  { code: 'it', label: 'IT' },
];

function isLanguage(value: string | null): value is Language {
  return Boolean(value && value in messages);
}

function getStoredLanguage(): Language {
  const storedLanguage = localStorage.getItem(LANGUAGE_STORAGE_KEY);
  return isLanguage(storedLanguage) ? storedLanguage : 'en';
}

function getByPath(obj: unknown, path: string): string | undefined {
  return path.split('.').reduce<unknown>((acc, part) => {
    if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[part];
    }

    return undefined;
  }, obj) as string | undefined;
}

function interpolate(message: string, params: TranslationParams = {}) {
  return message.replace(/{{\s*(\w+)\s*}}/g, (_, key: string) => String(params[key] ?? ''));
}

function translate(language: Language, key: string, params?: TranslationParams) {
  const message = getByPath(messages[language], key) ?? getByPath(messages.en, key) ?? key;
  return interpolate(message, params);
}

function subscribe(callback: () => void) {
  window.addEventListener('storage', callback);
  window.addEventListener('fantameister-language-change', callback);

  return () => {
    window.removeEventListener('storage', callback);
    window.removeEventListener('fantameister-language-change', callback);
  };
}

export function setLanguage(language: Language) {
  localStorage.setItem(LANGUAGE_STORAGE_KEY, language);
  window.dispatchEvent(new Event('fantameister-language-change'));
}

export function useTranslation() {
  const language = useSyncExternalStore(subscribe, getStoredLanguage, () => 'en' as Language);

    return {
    language,
    setLanguage,
    t: (key: string, params?: TranslationParams) => translate(language, key, params),
  };
}