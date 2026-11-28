import {
    BadgeCheck,
    Clock,
    ExternalLink,
    MapPin,
    ShieldAlert,
} from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import type { ProviderCard as Card } from './types';

const KINDS = ['nmls', 'trec_inspector', 'tdi', 'business_id'] as const;

/** The licence kind's name, for the badge. */
function useKindName(kind: string): string {
    const { t } = useTranslation();
    const key =
        KINDS.find((k) => k === (kind === 'business' ? 'business_id' : kind)) ??
        'business_id';

    return t(`providers.licences.kinds.${key}`);
}

/**
 * A provider as agents see them (B15.3): the licence badge and when it was
 * checked, the bio, services, specialities, areas, the response-time goal,
 * free resources and expert sessions. No phone or email: agents reach
 * providers through REOS.
 */
export function ProviderProfileCard({
    provider,
    compact = false,
}: {
    provider: Card;
    compact?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const kind = useKindName(provider.licence.kind);
    const checked = provider.licence.checked_at
        ? new Date(provider.licence.checked_at).toLocaleDateString(
              i18n.language,
              { dateStyle: 'medium' },
          )
        : null;
    const list = (title: string, items: string[]) =>
        items.length === 0 ? null : (
            <div className="space-y-1">
                <h3 className="font-medium">{title}</h3>
                <ul className="flex flex-wrap gap-2">
                    {items.map((i) => (
                        <li
                            key={i}
                            className="rounded-full bg-muted px-3 py-1 text-base"
                        >
                            {i}
                        </li>
                    ))}
                </ul>
            </div>
        );

    return (
        <article
            aria-labelledby={`${ids}-name`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="flex items-start gap-4">
                {provider.photo_url ? (
                    <img
                        src={provider.photo_url}
                        alt=""
                        className="size-16 shrink-0 rounded-full object-cover"
                    />
                ) : (
                    <span
                        aria-hidden
                        className="flex size-16 shrink-0 items-center justify-center rounded-full bg-muted text-title font-semibold"
                    >
                        {provider.name.slice(0, 1)}
                    </span>
                )}
                <div className="min-w-0 flex-1 space-y-1">
                    <h2 id={`${ids}-name`} className="text-title font-semibold">
                        {provider.name}
                    </h2>
                    {provider.category ? (
                        <p className="text-base text-muted-foreground">
                            {provider.category}
                        </p>
                    ) : null}
                    {provider.licence.verified ? (
                        <p className="flex flex-wrap items-center gap-2 text-base text-reos-tone-success-fg">
                            <BadgeCheck aria-hidden className="size-5" />
                            {t('providers.directory.verified', { kind })}
                            {provider.licence.number
                                ? ` · ${t('providers.directory.licenceNumber', { number: provider.licence.number })}`
                                : ''}
                            {checked
                                ? ` · ${t('providers.directory.checked', { date: checked })}`
                                : ''}
                        </p>
                    ) : (
                        <p className="flex items-center gap-2 text-base text-reos-tone-warning-fg">
                            <ShieldAlert aria-hidden className="size-5" />
                            {t('providers.directory.notVerified')}
                        </p>
                    )}
                    {provider.tier === 'premium' ? (
                        <span className="inline-block rounded-full bg-primary px-3 py-1 text-sm text-primary-foreground">
                            {t('providers.directory.partner')}
                        </span>
                    ) : null}
                </div>
                {provider.logo_url ? (
                    <img
                        src={provider.logo_url}
                        alt=""
                        className="h-12 w-auto max-w-24 shrink-0 object-contain"
                    />
                ) : null}
            </header>
            {provider.bio ? (
                <p className="text-base whitespace-pre-line">{provider.bio}</p>
            ) : null}
            {provider.areas.length > 0 ? (
                <p className="flex gap-2 text-base">
                    <MapPin aria-hidden className="mt-0.5 size-5 shrink-0" />
                    <span>
                        <span className="sr-only">
                            {t('providers.directory.areas')}:{' '}
                        </span>
                        {provider.areas.join(' · ')}
                    </span>
                </p>
            ) : null}
            {list(t('providers.directory.services'), provider.services)}
            {compact ? null : (
                <>
                    {list(
                        t('providers.directory.specialities'),
                        provider.specialities,
                    )}
                    {provider.response_hours ? (
                        <p className="flex items-center gap-2 text-base">
                            <Clock aria-hidden className="size-5" />
                            {t('providers.directory.response')}:{' '}
                            {t('providers.profile.responseHours', {
                                count: provider.response_hours,
                            })}
                        </p>
                    ) : null}
                    {provider.resources.length > 0 ? (
                        <div className="space-y-1">
                            <h3 className="font-medium">
                                {t('providers.directory.resources')}
                            </h3>
                            <ul className="space-y-1">
                                {provider.resources.map((r) => (
                                    <li key={r.url}>
                                        <a
                                            href={r.url}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            className="inline-flex min-h-12 items-center gap-1 text-base underline"
                                        >
                                            {r.title}
                                            <ExternalLink
                                                aria-hidden
                                                className="size-4"
                                            />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    {provider.sessions.length > 0 ? (
                        <div className="space-y-1">
                            <h3 className="font-medium">
                                {t('providers.directory.sessions')}
                            </h3>
                            <ul className="space-y-1 text-base">
                                {provider.sessions.map((s) => (
                                    <li key={s.title}>
                                        {s.title} ·{' '}
                                        {t('providers.profile.sessionLength', {
                                            minutes: s.minutes,
                                        })}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </>
            )}
        </article>
    );
}
