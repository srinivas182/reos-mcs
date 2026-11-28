import { DoorOpen, UserRound } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/copy-field';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getJson, postJson } from '@/lib/content';

export type OpenHouse = {
    id: string;
    address: string;
    city: string | null;
    starts_at: string;
    ends_at: string;
    open: boolean;
    url: string;
    qr_url: string;
    visits: number;
};

export type Timeline =
    | 'now'
    | '3_months'
    | '6_months'
    | 'later'
    | 'just_looking';

export type OpenHouseVisit = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    has_agent: boolean;
    pre_approved: boolean;
    timeline: Timeline | null;
    source: 'qr' | 'kiosk';
    signed_in_at: string;
    contact: string | null;
};

/** Where the view starts open houses and reads their visitors. */
export type OpenHouseRoutes = {
    create: string;
    visits: (id: string) => string;
    contact: (uuid: string) => string;
};

/** A datetime-local value for a date, in the browser's time zone. */
export function localInput(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The next whole hour, and three hours after it: a usual open house. */
function defaultTimes(now: Date): [string, string] {
    const start = new Date(now);
    start.setMinutes(0, 0, 0);
    start.setHours(start.getHours() + 1);

    return [
        localInput(start),
        localInput(new Date(start.getTime() + 3 * 3600_000)),
    ];
}

/**
 * Open houses (B14.3a): start sign-in, put the QR code by the door, and
 * see who signed in. Each visitor lands in the agent's contacts with a
 * follow-up and a suggested first message.
 */
export function OpenHousesView({
    houses: initial,
    visits: firstVisits,
    routes,
}: {
    houses: OpenHouse[];
    visits: OpenHouseVisit[];
    routes: OpenHouseRoutes;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [houses, setHouses] = useState(initial);
    const [selected, setSelected] = useState<string | null>(
        initial[0]?.id ?? null,
    );
    const [visits, setVisits] = useState(firstVisits);
    const [address, setAddress] = useState('');
    const [city, setCity] = useState('');
    const [[startsAt, endsAt], setTimes] = useState(() =>
        defaultTimes(new Date()),
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const when = new Intl.DateTimeFormat(i18n.language, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
    const house = houses.find((h) => h.id === selected) ?? null;

    const choose = (id: string) => {
        setSelected(id);
        setVisits([]);
        void getJson<{ data: OpenHouseVisit[] }>(routes.visits(id))
            .then((r) => setVisits(r.data))
            .catch(() => setVisits([]));
    };

    const start = async () => {
        setBusy(true);
        setError(null);
        try {
            const made = (
                await postJson<{ data: OpenHouse }>(routes.create, {
                    address,
                    city: city.trim() === '' ? null : city.trim(),
                    starts_at: new Date(startsAt).toISOString(),
                    ends_at: new Date(endsAt).toISOString(),
                })
            ).data;
            setHouses((now) => [made, ...now]);
            setSelected(made.id);
            setVisits([]);
            setAddress('');
            setCity('');
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <div className="space-y-6">
                <form
                    aria-labelledby={`${ids}-start`}
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void start();
                    }}
                >
                    <h2 id={`${ids}-start`} className="text-lg font-semibold">
                        {t('leads.openHouse.start')}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor={`${ids}-address`}>
                                {t('leads.openHouse.address')}
                            </Label>
                            <Input
                                id={`${ids}-address`}
                                required
                                minLength={3}
                                maxLength={160}
                                value={address}
                                onChange={(e) => setAddress(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor={`${ids}-city`}>
                                {t('leads.openHouse.city')}
                            </Label>
                            <Input
                                id={`${ids}-city`}
                                maxLength={80}
                                value={city}
                                onChange={(e) => setCity(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`${ids}-starts`}>
                                {t('leads.openHouse.startsAt')}
                            </Label>
                            <Input
                                id={`${ids}-starts`}
                                type="datetime-local"
                                required
                                value={startsAt}
                                onChange={(e) =>
                                    setTimes([e.target.value, endsAt])
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`${ids}-ends`}>
                                {t('leads.openHouse.endsAt')}
                            </Label>
                            <Input
                                id={`${ids}-ends`}
                                type="datetime-local"
                                required
                                min={startsAt}
                                value={endsAt}
                                onChange={(e) =>
                                    setTimes([startsAt, e.target.value])
                                }
                            />
                        </div>
                    </div>
                    <InputError message={error ?? undefined} />
                    <Button
                        type="submit"
                        disabled={busy || address.trim().length < 3}
                    >
                        <DoorOpen aria-hidden />
                        {t('leads.openHouse.start')}
                    </Button>
                </form>

                {houses.length === 0 ? (
                    <EmptyState
                        icon={DoorOpen}
                        title={t('leads.openHouse.none')}
                        description={t('leads.openHouse.noneHint')}
                    />
                ) : (
                    <ul className="space-y-2">
                        {houses.map((h) => (
                            <li key={h.id}>
                                <button
                                    type="button"
                                    aria-pressed={h.id === selected}
                                    onClick={() => choose(h.id)}
                                    className="aria-pressed:border-reos-primary flex min-h-12 w-full flex-col items-start gap-1 rounded-reos-md border border-border p-3 text-left aria-pressed:bg-accent"
                                >
                                    <span className="text-base font-medium">
                                        {h.city
                                            ? `${h.address}, ${h.city}`
                                            : h.address}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {when.format(new Date(h.starts_at))} ·{' '}
                                        {h.open
                                            ? t('leads.openHouse.open')
                                            : t('leads.openHouse.closed')}{' '}
                                        ·{' '}
                                        {t('leads.openHouse.visits', {
                                            count: h.visits,
                                        })}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {house ? (
                <section aria-labelledby={`${ids}-house`} className="space-y-5">
                    <h2 id={`${ids}-house`} className="text-lg font-semibold">
                        {house.city
                            ? `${house.address}, ${house.city}`
                            : house.address}
                    </h2>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <img
                            src={house.qr_url}
                            alt={t('leads.openHouse.qrAlt', {
                                address: house.address,
                            })}
                            width={200}
                            height={200}
                            className="size-50 rounded-reos-md border border-border bg-white p-2"
                        />
                        <div className="min-w-0 flex-1">
                            <CopyField
                                label={t('leads.openHouse.link')}
                                value={house.url}
                            />
                        </div>
                    </div>
                    <h3 className="text-base font-semibold">
                        {t('leads.openHouse.visitors')}
                    </h3>
                    {visits.length === 0 ? (
                        <EmptyState
                            icon={UserRound}
                            title={t('leads.openHouse.noVisits')}
                            description={t('leads.openHouse.kioskHint')}
                        />
                    ) : (
                        <ul className="divide-y divide-border rounded-reos-md border border-border">
                            {visits.map((v) => (
                                <li
                                    key={v.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3"
                                >
                                    <div className="min-w-0">
                                        <p className="text-base font-medium">
                                            {v.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                v.email,
                                                v.phone,
                                                v.timeline
                                                    ? t(
                                                          `leads.timelines.${v.timeline}`,
                                                      )
                                                    : null,
                                                v.pre_approved
                                                    ? t(
                                                          'leads.openHouse.preApproved',
                                                      )
                                                    : null,
                                                v.has_agent
                                                    ? t(
                                                          'leads.openHouse.withAgent',
                                                      )
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    {v.contact ? (
                                        <Button variant="outline" asChild>
                                            <a href={routes.contact(v.contact)}>
                                                {t(
                                                    'leads.openHouse.openContact',
                                                )}
                                            </a>
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ) : null}
        </div>
    );
}
