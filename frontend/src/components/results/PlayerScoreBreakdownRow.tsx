import type { HistoricalPlayerResult } from '../../types/results';
import { useTranslation } from '../../i18n';

export function PlayerScoreBreakdownRow({ item }: { item: HistoricalPlayerResult }) {
  const { t } = useTranslation();
  const playable =
    item.player_score?.status === 'confirmed' && item.player_score.final_score !== null;
  return (
    <li className="rounded-xl border border-slate-700 bg-slate-950/50 p-4">
      <div className="flex flex-wrap justify-between gap-3">
        <div>
          <p className="font-semibold text-white">{item.player.name}</p>
          <p className="text-sm text-slate-400">{t(`formation.roles.${item.player.role}`)}</p>
        </div>
        <span className="text-sm font-semibold text-emerald-200">
          {t(`results.${item.submitted_slot}`)} #{item.submitted_order}
        </span>
      </div>
      <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-slate-400">{t('results.importedScore')}</dt>
          <dd className="text-white">
            {playable ? item.player_score?.final_score : t('results.noPlayableScore')}
          </dd>
        </div>
        <div>
          <dt className="text-slate-400">{t('results.effectiveContribution')}</dt>
          <dd className="text-white">{item.effective_contribution ?? '—'}</dd>
        </div>
      </dl>
      {item.used_as_substitute ? (
        <p className="mt-3 text-sm text-emerald-300">
          {t('results.enteredFromBench')}
          {item.replaced_player ? ` · ${t('results.replaced')} ${item.replaced_player.name}` : ''}
        </p>
      ) : null}
      {item.replaced_by_player ? (
        <p className="mt-3 text-sm text-amber-200">
          {t('results.replacedBy')}: {item.replaced_by_player.name}
        </p>
      ) : null}
      {item.player_score?.is_real_captain ? (
        <p className="mt-3 text-xs text-slate-400">{t('results.realCaptain')}</p>
      ) : null}
    </li>
  );
}
