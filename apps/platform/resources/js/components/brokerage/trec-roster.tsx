import { Send, Users } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import type { TrecRosterData } from './types';

/**
 * Bring in your agents (A15.1b, broker journey screen 3): every agent TREC
 * lists under the brokerage's license, with Invite all. TREC publishes no
 * emails or phones, so the broker adds them for agents not on REOS.
 */
export function TrecRoster({
    roster: initial,
    routes,
}: {
    roster: TrecRosterData;
    routes: { contacts: string; invite: string };
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [roster, setRoster] = useState(initial);
    const [lines, setLines] = useState('');
    const [adding, setAdding] = useState(false);
    const [note, setNote] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const c = roster.counts;
    const ready = c.on_reos + c.contact;
    const day = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
            new Date(`${iso}T12:00:00`),
        );

    const run = async (work: () => Promise<void>) => {
        setBusy(true);
        setError(null);
        try {
            await work();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <section
            aria-labelledby={`${ids}-title`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 id={`${ids}-title`} className="text-lg font-semibold">
                        {t('brokerage.roster.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.roster.purpose')}
                    </p>
                    <p className="text-base">
                        {t('brokerage.roster.counts', {
                            sponsored: c.sponsored,
                            member: c.member,
                            invited: c.invited,
                            ready,
                            needs: c.needs_contact,
                        })}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        aria-expanded={adding}
                        onClick={() => setAdding((now) => !now)}
                    >
                        {t('brokerage.roster.addContacts')}
                    </Button>
                    <Button
                        disabled={busy || ready === 0}
                        onClick={() =>
                            void run(async () => {
                                const r = (
                                    await postJson<{
                                        data: {
                                            sent: number;
                                            needs_contact: number;
                                            roster: TrecRosterData;
                                        };
                                    }>(routes.invite)
                                ).data;
                                setRoster(r.roster);
                                setNote(
                                    [
                                        t('brokerage.roster.sent', {
                                            count: r.sent,
                                        }),
                                        r.needs_contact > 0
                                            ? t('brokerage.roster.stillNeeds', {
                                                  count: r.needs_contact,
                                              })
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' '),
                                );
                            })
                        }
                    >
                        <Send aria-hidden />
                        {ready === 0
                            ? t('brokerage.roster.inviteAll')
                            : t('brokerage.roster.inviteAllCount', {
                                  count: ready,
                              })}
                    </Button>
                </div>
            </header>

            {adding ? (
                <form
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void run(async () => {
                            const r = (
                                await postJson<{
                                    data: {
                                        saved: number;
                                        refused: string[];
                                        roster: TrecRosterData;
                                    };
                                }>(routes.contacts, { lines })
                            ).data;
                            setRoster(r.roster);
                            setLines('');
                            setNote(
                                [
                                    t('brokerage.roster.saved', {
                                        count: r.saved,
                                    }),
                                    r.refused.length > 0
                                        ? t('brokerage.roster.refused', {
                                              lines: r.refused.join('; '),
                                          })
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' '),
                            );
                        });
                    }}
                >
                    <Label htmlFor={`${ids}-lines`}>
                        {t('brokerage.roster.lines')}
                    </Label>
                    <Textarea
                        id={`${ids}-lines`}
                        rows={5}
                        aria-describedby={`${ids}-hint`}
                        value={lines}
                        onChange={(e) => setLines(e.target.value)}
                    />
                    <p
                        id={`${ids}-hint`}
                        className="text-sm text-muted-foreground"
                    >
                        {t('brokerage.roster.addHint')}
                    </p>
                    <Button
                        type="submit"
                        disabled={busy || lines.trim() === ''}
                    >
                        {t('brokerage.roster.save')}
                    </Button>
                </form>
            ) : null}

            {note ? (
                <p
                    role="status"
                    className="rounded-reos-md bg-muted p-3 text-base"
                >
                    {note}
                </p>
            ) : null}
            <InputError message={error ?? undefined} />

            {roster.agents.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title={t('brokerage.roster.none')}
                    description={t('brokerage.roster.noneHint')}
                />
            ) : (
                <ul className="divide-y divide-border rounded-reos-md border border-border">
                    {roster.agents.map((a) => (
                        <li
                            key={a.licence}
                            className="flex flex-wrap items-center justify-between gap-2 p-3"
                        >
                            <span>
                                <span className="block text-base font-medium">
                                    {a.name}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {t('brokerage.roster.licence', {
                                        number: a.licence,
                                    })}
                                    {a.expires_on
                                        ? ` · ${t('brokerage.roster.expires', { date: day(a.expires_on) })}`
                                        : ''}
                                    {a.contact ? ` · ${a.contact}` : ''}
                                </span>
                            </span>
                            <span className="text-sm font-medium">
                                {t(`brokerage.roster.states.${a.state}`)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
