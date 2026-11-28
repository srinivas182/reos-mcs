import { BarChart3, QrCode, UserCheck } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { CopyField } from '@/components/copy-field';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson, putJson } from '@/lib/content';
import { card, errorText } from './community';
import type { CommunityResults, RosterRow } from './types';

const select =
    'h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base';

/** The invite (B17.2, provider screen 16): the link, a QR code for events, and a new link. */
export function InvitePanel({
    name,
    inviteUrl,
    qrUrl,
    newCodeUrl,
    onChanged,
}: {
    name: string;
    inviteUrl: string;
    /** The QR code image; null in stories. */
    qrUrl: string | null;
    newCodeUrl: string;
    onChanged: () => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [error, setError] = useState<string | null>(null);

    return (
        <section aria-labelledby={`${ids}-invite`} className={card}>
            <h2
                id={`${ids}-invite`}
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <QrCode aria-hidden className="size-5" />
                {t('communities.invite.title')}
            </h2>
            <p className="text-body text-muted-foreground">
                {t('communities.invite.purpose')}
            </p>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                {qrUrl ? (
                    // The SVG has its own white field, so it scans in dark mode too.
                    <img
                        src={qrUrl}
                        alt={t('communities.invite.qr', { name })}
                        className="size-40 rounded-reos-md border border-border"
                    />
                ) : null}
                <div className="flex-1 space-y-3">
                    <CopyField
                        label={t('communities.invite.link')}
                        value={inviteUrl}
                    />
                    <Button
                        variant="outline"
                        onClick={() =>
                            void postJson(newCodeUrl)
                                .then(() => {
                                    toast.success(
                                        t('communities.invite.newCodeDone'),
                                    );
                                    onChanged();
                                })
                                .catch((e: unknown) => setError(errorText(e)))
                        }
                    >
                        {t('communities.invite.newCode')}
                    </Button>
                </div>
            </div>
            <InputError message={error ?? undefined} />
        </section>
    );
}

/** Schedule an expert session (B17.2): free on Pro; a price on Partner only; CE only with an approved course number. */
export function ScheduleForm({
    url,
    canCharge,
    onScheduled,
}: {
    url: string;
    /** Partner providers can charge. */
    canCharge: boolean;
    onScheduled: () => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [v, setV] = useState({
        title: '',
        about: '',
        format: 'online',
        starts_at: '',
        minutes: '60',
        place: '',
        join_url: '',
        capacity: '',
        price: '0',
        ce_course: '',
    });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const set = (key: keyof typeof v) => (e: { target: { value: string } }) =>
        setV({ ...v, [key]: e.target.value });
    const field = (key: keyof typeof v, label: string, type = 'text') => (
        <div className="space-y-2">
            <Label htmlFor={`${ids}-${key}`}>{label}</Label>
            <Input
                id={`${ids}-${key}`}
                type={type}
                value={v[key]}
                onChange={set(key)}
            />
        </div>
    );

    return (
        <form
            className={card}
            aria-labelledby={`${ids}-title`}
            onSubmit={(e) => {
                e.preventDefault();
                setBusy(true);
                setError(null);
                postJson(url, {
                    title: v.title,
                    about: v.about || null,
                    format: v.format,
                    starts_at:
                        v.starts_at === ''
                            ? ''
                            : new Date(v.starts_at).toISOString(),
                    minutes: Number(v.minutes),
                    place: v.format === 'in_person' ? v.place : null,
                    join_url: v.format === 'online' ? v.join_url : null,
                    capacity: v.capacity === '' ? null : Number(v.capacity),
                    price_cents: Math.round(Number(v.price || '0') * 100),
                    ce_course: v.ce_course || null,
                })
                    .then(() => {
                        toast.success(t('communities.sessions.scheduled'));
                        setV({ ...v, title: '', about: '', starts_at: '' });
                        onScheduled();
                    })
                    .catch((err: unknown) => setError(errorText(err)))
                    .finally(() => setBusy(false));
            }}
        >
            <h2 id={`${ids}-title`} className="text-lg font-semibold">
                {t('communities.sessions.schedule')}
            </h2>
            {field('title', t('communities.sessions.sessionTitle'))}
            <div className="space-y-2">
                <Label htmlFor={`${ids}-about`}>
                    {t('communities.sessions.about')}
                </Label>
                <Textarea
                    id={`${ids}-about`}
                    rows={3}
                    value={v.about}
                    onChange={set('about')}
                />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={`${ids}-format`}>
                        {t('communities.sessions.format')}
                    </Label>
                    <select
                        id={`${ids}-format`}
                        className={select}
                        value={v.format}
                        onChange={set('format')}
                    >
                        <option value="online">
                            {t('communities.sessions.formats.online')}
                        </option>
                        <option value="in_person">
                            {t('communities.sessions.formats.in_person')}
                        </option>
                    </select>
                </div>
                {field(
                    'starts_at',
                    t('communities.sessions.startsAt'),
                    'datetime-local',
                )}
                {field('minutes', t('communities.sessions.minutes'), 'number')}
                {v.format === 'online'
                    ? field(
                          'join_url',
                          t('communities.sessions.joinUrl'),
                          'url',
                      )
                    : field('place', t('communities.sessions.place'))}
                {field(
                    'capacity',
                    t('communities.sessions.capacity'),
                    'number',
                )}
                <div className="space-y-2">
                    <Label htmlFor={`${ids}-price`}>
                        {t('communities.sessions.price')}
                    </Label>
                    <Input
                        id={`${ids}-price`}
                        type="number"
                        min={0}
                        step="1"
                        value={v.price}
                        disabled={!canCharge}
                        aria-describedby={
                            canCharge ? undefined : `${ids}-partner`
                        }
                        onChange={set('price')}
                    />
                    {canCharge ? null : (
                        <p
                            id={`${ids}-partner`}
                            className="text-body text-muted-foreground"
                        >
                            {t('communities.sessions.pricePartner')}
                        </p>
                    )}
                </div>
            </div>
            {field('ce_course', t('communities.sessions.ce'))}
            <InputError message={error ?? undefined} />
            <Button
                type="submit"
                disabled={
                    busy || v.title.trim().length < 3 || v.starts_at === ''
                }
            >
                {t('communities.sessions.schedule')}
            </Button>
        </form>
    );
}

/** Who's going, and who came (B17.2): the host ticks attendees after the session. */
export function RosterPanel({
    title,
    rows,
    url,
    onSaved,
}: {
    title: string;
    rows: RosterRow[];
    url: string;
    onSaved: () => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [came, setCame] = useState<number[]>(
        rows.filter((r) => r.attended).map((r) => r.id),
    );
    const [error, setError] = useState<string | null>(null);

    return (
        <section aria-labelledby={`${ids}-roster`} className={card}>
            <h2
                id={`${ids}-roster`}
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <UserCheck aria-hidden className="size-5" />
                {t('communities.sessions.rosterFor', { title })}
            </h2>
            {rows.length === 0 ? (
                <p className="text-body text-muted-foreground">
                    {t('communities.sessions.rosterEmpty')}
                </p>
            ) : (
                <>
                    <ul className="grid gap-1 sm:grid-cols-2">
                        {rows.map((r) => (
                            <li
                                key={r.id}
                                className="flex min-h-12 items-center gap-3"
                            >
                                <Checkbox
                                    id={`${ids}-${r.id}`}
                                    checked={came.includes(r.id)}
                                    onCheckedChange={(on) =>
                                        setCame(
                                            on === true
                                                ? [...came, r.id]
                                                : came.filter(
                                                      (x) => x !== r.id,
                                                  ),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`${ids}-${r.id}`}
                                    className="text-base"
                                >
                                    {t('communities.sessions.attendedFor', {
                                        name: r.name,
                                    })}
                                </Label>
                            </li>
                        ))}
                    </ul>
                    <Button
                        onClick={() =>
                            void putJson(url, { user_ids: came })
                                .then(() => {
                                    toast.success(
                                        t(
                                            'communities.sessions.attendanceSaved',
                                        ),
                                    );
                                    onSaved();
                                })
                                .catch((e: unknown) => setError(errorText(e)))
                        }
                    >
                        {t('communities.sessions.attendance')}
                    </Button>
                </>
            )}
            <InputError message={error ?? undefined} />
        </section>
    );
}

/** Results for the owner (B17.2, provider screen 18). */
export function ResultsView({ results }: { results: CommunityResults }) {
    const { t } = useTranslation();
    const tiles = [
        [t('communities.results.members'), String(results.members)],
        [t('communities.results.joined30'), String(results.joined_30)],
        [t('communities.results.viaLink'), String(results.via.link)],
        [t('communities.results.viaQr'), String(results.via.qr)],
        [t('communities.results.posts30'), String(results.posts_30)],
        [t('communities.results.sessions'), String(results.sessions)],
        [t('communities.results.rsvps'), String(results.rsvps)],
        [t('communities.results.attended'), String(results.attended)],
        [
            t('communities.results.showRate'),
            t('communities.results.showRateValue', { rate: results.show_rate }),
        ],
    ] as const;

    return (
        <div className="space-y-6">
            <ul className="grid gap-3 sm:grid-cols-3">
                {tiles.map(([label, value]) => (
                    <li
                        key={label}
                        className="rounded-reos-lg border border-border bg-card p-4"
                    >
                        <p className="text-body text-muted-foreground">
                            {label}
                        </p>
                        <p className="text-2xl font-semibold">{value}</p>
                    </li>
                ))}
            </ul>
            <section className={card} aria-label={t('communities.results.top')}>
                <h2 className="flex items-center gap-2 text-lg font-semibold">
                    <BarChart3 aria-hidden className="size-5" />
                    {t('communities.results.top')}
                </h2>
                {results.top.length === 0 ? (
                    <EmptyState
                        icon={BarChart3}
                        title={t('communities.results.topEmpty')}
                        description={t('communities.results.topEmptyHint')}
                    />
                ) : (
                    <ul className="space-y-2">
                        {results.top.map((s) => (
                            <li key={s.title} className="text-body">
                                {t('communities.results.topRow', s)}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
