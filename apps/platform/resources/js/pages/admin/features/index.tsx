import { Head, router } from '@inertiajs/react';
import {
    Flag,
    Megaphone,
    Plus,
    Power,
    RotateCcw,
    SlidersHorizontal,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import type {
    FlagOptions,
    FlagRow,
    RolloutStage,
} from '@/components/feature-flag-dialogs';
import {
    ReleaseDialog,
    RolloutDialog,
} from '@/components/feature-flag-dialogs';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/time-ago';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { kill, restore } from '@/routes/features';
import type { RoleName } from '@/types/auth';

type ReleaseRow = {
    id: number;
    version: string;
    released_on: string;
    notes: Partial<Record<RoleName, string>>;
    flags: string[];
    author: string | null;
    dismissed: number;
};

const TONE: Record<RolloutStage, string> = {
    off: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    internal: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    tenants: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    states: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    percent: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    everyone: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
};

/**
 * Features and releases (web journey screen 70, A6.2): each feature flag
 * with its rollout step, who sees it and the errors since the rollout;
 * change rollout and turn off everywhere (with Undo). Releases below, with
 * their notes per role and the flags they rolled out; New release.
 * Automatic rollback steps a failing flag back one stage on its own.
 */
export default function FeaturesAndReleases({
    flags,
    releases,
    options,
}: {
    flags: FlagRow[];
    releases: ReleaseRow[];
    options: FlagOptions;
}) {
    const { t, i18n } = useTranslation();
    const [editing, setEditing] = useState<FlagRow | null>(null);
    const [publishing, setPublishing] = useState(false);
    const today = new Date().toISOString().slice(0, 10);
    const date = (value: string) =>
        new Date(`${value}T12:00:00`).toLocaleDateString(i18n.language, {
            dateStyle: 'medium',
        });
    const names = Object.fromEntries(flags.map((f) => [f.key, f.name]));

    const rolloutDetail = (flag: FlagRow): string | null => {
        if (flag.stage === 'off' || flag.stage === 'everyone') {
            return null;
        }

        const parts: string[] = [];

        if (flag.stage !== 'internal') {
            // Up to three tenants by name, then a count.
            parts.push(
                flag.tenants.length > 0 && flag.tenants.length <= 3
                    ? flag.tenants.map((x) => x.name).join(', ')
                    : t('features.detail.tenants', {
                          count: flag.tenants.length,
                      }),
            );
        }

        if (
            (flag.stage === 'states' || flag.stage === 'percent') &&
            flag.states.length > 0
        ) {
            parts.push(flag.states.join(', '));
        }

        if (flag.stage === 'percent') {
            parts.push(t('features.detail.percent', { percent: flag.percent }));
        }

        return parts.length > 0 ? parts.join(' · ') : null;
    };

    const turnOff = (flag: FlagRow) => {
        router.post(
            kill(flag.key).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    undoToast({
                        message: t('features.killed', { name: flag.name }),
                        onUndo: () =>
                            new Promise<void>((resolve) =>
                                router.delete(restore(flag.key).url, {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                }),
                            ),
                    }),
            },
        );
    };

    const turnOn = (flag: FlagRow) => {
        router.delete(restore(flag.key).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('features.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('features.title')}
                    description={t('features.purpose')}
                    actions={
                        <Button onClick={() => setPublishing(true)}>
                            <Plus aria-hidden />
                            {t('features.releases.new')}
                        </Button>
                    }
                />

                <section
                    aria-labelledby="flags-title"
                    className="rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2
                        id="flags-title"
                        className="mb-3 text-title font-semibold"
                    >
                        {t('features.flagsTitle')}
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-base">
                            <caption className="sr-only">
                                {t('features.flagsTitle')}
                            </caption>
                            <thead className="text-sm text-muted-foreground uppercase">
                                <tr className="border-b border-border">
                                    {(
                                        [
                                            'feature',
                                            'rollout',
                                            'who',
                                            'errors',
                                            'actions',
                                        ] as const
                                    ).map((c) => (
                                        <th
                                            key={c}
                                            scope="col"
                                            className="px-3 py-3 font-semibold"
                                        >
                                            {t(`features.columns.${c}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {flags.map((flag) => {
                                    const detail = rolloutDetail(flag);
                                    const killed = flag.killed_at !== null;
                                    const rate =
                                        flag.uses > 0
                                            ? (
                                                  (flag.errors * 100) /
                                                  flag.uses
                                              ).toFixed(1)
                                            : null;

                                    return (
                                        <tr
                                            key={flag.key}
                                            className="border-b border-border align-top last:border-b-0"
                                        >
                                            <th
                                                scope="row"
                                                className="max-w-sm px-3 py-4 font-normal"
                                            >
                                                <span className="block font-semibold">
                                                    {flag.name}
                                                </span>
                                                {flag.description ? (
                                                    <span className="block text-sm text-muted-foreground">
                                                        {flag.description}
                                                    </span>
                                                ) : null}
                                                <span className="mt-1 block font-mono text-sm text-muted-foreground">
                                                    {flag.key}
                                                </span>
                                                {flag.tenant_feature ? (
                                                    <span className="mt-1 block text-sm text-muted-foreground">
                                                        {t(
                                                            'features.tenantFeature',
                                                        )}
                                                    </span>
                                                ) : null}
                                            </th>
                                            <td className="px-3 py-4">
                                                {killed ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-reos-tone-danger-bg px-3 py-1 text-sm font-semibold text-reos-tone-danger-fg">
                                                        <Power
                                                            aria-hidden
                                                            className="size-4"
                                                        />
                                                        {t(
                                                            'features.offEverywhere',
                                                        )}
                                                    </span>
                                                ) : (
                                                    <span
                                                        className={cn(
                                                            'inline-flex rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap',
                                                            TONE[flag.stage],
                                                        )}
                                                    >
                                                        {t(
                                                            `features.stages.${flag.stage}`,
                                                        )}
                                                    </span>
                                                )}
                                                {detail ? (
                                                    <span className="mt-1 block text-sm text-muted-foreground">
                                                        {detail}
                                                    </span>
                                                ) : null}
                                                {flag.rolled_back_at &&
                                                flag.rolled_back_from ? (
                                                    <p className="mt-2 flex max-w-xs gap-2 rounded-reos-md bg-reos-tone-warning-bg p-2 text-sm text-reos-tone-warning-fg">
                                                        <TriangleAlert
                                                            aria-hidden
                                                            className="size-4 shrink-0"
                                                        />
                                                        {t(
                                                            'features.rolledBack',
                                                            {
                                                                ago: timeAgo(
                                                                    flag.rolled_back_at,
                                                                    i18n.language,
                                                                ),
                                                                from: t(
                                                                    `features.stages.${flag.rolled_back_from}`,
                                                                ),
                                                                threshold:
                                                                    flag.error_threshold,
                                                                minutes:
                                                                    options.window_minutes,
                                                            },
                                                        )}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-4">
                                                {flag.roles &&
                                                flag.roles.length > 0
                                                    ? t('features.onlyRoles', {
                                                          roles: flag.roles
                                                              .map((r) =>
                                                                  t(
                                                                      `roles.names.${r}`,
                                                                  ),
                                                              )
                                                              .join(', '),
                                                      })
                                                    : t('features.everyRole')}
                                            </td>
                                            <td className="px-3 py-4 tabular-nums">
                                                {flag.uses === 0 ? (
                                                    <span className="text-muted-foreground">
                                                        {t('features.noUses')}
                                                    </span>
                                                ) : (
                                                    <>
                                                        <span className="block">
                                                            {t(
                                                                'features.uses',
                                                                {
                                                                    errors: flag.errors,
                                                                    uses: flag.uses,
                                                                },
                                                            )}
                                                        </span>
                                                        <span className="block text-sm text-muted-foreground">
                                                            {t(
                                                                'features.rate',
                                                                {
                                                                    rate,
                                                                },
                                                            )}
                                                        </span>
                                                    </>
                                                )}
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="flex flex-col gap-2">
                                                    <Button
                                                        variant="outline"
                                                        onClick={() =>
                                                            setEditing(flag)
                                                        }
                                                        aria-label={t(
                                                            'features.changeRolloutFor',
                                                            { name: flag.name },
                                                        )}
                                                    >
                                                        <SlidersHorizontal
                                                            aria-hidden
                                                        />
                                                        {t(
                                                            'features.changeRollout',
                                                        )}
                                                    </Button>
                                                    {killed ? (
                                                        <Button
                                                            variant="outline"
                                                            onClick={() =>
                                                                turnOn(flag)
                                                            }
                                                            aria-label={t(
                                                                'features.restoreFor',
                                                                {
                                                                    name: flag.name,
                                                                },
                                                            )}
                                                        >
                                                            <RotateCcw
                                                                aria-hidden
                                                            />
                                                            {t(
                                                                'features.restore',
                                                            )}
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            variant="destructive"
                                                            onClick={() =>
                                                                turnOff(flag)
                                                            }
                                                            aria-label={t(
                                                                'features.killFor',
                                                                {
                                                                    name: flag.name,
                                                                },
                                                            )}
                                                        >
                                                            <Power
                                                                aria-hidden
                                                            />
                                                            {t('features.kill')}
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    aria-labelledby="releases-title"
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <h2
                        id="releases-title"
                        className="text-title font-semibold"
                    >
                        {t('features.releases.title')}
                    </h2>
                    {releases.length === 0 ? (
                        <EmptyState
                            icon={Megaphone}
                            title={t('features.releases.empty')}
                            description={t('features.releases.emptyBody')}
                            action={
                                <Button onClick={() => setPublishing(true)}>
                                    <Plus aria-hidden />
                                    {t('features.releases.new')}
                                </Button>
                            }
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {releases.map((release) => (
                                <li key={release.id} className="space-y-2 py-4">
                                    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <h3 className="text-lg font-semibold">
                                            {release.version}
                                        </h3>
                                        <span className="text-body text-muted-foreground">
                                            {date(release.released_on)}
                                        </span>
                                        {release.author ? (
                                            <span className="text-sm text-muted-foreground">
                                                {t('features.releases.by', {
                                                    name: release.author,
                                                })}
                                            </span>
                                        ) : null}
                                        <span className="text-sm text-muted-foreground">
                                            {t('features.releases.dismissed', {
                                                count: release.dismissed,
                                            })}
                                        </span>
                                    </div>
                                    <dl className="grid gap-2 sm:grid-cols-[12rem_1fr]">
                                        {(
                                            Object.entries(release.notes) as [
                                                RoleName,
                                                string,
                                            ][]
                                        ).map(([role, note]) => (
                                            <div
                                                key={role}
                                                className="contents"
                                            >
                                                <dt className="text-sm font-medium text-muted-foreground">
                                                    {t(`roles.names.${role}`)}
                                                </dt>
                                                <dd className="text-body">
                                                    {note}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                    {release.flags.length > 0 ? (
                                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Flag
                                                aria-hidden
                                                className="size-4"
                                            />
                                            {t('features.releases.rollsOut', {
                                                flags: release.flags
                                                    .map((k) => names[k] ?? k)
                                                    .join(', '),
                                            })}
                                        </p>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <RolloutDialog
                flag={editing}
                options={options}
                onOpenChange={(open) => (open ? null : setEditing(null))}
            />
            <ReleaseDialog
                open={publishing}
                flags={flags}
                roles={[...options.roles, 'super_admin']}
                today={today}
                onOpenChange={setPublishing}
            />
        </>
    );
}
