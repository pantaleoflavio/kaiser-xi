import { useTranslation } from '../i18n';

export function LoadingState({ message }: { message?: string }) {
  const { t } = useTranslation();

  return <p className="text-sm text-slate-500">{message ?? t('common.loading')}</p>;
}