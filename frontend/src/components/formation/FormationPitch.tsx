import type { ReactNode } from 'react';
import type { PlayerRoleKey } from '../../types/league';

const roleOrder: PlayerRoleKey[] = ['goalkeeper', 'defender', 'midfielder', 'forward'];

export type PitchPlayer = {
  id: number;
  name: string;
  role: PlayerRoleKey;
  order?: number;
  detail?: ReactNode;
  status?: ReactNode;
};

type FormationPitchProps = {
  players: PitchPlayer[];
  bench?: PitchPlayer[];
  mode: 'editor' | 'readonly';
  ariaLabel: string;
  benchLabel?: string;
  emptyLabel?: string;
  onPlayerClick?: (player: PitchPlayer) => void;
};

function PlayerMarker({
  player,
  interactive,
  onClick,
}: {
  player: PitchPlayer;
  interactive: boolean;
  onClick?: () => void;
}) {
  const content = (
    <>
      <span className="formation-player-name">{player.name}</span>
      {player.detail ? <span className="formation-player-detail">{player.detail}</span> : null}
      {player.status ? <span className="formation-player-status">{player.status}</span> : null}
    </>
  );

  return interactive ? (
    <button
      className="formation-player formation-player-interactive"
      onClick={onClick}
      type="button"
    >
      {content}
    </button>
  ) : (
    <div className="formation-player">{content}</div>
  );
}

export function FormationPitch({
  players,
  bench = [],
  mode,
  ariaLabel,
  benchLabel,
  emptyLabel,
  onPlayerClick,
}: FormationPitchProps) {
  const sortedPlayers = [...players].sort((left, right) => (left.order ?? 0) - (right.order ?? 0));

  return (
    <div className="formation-pitch-wrapper">
      <div aria-label={ariaLabel} className="formation-pitch" role="group">
        <div aria-hidden="true" className="formation-pitch-markings">
          <span className="formation-halfway" />
          <span className="formation-center-circle" />
          <span className="formation-penalty-area formation-penalty-area-top" />
          <span className="formation-goal-area formation-goal-area-top" />
          <span className="formation-penalty-area formation-penalty-area-bottom" />
          <span className="formation-goal-area formation-goal-area-bottom" />
        </div>
        <div className="formation-lines">
          {roleOrder.map((role) => {
            const line = sortedPlayers.filter((player) => player.role === role);
            return (
              <div className="formation-line" data-role={role} key={role}>
                {line.map((player) => (
                  <PlayerMarker
                    interactive={mode === 'editor' && Boolean(onPlayerClick)}
                    key={player.id}
                    onClick={() => onPlayerClick?.(player)}
                    player={player}
                  />
                ))}
                {!line.length && mode === 'editor' && emptyLabel ? (
                  <span className="formation-empty-line">{emptyLabel}</span>
                ) : null}
              </div>
            );
          })}
        </div>
      </div>
      {bench.length ? (
        <section className="formation-bench">
          {benchLabel ? <h4 className="formation-bench-title">{benchLabel}</h4> : null}
          <ol className="formation-bench-list">
            {[...bench]
              .sort((left, right) => (left.order ?? 0) - (right.order ?? 0))
              .map((player, index) => (
                <li className="formation-bench-player" key={player.id}>
                  <span className="formation-bench-order">{player.order ?? index + 1}</span>
                  <PlayerMarker interactive={false} player={player} />
                </li>
              ))}
          </ol>
        </section>
      ) : null}
    </div>
  );
}
