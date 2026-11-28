import { BadgeCheck, Handshake, MapPin, Star } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import type {
    NearProvider,
    NearYouData,
    ProviderConnection,
    ProviderNetworkInfo,
    ProviderRating,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';

function errorsOf(e: unknown): Record<string, string> {
    if (typeof e === 'object' && e !== null && 'errors' in e) {
        return Object.fromEntries(
            Object.entries(
                (e as { errors: Record<string, string[] | string> }).errors,
            ).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : v]),
        );
    }

    return { form: e instanceof Error ? e.message : String(e) };
}

function useDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(
            iso.length === 10 ? `${iso}T12:00:00` : iso,
        ).toLocaleDateString(i18n.language, { dateStyle: 'medium' });
}

function RatingLine({ rating }: { rating: ProviderRating }) {
    const { t } = useTranslation();

    if (rating.count === 0 || rating.average === null) {
        return null;
    }

    return (
        <p className="flex items-center gap-1 text-base">
            <Star aria-hidden className="size-4 fill-current text-primary" />
            {t('providers.directory.reviews.average', {
                average: rating.average,
                count: rating.count,
            })}
        </p>
    );
}

/**
 * Providers near you (B16.3, agent journey): verified providers serving
 * the agent's cities first, by category, with ratings. One tap opens a
 * provider; the second is Request to connect.
 */
