import type { Formation, FormationPlayer } from '../../types/formation';
import { useTranslation } from '../../i18n';

function FormationPlayerRow({ item, slot }: { item: FormationPlayer; slot: 'starter' | 'bench' }) {
  const { t } = useTranslation();

  return (
    <li className="rounded-xl border border-slate-700 bg-slate-950/50 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="font-semibold text-white">{item.player.name}</p>
          <p className="text-sm text-slate-400">{t(`roster.roles.${item.player.role}`)}</p>
        </div>
        <span className="text-sm font-semibold text-emerald-200">
          {t(`results.${slot}`)} #{item.order}
        </span>
      </div>
    </li>
  );
}

export function SubmittedFormationView({ formation }: { formation: Formation }) {
  const { t } = useTranslation();

  return (
    <article className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
        {t('results.submittedFormation')}
      </p>
      <h2 className="mt-1 text-2xl font-semibold text-white">{formation.fantasy_team.name}</h2>
      <p className="mt-1 text-slate-300">
        {t('formation.title')}: {formation.formation_module.name}
      </p>
      <p className="mt-2 text-sm text-emerald-200" role="status">
        {t('formation.submitted')}
      </p>

      <section className="mt-6">
        <h3 className="mb-3 text-lg font-semibold text-white">{t('results.starter')}</h3>
        <ol className="grid gap-3 lg:grid-cols-2">
          {formation.starters.map((item) => (
            <FormationPlayerRow item={item} key={item.fantasy_team_player_id} slot="starter" />
          ))}
        </ol>
      </section>
      <section className="mt-6">
        <h3 className="mb-3 text-lg font-semibold text-white">{t('results.bench')}</h3>
        <ol className="grid gap-3 lg:grid-cols-2">
          {formation.bench.map((item) => (
            <FormationPlayerRow item={item} key={item.fantasy_team_player_id} slot="bench" />
          ))}
        </ol>
      </section>
    </article>
  );
}
