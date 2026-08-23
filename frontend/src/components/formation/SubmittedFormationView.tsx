import type { Formation } from '../../types/formation';
import { useTranslation } from '../../i18n';
import { FormationPitch, type PitchPlayer } from './FormationPitch';

export function SubmittedFormationView({ formation }: { formation: Formation }) {
  const { t } = useTranslation();
  const toPitchPlayer = (item: Formation['starters'][number]): PitchPlayer => ({
    id: item.fantasy_team_player_id,
    name: item.player.name,
    role: item.player.role,
    order: item.order,
  });

  return (
    <article className="rounded-2xl border border-theme-border bg-theme-surface/70 p-4 sm:p-6">
      <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
        {t('results.submittedFormation')}
      </p>
      <h2 className="mt-1 text-2xl font-semibold text-theme-text">{formation.fantasy_team.name}</h2>
      <p className="mt-1 text-theme-muted">
        {t('formation.title')}: {formation.formation_module.name}
      </p>
      <p className="mt-2 text-sm text-theme-accent" role="status">
        {t('formation.submitted')}
      </p>

      <div className="mt-6">
        <FormationPitch
          ariaLabel={`${t('results.submittedFormation')} · ${formation.formation_module.name}`}
          bench={formation.bench.map(toPitchPlayer)}
          benchLabel={t('results.bench')}
          mode="readonly"
          players={formation.starters.map(toPitchPlayer)}
        />
      </div>
    </article>
  );
}