export function NearYou({
    near,
    profileHref,
    categoryHref,
    category,
}: {
    near: NearYouData;
    profileHref: (id: number) => string;
    categoryHref: (key: string | null) => string;
    category: string | null;
}) {
    const { t } = useTranslation();
    const ids = useId();

    return (
        <section className={card} aria-labelledby={`${ids}-near`}>
            <div className="space-y-1">
                <h2 id={`${ids}-near`} className="text-title font-semibold">
                    {t('providers.directory.near.title')}
                </h2>
                <p className="flex items-center gap-2 text-base text-muted-foreground">
                    <MapPin aria-hidden className="size-4" />
                    {near.cities.length > 0
                        ? t('providers.directory.near.serving', {
                              cities: near.cities.join(', '),
                          })
                        : t('providers.directory.near.none')}
                </p>
            </div>
            <nav aria-label={t('providers.directory.category')}>
                <ul className="flex flex-wrap gap-2">
                    <li>
                        <Button
                            asChild
                            variant={category === null ? 'default' : 'outline'}
                        >
                            <a
                                href={categoryHref(null)}
                                aria-current={
                                    category === null ? 'page' : undefined
                                }
                            >
                                {t('providers.directory.near.all')}
                            </a>
                        </Button>
                    </li>
                    {near.categories
                        .filter((c) => c.count > 0 || c.key === category)
                        .map((c) => (
                            <li key={c.key}>
                                <Button
                                    asChild
                                    variant={
                                        category === c.key
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    <a
                                        href={categoryHref(c.key)}
                                        aria-current={
                                            category === c.key
                                                ? 'page'
                                                : undefined
                                        }
                                    >
                                        {t(
                                            'providers.directory.near.category',
                                            {
                                                name: c.name,
                                                count: c.count,
                                            },
                                        )}
                                    </a>
                                </Button>
                            </li>
                        ))}
                </ul>
            </nav>
            {near.providers.length === 0 ? (
                <EmptyState
                    icon={Handshake}
                    title={t('providers.directory.near.empty')}
                    description={t('providers.directory.near.emptyHint')}
                    className="border-0"
                />
            ) : (
                <ul className="divide-y divide-border">
                    {near.providers.map((p) => (
                        <NearRow
                            key={p.id}
                            provider={p}
                            href={profileHref(p.id)}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}

function NearRow({
    provider: p,
    href,
}: {
    provider: NearProvider;
    href: string;
}) {
    const { t } = useTranslation();

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 py-4">
            <div className="space-y-1">
                <p className="flex flex-wrap items-center gap-2 font-medium">
                    {p.name}
                    {p.licence_verified ? (
                        <BadgeCheck
                            aria-hidden
                            className="size-5 text-reos-tone-success-fg"
                        />
                    ) : null}
                    {p.near ? (
                        <span className="rounded-full bg-reos-tone-success-bg px-3 py-1 text-sm text-reos-tone-success-fg">
                            {t('providers.directory.near.badge')}
                        </span>
                    ) : null}
                </p>
                <p className="text-base text-muted-foreground">
                    {[p.category, p.areas.join(', ')]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                <RatingLine rating={p.rating} />
            </div>
            <Button asChild variant={p.connection ? 'outline' : 'default'}>
                <a href={href}>
                    {t('providers.directory.open', { name: p.name })}
                </a>
            </Button>
        </li>
    );
}

/**
 * Beside a provider's profile (B16.3): Request to connect (the second
 * tap), reviews from agents who worked with them, and my review after
 * 30 days connected.
 */
export function ProviderNetworkPanel({
    name,
    network: initial,
    routes,
}: {
    name: string;
    network: ProviderNetworkInfo;
    routes: { connect: string; review: string; requests: string };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const date = useDate();
    const [network, setNetwork] = useState(initial);
    const [rating, setRating] = useState(initial.my_review?.rating ?? 0);
    const [body, setBody] = useState(initial.my_review?.body ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const connected = network.connection?.status === 'accepted';

    const connect = async () => {
        setBusy(true);

        try {
            const res = await postJson<{ data: ProviderConnection }>(
                routes.connect,
            );
            setNetwork({
                ...network,
                connection: { id: res.data.id, status: 'accepted' },
                can_connect: false,
            });
            toast.success(t('providers.directory.connect.connected', { name }));
        } catch (e) {
            toast.error(Object.values(errorsOf(e))[0] ?? '');
        } finally {
            setBusy(false);
        }
    };
    const review = async () => {
        setErrors({});

        try {
            const res = await postJson<{ data: ProviderNetworkInfo }>(
                routes.review,
                { rating, body: body || null },
            );
            setNetwork(res.data);
            toast.success(t('providers.directory.reviews.saved'));
        } catch (e) {
            setErrors(errorsOf(e));
        }
    };

    return (
        <div className="space-y-6">
            <section className={card} aria-labelledby={`${ids}-connect`}>
                <h2 id={`${ids}-connect`} className="sr-only">
                    {t('providers.directory.connect.button')}
                </h2>
                {connected ? (
                    <div className="space-y-3">
                        <p className="text-base">
                            {t('providers.directory.connect.already')}
                        </p>
                        <Button asChild variant="outline">
                            <a href={routes.requests}>
                                {t('providers.directory.connect.open')}
                            </a>
                        </Button>
                    </div>
                ) : network.can_connect ? (
                    <Button
                        size="lg"
                        aria-label={t('providers.directory.connect.buttonFor', {
                            name,
                        })}
                        disabled={busy}
                        onClick={() => void connect()}
                    >
                        <Handshake aria-hidden />
                        {t('providers.directory.connect.button')}
                    </Button>
                ) : null}
            </section>

            <section className={card} aria-labelledby={`${ids}-reviews`}>
                <h2 id={`${ids}-reviews`} className="text-title font-semibold">
                    {t('providers.directory.reviews.title')}
                </h2>
                <RatingLine rating={network.rating} />
                {network.reviews.length === 0 ? (
                    <div className="space-y-1">
                        <p className="font-medium">
                            {t('providers.directory.reviews.none')}
                        </p>
                        <p className="text-base text-muted-foreground">
                            {t('providers.directory.reviews.noneHint')}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border">
                        {network.reviews.map((r) => (
                            <li key={r.id} className="space-y-1 py-3">
                                <p className="font-medium">
                                    {t('providers.directory.reviews.stars', {
                                        count: r.rating,
                                    })}
                                </p>
                                {r.body ? (
                                    <p className="text-base">{r.body}</p>
                                ) : null}
                                <p className="text-sm text-muted-foreground">
                                    {t('providers.directory.reviews.by', {
                                        agent: r.agent,
                                        date: date(r.at),
                                    })}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                {network.can_review ? (
                    <div className="space-y-3 border-t border-border pt-4">
                        <h3 className="font-semibold">
                            {network.my_review
                                ? t('providers.directory.reviews.edit')
                                : t('providers.directory.reviews.write')}
                        </h3>
                        <fieldset>
                            <legend className="mb-2 text-base">
                                {t('providers.directory.reviews.rating')}
                            </legend>
                            <div className="flex gap-2">
                                {[1, 2, 3, 4, 5].map((n) => (
                                    <Button
                                        key={n}
                                        type="button"
                                        variant={
                                            rating >= n ? 'default' : 'outline'
                                        }
                                        aria-pressed={rating === n}
                                        aria-label={t(
                                            'providers.directory.reviews.stars',
                                            { count: n },
                                        )}
                                        onClick={() => setRating(n)}
                                    >
                                        <Star
                                            aria-hidden
                                            className={cn(
                                                rating >= n && 'fill-current',
                                            )}
                                        />
                                    </Button>
                                ))}
                            </div>
                        </fieldset>
                        <div className="space-y-2">
                            <Label htmlFor={`${ids}-body`}>
                                {t('providers.directory.reviews.body')}
                            </Label>
                            <Textarea
                                id={`${ids}-body`}
                                value={body}
                                maxLength={1000}
                                onChange={(e) => setBody(e.target.value)}
                            />
                            <p className="text-base text-muted-foreground">
                                {t('providers.directory.reviews.bodyHint')}
                            </p>
                            <InputError
                                message={
                                    errors.body ??
                                    errors.review ??
                                    errors.rating ??
                                    errors.form
                                }
                            />
                        </div>
                        <Button
                            onClick={() => void review()}
                            disabled={rating === 0}
                        >
                            {t('providers.directory.reviews.submit')}
                        </Button>
                    </div>
                ) : connected && network.review_from ? (
                    <p className="text-base text-muted-foreground">
                        {t('providers.directory.reviews.from', {
                            date: date(network.review_from),
                        })}
                    </p>
                ) : null}
            </section>
        </div>
    );
}
