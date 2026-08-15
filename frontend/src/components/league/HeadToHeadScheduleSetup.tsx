import { useEffect, useRef, useState } from 'react';
import type { Matchday } from '../../types/formation';
import { useTranslation } from '../../i18n';
import { formatDate } from '../../utils/formatters';

export function HeadToHeadScheduleSetup({
  matchdays,
  participantCount,
  maximumParticipants,
  canInitialize,
  isPending,
  error,
  onConfirm,
}: {
  matchdays: Matchday[];
  participantCount: number;
  maximumParticipants: number | null;
  canInitialize: boolean;
  isPending: boolean;
  error: string | null;
  onConfirm: (matchdayId: number) => void;
}) {
  const { language, t } = useTranslation();
  const [selectedId, setSelectedId] = useState<number | null>(matchdays[0]?.id ?? null);
  const [confirming, setConfirming] = useState(false);
  const dialogRef = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    if (selectedId === null && matchdays[0]) setSelectedId(matchdays[0].id);
  }, [matchdays, selectedId]);
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    if (confirming && !dialog.open) dialog.showModal();
    if (!confirming && dialog.open) dialog.close();
  }, [confirming]);

  const selected = matchdays.find((matchday) => matchday.id === selectedId) ?? null;
  const matchdayLabel = (matchday: Matchday) =>
    `${matchday.name || t('formation.matchdayNumber', { number: matchday.number })} — ${formatDate(matchday.starts_at, t('leagueDetail.notAvailable'), language)}`;

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h1 className="text-3xl font-bold text-white">{t('h2h.title')}</h1>
      <p className="mt-2 text-slate-300">{t('h2h.notStarted')}</p>
      <dl className="mt-6 grid gap-4 sm:grid-cols-2">
        <div>
          <dt className="text-sm text-slate-400">{t('h2h.currentParticipants')}</dt>
          <dd className="mt-1 text-xl font-semibold text-white">{participantCount}</dd>
        </div>
        <div>
          <dt className="text-sm text-slate-400">{t('h2h.maximumParticipants')}</dt>
          <dd className="mt-1 text-xl font-semibold text-white">
            {maximumParticipants ?? t('leagueDetail.notAvailable')}
          </dd>
        </div>
      </dl>
      <div className="mt-5 rounded-xl border border-sky-400/30 bg-sky-950/30 p-4 text-sm text-sky-100">
        <p>{t('h2h.minimumTeams')}</p>
        <p className="mt-1">{t('h2h.maximumNotRequired')}</p>
      </div>
      <label className="mt-5 block text-sm font-medium text-slate-200" htmlFor="start-matchday">
        {t('h2h.startingMatchday')}
      </label>
      <select
        className="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
        disabled={!canInitialize || isPending || matchdays.length === 0}
        id="start-matchday"
        onChange={(event) => setSelectedId(Number(event.target.value))}
        value={selectedId ?? ''}
      >
        {matchdays.map((matchday) => (
          <option key={matchday.id} value={matchday.id}>
            {matchdayLabel(matchday)}
          </option>
        ))}
      </select>
      {matchdays.length === 0 ? (
        <p className="mt-2 text-sm text-amber-200">{t('h2h.noFutureMatchdays')}</p>
      ) : null}
      <div
        className="mt-5 rounded-xl border border-amber-400/40 bg-amber-950/30 p-4"
        id="schedule-warning"
      >
        <p className="font-semibold text-amber-100">{t('h2h.irreversible')}</p>
        <p className="mt-1 text-sm text-amber-100">{t('h2h.participantsBlocked')}</p>
      </div>
      {!canInitialize ? (
        <p className="mt-4 text-sm text-slate-300">{t('h2h.unauthorized')}</p>
      ) : null}
      {error ? (
        <p className="mt-4 text-sm text-red-200" role="alert">
          {error}
        </p>
      ) : null}
      <button
        aria-describedby="schedule-warning"
        className="mt-5 rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
        disabled={!canInitialize || !selected || isPending}
        onClick={() => setConfirming(true)}
        type="button"
      >
        {t('h2h.startChampionship')}
      </button>

      <dialog
        className="m-auto w-[min(92vw,34rem)] rounded-2xl border border-slate-700 bg-slate-900 p-0 text-white backdrop:bg-slate-950/80"
        onCancel={() => setConfirming(false)}
        ref={dialogRef}
      >
        <div className="p-6">
          <h2 className="text-2xl font-semibold">{t('h2h.confirmStart')}</h2>
          <p className="mt-4 text-slate-300">
            {t('h2h.confirmParticipants', { count: participantCount })}
          </p>
          <p className="mt-2 text-slate-300">
            {t('h2h.confirmMatchday', { matchday: selected ? matchdayLabel(selected) : '' })}
          </p>
          <p className="mt-4 text-amber-100">{t('h2h.participantsAndInvitationsBlocked')}</p>
          <p className="mt-2 text-amber-100">{t('h2h.cannotRegenerate')}</p>
          <div className="mt-6 flex flex-wrap justify-end gap-3">
            <button
              className="rounded-lg border border-slate-600 px-4 py-2"
              disabled={isPending}
              onClick={() => setConfirming(false)}
              type="button"
            >
              {t('common.cancel')}
            </button>
            <button
              autoFocus
              className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
              disabled={!selected || isPending}
              onClick={() => selected && onConfirm(selected.id)}
              type="button"
            >
              {isPending ? t('common.loading') : t('h2h.confirmStart')}
            </button>
          </div>
        </div>
      </dialog>
    </section>
  );
}
