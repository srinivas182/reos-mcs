import { ArrowRight, CircleCheck, CircleSlash, Landmark } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export type MoveKey =
    | 'licenses'
    | 'ce_hours'
    | 'certificates'
    | 'contacts'
    | 'posts'
    | 'points'
    | 'practice'
    | 'deals'
    | 'compliance'
    | 'mentor'
    | 'pods'
    | 'approvals';

export type Move = {
    kind: 'brokerage' | 'team';
    from: { id: number; name: string };
    to: { id: number; name: string };
    moves: { key: MoveKey; count: number }[];
    stays: { key: MoveKey; count: number }[];
    ends: { key: MoveKey; count: number }[];
};

/**
 * Before an agent joins another brokerage or team (A14.3): what moves with
 * them, what stays with the old brokerage, and what ends.
 */
export function MoveSummary({ move }: { move: Move }) {
    const { t } = useTranslation();
    const groups = [
        { title: t('moves.movesTitle'), icon: ArrowRight, rows: move.moves },
        {
            title: t('moves.staysTitle', { from: move.from.name }),
            icon: Landmark,
            rows: move.stays,
        },
        { title: t('moves.endsTitle'), icon: CircleSlash, rows: move.ends },
    ].filter((g) => g.rows.length > 0);

    return (
        <section
            aria-labelledby="move-title"
            className="space-y-4 rounded-reos-lg border border-reos-action bg-card p-5"
        >
            <h2 id="move-title" className="text-lg font-semibold">
                {move.kind === 'team'
                    ? t('moves.teamTitle', { to: move.to.name })
                    : t('moves.title', { to: move.to.name })}
            </h2>
            <p className="text-base text-muted-foreground">
                {move.kind === 'team'
                    ? t('moves.teamIntro', { from: move.from.name })
                    : t('moves.intro', {
                          from: move.from.name,
                          to: move.to.name,
                      })}
            </p>
            {groups.map((group) => (
                <div key={group.title} className="space-y-2">
                    <h3 className="flex items-center gap-2 text-base font-semibold">
                        <group.icon aria-hidden className="size-5" />
                        {group.title}
                    </h3>
                    <ul className="space-y-1 text-base">
                        {group.rows.map((row) => (
                            <li
                                key={row.key}
                                className="flex items-center gap-2"
                            >
                                <CircleCheck
                                    aria-hidden
                                    className="size-4 text-muted-foreground"
                                />
                                {t(`moves.keys.${row.key}`, {
                                    count: row.count,
                                })}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </section>
    );
}
