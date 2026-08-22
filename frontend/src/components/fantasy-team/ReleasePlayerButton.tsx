import { useTranslation } from '../../i18n';

export function ReleasePlayerButton({
  isReleasing,
  onRelease,
}: {
  isReleasing: boolean;
  onRelease: () => void;
}) {
  const { t } = useTranslation();
  return (
    <button
      className="rounded-lg border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-100 disabled:opacity-60"
      disabled={isReleasing}
      onClick={onRelease}
      type="button"
    >
      {isReleasing ? t('roster.release.releasing') : t('roster.release.submit')}
    </button>
  );
}