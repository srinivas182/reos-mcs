import { Handshake, MessageSquare, ShieldOff } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { postJson, putJson } from '@/lib/content';
import type {
    AgentNetwork,
    PendingProviderRequest,
    ProviderConnection,
    ProviderPreferences,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';

function firstError(e: unknown): string {
    if (typeof e === 'object' && e !== null && 'errors' in e) {
        const v = Object.values(
            (e as { errors: Record<string, string[] | string> }).errors,
        )[0];

        return Array.isArray(v) ? (v[0] ?? '') : (v ?? '');
    }

    return e instanceof Error ? e.message : String(e);
}

function useDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(iso).toLocaleDateString(i18n.language, {
            dateStyle: 'medium',
        });
}

export type NetworkRoutes = {
    accept: (id: string) => string;
    decline: (id: string) => string;
    share: (id: string) => string;
    block: (id: string) => string;
    preferences: string;
    chat: (conversation: string) => string;
};

/**
 * One connection (B16.2), for either side: Share my phone and email (shown
 * only when both have shared), Message, and Block.
 */
export function ConnectionCard({
    connection: initial,
    routes,
}: {
    connection: ProviderConnection;
    routes: Pick<NetworkRoutes, 'share' | 'block' | 'chat'>;
}) {
    const { t } = useTranslation();
    const date = useDate();
    const [c, setC] = useState(initial);
    const [busy, setBusy] = useState(false);
    const title =
        c.side === 'agent'
            ? `${c.provider.name}${c.provider.category ? ` · ${c.provider.category}` : ''}`
            : c.with;

    const share = async (on: boolean) => {
        setBusy(true);

        try {
            setC(
                (
                    await putJson<{ data: ProviderConnection }>(
                        routes.share(c.id),
                        { share: on },
                    )
                ).data,
            );
        } catch (e) {
            toast.error(firstError(e));
        } finally {
            setBusy(false);
        }
    };
    const block = async () => {
        try {
            await postJson(routes.block(c.id));
            setC({ ...c, blocked: true, contacts: null, conversation: null });
            toast.success(t('providers.network.blocked'));
        } catch (e) {
            toast.error(firstError(e));
        }
    };

    return (
        <li className="space-y-3 py-4">
            <div>
                <p className="font-medium">{title}</p>
                {c.connected_at ? (
                    <p className="text-base text-muted-foreground">
                        {t('providers.network.connectedOn', {
                            date: date(c.connected_at),
                        })}
                    </p>
                ) : null}
            </div>
            {c.blocked ? (
                <p className="text-base text-muted-foreground">
                    {t('providers.network.blocked')}
                </p>
            ) : (
                <>
                    {c.contacts ? (
                        <div className="rounded-reos-md bg-reos-tone-success-bg p-3 text-base text-reos-tone-success-fg">
                            <p className="font-medium">
                                {t('providers.network.contacts')}
                            </p>
                            {c.contacts.email ? (
                                <p>
                                    {t('providers.network.email', {
                                        email: c.contacts.email,
                                    })}
                                </p>
                            ) : null}
                            {c.contacts.phone ? (
                                <p>
                                    {t('providers.network.phone', {
                                        phone: c.contacts.phone,
                                    })}
                                </p>
                            ) : null}
                        </div>
                    ) : (
                        <p className="text-base text-muted-foreground">
                            {c.i_shared
                                ? t('providers.network.waitingThem', {
                                      name: c.with,
                                  })
                                : c.they_shared
                                  ? t('providers.network.waitingMe', {
                                        name: c.with,
                                    })
                                  : t('providers.network.notShared')}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant={c.i_shared ? 'outline' : 'default'}
                            disabled={busy}
                            onClick={() => void share(!c.i_shared)}
                        >
                            {c.i_shared
                                ? t('providers.network.unshare')
                                : t('providers.network.share')}
                        </Button>
                        {c.conversation ? (
                            <Button asChild variant="outline">
                                <a
                                    href={routes.chat(c.conversation)}
                                    aria-label={t(
                                        'providers.network.messageFor',
                                        { name: c.with },
                                    )}
                                >
                                    <MessageSquare aria-hidden />
                                    {t('providers.network.message')}
                                </a>
                            </Button>
                        ) : null}
                        <Button
                            variant="outline"
                            aria-label={t('providers.network.blockFor', {
                                name: c.with,
                            })}
                            onClick={() => void block()}
                        >
                            <ShieldOff aria-hidden />
                            {t('providers.network.block')}
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {t('providers.network.report')}
                    </p>
                </>
            )}
        </li>
    );
}

/** The agent's choices (B16.2): all providers, and each category. */
function Preferences({
    initial,
    route,
}: {
    initial: ProviderPreferences;
    route: string;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [prefs, setPrefs] = useState(initial);
    const [busy, setBusy] = useState(false);

    const save = async () => {
        setBusy(true);

        try {
            const res = await putJson<{ data: ProviderPreferences }>(route, {
                all: prefs.all,
                off: prefs.categories.filter((c) => !c.on).map((c) => c.key),
            });
            setPrefs(res.data);
            toast.success(t('providers.network.preferences.saved'));
        } catch (e) {
            toast.error(firstError(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className={card} aria-labelledby={`${ids}-prefs`}>
            <h2 id={`${ids}-prefs`} className="text-title font-semibold">
                {t('providers.network.preferences.title')}
            </h2>
            <div className="flex items-center justify-between gap-4">
                <Label htmlFor={`${ids}-all`} className="text-base">
                    {t('providers.network.preferences.all')}
                </Label>
                <Switch
                    id={`${ids}-all`}
                    checked={prefs.all}
                    onCheckedChange={(all) => setPrefs({ ...prefs, all })}
                />
            </div>
            {prefs.all ? (
                <fieldset className="space-y-3">
                    <legend className="font-medium">
                        {t('providers.network.preferences.categories')}
                    </legend>
                    <p className="text-base text-muted-foreground">
                        {t('providers.network.preferences.categoriesHint')}
                    </p>
                    {prefs.categories.map((c) => (
                        <div key={c.key} className="flex items-center gap-3">
                            <Checkbox
                                id={`${ids}-${c.key}`}
                                checked={c.on}
                                onCheckedChange={(on) =>
                                    setPrefs({
                                        ...prefs,
                                        categories: prefs.categories.map((x) =>
                                            x.key === c.key
                                                ? { ...x, on: on === true }
                                                : x,
                                        ),
                                    })
                                }
                            />
                            <Label
                                htmlFor={`${ids}-${c.key}`}
                                className="text-base"
                            >
                                {c.name}
                            </Label>
                        </div>
                    ))}
                </fieldset>
            ) : null}
            <Button onClick={() => void save()} disabled={busy}>
                {t('providers.network.preferences.save')}
            </Button>
        </section>
    );
}

/**
 * Provider requests (B16.2, agent journey): waiting requests to accept or
 * decline (and block), connections with mutual sharing, and my choices.
 */
export function ProviderRequestsInbox({
    network,
    routes,
}: {
    network: AgentNetwork;
    routes: NetworkRoutes;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const date = useDate();
    const [pending, setPending] = useState(network.pending);
    const [connections, setConnections] = useState(network.connections);

    const answer = async (
        r: PendingProviderRequest,
        how: 'accept' | 'decline' | 'block',
    ) => {
        try {
            if (how === 'accept') {
                const res = await postJson<{ data: ProviderConnection }>(
                    routes.accept(r.id),
                );
                setConnections((list) => [res.data, ...list]);
                toast.success(
                    t('providers.network.accepted', { name: r.provider.name }),
                );
            } else {
                await postJson(routes.decline(r.id), {
                    block: how === 'block',
                });
                toast.success(
                    t('providers.network.declined', { name: r.provider.name }),
                );
            }

            setPending((list) => list.filter((x) => x.id !== r.id));
        } catch (e) {
            toast.error(firstError(e));
        }
    };

    return (
        <div className="space-y-6">
            <section className={card} aria-labelledby={`${ids}-pending`}>
                <h2 id={`${ids}-pending`} className="text-title font-semibold">
                    {t('providers.network.pending')}
                </h2>
                {pending.length === 0 ? (
                    <div className="space-y-1">
                        <p className="font-medium">
                            {t('providers.network.pendingEmpty')}
                        </p>
                        <p className="text-base text-muted-foreground">
                            {t('providers.network.pendingEmptyHint')}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border">
                        {pending.map((r) => (
                            <li key={r.id} className="space-y-3 py-4">
                                <div>
                                    <p className="font-medium">
                                        {r.provider.name}
                                        {r.provider.category
                                            ? ` · ${r.provider.category}`
                                            : ''}
                                    </p>
                                    <p className="text-base text-muted-foreground">
                                        {t('providers.agents.expires', {
                                            date: date(r.expires_at),
                                        })}
                                    </p>
                                </div>
                                {r.message ? (
                                    <p className="rounded-reos-md bg-muted p-3 text-base">
                                        {r.message}
                                    </p>
                                ) : null}
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        aria-label={t(
                                            'providers.network.acceptFor',
                                            { name: r.provider.name },
                                        )}
                                        onClick={() => void answer(r, 'accept')}
                                    >
                                        {t('providers.network.accept')}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        aria-label={t(
                                            'providers.network.declineFor',
                                            { name: r.provider.name },
                                        )}
                                        onClick={() =>
                                            void answer(r, 'decline')
                                        }
                                    >
                                        {t('providers.network.decline')}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        aria-label={t(
                                            'providers.network.declineBlockFor',
                                            { name: r.provider.name },
                                        )}
                                        onClick={() => void answer(r, 'block')}
                                    >
                                        {t('providers.network.declineBlock')}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className={card} aria-labelledby={`${ids}-connections`}>
                <h2
                    id={`${ids}-connections`}
                    className="text-title font-semibold"
                >
                    {t('providers.network.connections')}
                </h2>
                {connections.length === 0 ? (
                    <EmptyState
                        icon={Handshake}
                        title={t('providers.network.connectionsEmpty')}
                        description={t(
                            'providers.network.connectionsEmptyHint',
                        )}
                        className="border-0"
                    />
                ) : (
                    <ul className="divide-y divide-border">
                        {connections.map((c) => (
                            <ConnectionCard
                                key={c.id}
                                connection={c}
                                routes={routes}
                            />
                        ))}
                    </ul>
                )}
            </section>

            <Preferences
                initial={network.preferences}
                route={routes.preferences}
            />
        </div>
    );
}
