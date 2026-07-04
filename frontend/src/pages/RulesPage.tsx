import { useTranslation } from '../i18n';

const sectionKeys = ['overview', 'leagues', 'roles', 'teams', 'future'] as const;
const roleKeys = ['commissioner', 'coCommissioner', 'participant'] as const;
const futureKeys = ['squads', 'budget', 'auctions', 'formations', 'matchdays'] as const;

export function RulesPage() {
  const { t } = useTranslation();

  return (
    <section className="space-y-8">
      <div className="rounded-3xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-emerald-950/20">
        <p className="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-300">
          {t('rules.eyebrow')}
        </p>
        <h1 className="mt-4 text-4xl font-bold tracking-tight text-white">{t('rules.title')}</h1>
        <p className="mt-4 max-w-3xl text-lg leading-8 text-slate-300">{t('rules.description')}</p>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {sectionKeys.map((sectionKey) => (
          <article
            className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
            key={sectionKey}
          >
            <h2 className="text-xl font-semibold text-white">
              {t(`rules.sections.${sectionKey}.title`)}
            </h2>
            <p className="mt-3 text-sm leading-6 text-slate-300">
              {t(`rules.sections.${sectionKey}.description`)}
            </p>
          </article>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_1fr]">
        <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
          <h2 className="text-xl font-semibold text-white">{t('rules.roles.title')}</h2>
          <dl className="mt-4 space-y-4">
            {roleKeys.map((roleKey) => (
              <div key={roleKey}>
                <dt className="font-semibold text-emerald-200">
                  {t(`rules.roles.${roleKey}.title`)}
                </dt>
                <dd className="mt-1 text-sm leading-6 text-slate-300">
                  {t(`rules.roles.${roleKey}.description`)}
                </dd>
              </div>
            ))}
          </dl>
        </section>

        <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
          <h2 className="text-xl font-semibold text-white">{t('rules.future.title')}</h2>
          <ul className="mt-4 space-y-3 text-sm leading-6 text-slate-300">
            {futureKeys.map((futureKey) => (
              <li className="flex gap-3" key={futureKey}>
                <span className="mt-2 h-2 w-2 rounded-full bg-emerald-300" />
                <span>{t(`rules.future.items.${futureKey}`)}</span>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </section>
  );
}