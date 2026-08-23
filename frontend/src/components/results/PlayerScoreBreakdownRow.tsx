import type { HistoricalPlayerResult } from '../../types/results';
import { useTranslation } from '../../i18n';

export function PlayerScoreBreakdownRow({ item }: { item: HistoricalPlayerResult }) {
  const { t } = useTranslation();
  const playable =
    item.player_score?.status === 'confirmed' && item.player_score.final_score !== null;
  return (
    <li className="rounded-xl border border-theme-border bg-theme-background/50 p-4">
      <div className="flex flex-wrap justify-between gap-3">
        <div>
          <p className="font-semibold text-theme-text">{item.player.name}</p>
          <p className="text-sm text-theme-muted">{t(`roster.roles.${item.player.role}`)}</p>
        </div>
        <span className="text-sm font-semibold text-theme-accent">
          {t(`results.${item.submitted_slot}`)} #{item.submitted_order}
        </span>
      </div>
      <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-theme-muted">{t('results.importedScore')}</dt>
          <dd className="text-theme-text">
            {playable ? item.player_score?.final_score : t('results.noPlayableScore')}
          </dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('results.effectiveContribution')}</dt>
          <dd className="text-theme-text">{item.effective_contribution ?? '—'}</dd>
        </div>
      </dl>
      {item.used_as_substitute ? (
        <p className="mt-3 text-sm text-theme-accent">
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
        <p className="mt-3 text-xs text-theme-muted">{t('results.realCaptain')}</p>
      ) : null}
      {item.player_score ? (
        <p className="mt-2 text-xs text-theme-muted">
          {[
            item.player_score.goals ? `${t('results.goals')}: ${item.player_score.goals}` : null,
            item.player_score.assists
              ? `${t('results.assists')}: ${item.player_score.assists}`
              : null,
            item.player_score.yellow_cards
              ? `${t('results.yellowCards')}: ${item.player_score.yellow_cards}`
              : null,
            item.player_score.red_cards
              ? `${t('results.redCards')}: ${item.player_score.red_cards}`
              : null,
            item.player_score.clean_sheet ? t('results.cleanSheet') : null,
          ]
            .filter((value) => value !== null)
            .join(' · ')}
        </p>
      ) : null}
    </li>
  );
}
