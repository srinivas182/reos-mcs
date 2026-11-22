import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Info, Pencil } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RuleEditorDialog } from '@/components/rule-editor-dialog';
import { RuleVersionsDialog } from '@/components/rule-versions-dialog';
import { Button } from '@/components/ui/button';
import type { Course, RuleRow, RuleTopic } from '@/lib/state-rules';
import { ruleDate, ruleText } from '@/lib/state-rules';
import { cn } from '@/lib/utils';
import { index } from '@/routes/states';
import { publish } from '@/routes/states/rules';

type Props = {
    state: { code: string; name: string; regulator: string | null };
    topics: RuleTopic[];
    liveFeatures: string[];
    rules: RuleRow[];
};

/**
 * State rules (web journey screen 62, A5.2): a state's regulator rules in
 * tabs, where each is used and its source. Edits are drafts until "Publish
 * changes"; every published change is a new version with its effective
 * date, and the history keeps them all.
 */
export default function StateRules({
    state,
    topics,
    liveFeatures,
    rules,
}: Props) {
    const { t, i18n } = useTranslation();
    const [topic, setTopic] = useState<RuleTopic>('ce');
    const [editing, setEditing] = useState<RuleRow | null>(null);
    const [history, setHistory] = useState(false);
    const shown = rules.filter((rule) => rule.topic === topic);
    const drafts = rules.filter((rule) => rule.draft !== null).length;
    const mandatory = (rules.find((r) => r.key === 'ce.mandatory_courses')
        ?.current?.value.courses ?? []) as Course[];
    const features = [...new Set(shown.flatMap((rule) => rule.used_by))];
    const sources = [
        ...new Map(
            shown
                .map((rule) => rule.current)
                .filter((v) => v !== null)
                .map((v) => [v.citation, v]),
        ).values(),
    ];

    return (
        <>
            <Head title={t('stateRules.title', { state: state.name })} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('stateRules.title', { state: state.name })}
                    description={t('stateRules.description')}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('stateRules.back')}
                        </Link>
                    }
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setHistory(true)}
                            >
                                {t('stateRules.history')}
                            </Button>
                            <Button
                                disabled={drafts === 0}
                                onClick={() =>
                                    router.post(
                                        publish(state.code).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {drafts > 0
                                    ? t('stateRules.publishCount', {
                                          count: drafts,
                                      })
                                    : t('stateRules.publish')}
                            </Button>
                        </>
                    }
                />

                <div
                    role="tablist"
                    aria-label={t('stateRules.topicsLabel')}
                    className="-mb-px flex gap-2 overflow-x-auto border-b border-border"
                >
                    {topics.map((value) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={value === topic}
                            onClick={() => setTopic(value)}
                            className={cn(
                                'flex min-h-12 items-center border-b-2 px-3 text-base whitespace-nowrap focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                value === topic
                                    ? 'border-primary font-semibold text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(`stateRules.topics.${value}`)}
                        </button>
                    ))}
                </div>

                {shown.every(
                    (rule) => rule.current === null && rule.draft === null,
                ) ? (
                    <EmptyState
                        icon={Info}
                        title={t('stateRules.emptyTitle', {
                            topic: t(`stateRules.topics.${topic}`),
                        })}
                        description={t('stateRules.emptyDescription')}
                        action={
                            shown[0] ? (
                                <Button onClick={() => setEditing(shown[0]!)}>
                                    {t('stateRules.addFirst')}
                                </Button>
                            ) : undefined
                        }
                    />
                ) : null}

                {shown.length > 0 ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <section
                            aria-labelledby="rules-card"
                            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <h2 id="rules-card" className="font-semibold">
                                {t(`stateRules.topics.${topic}`)}
                            </h2>
                            <ul className="divide-y divide-border">
                                {shown.map((rule) => {
                                    const value = rule.draft ?? rule.current;

                                    return (
                                        <li
                                            key={rule.key}
                                            className="flex flex-wrap items-start gap-3 py-3"
                                        >
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <p className="text-body text-muted-foreground">
                                                    {t(
                                                        `stateRules.keys.${rule.key}` as 'stateRules.keys.ce.hours',
                                                    )}
                                                </p>
                                                <p className="font-medium text-foreground">
                                                    {value
                                                        ? ruleText(
                                                              t,
                                                              rule.type,
                                                              rule.unit,
                                                              value.value,
                                                          )
                                                        : t(
                                                              'stateRules.notSet',
                                                          )}
                                                </p>
                                                {rule.draft ? (
                                                    <p className="text-sm font-semibold text-reos-tone-warning-fg">
                                                        {t(
                                                            'stateRules.draftNote',
                                                            {
                                                                date: ruleDate(
                                                                    rule.draft
                                                                        .effective_from,
                                                                    i18n.language,
                                                                ),
                                                            },
                                                        )}
                                                    </p>
                                                ) : null}
                                                {rule.scheduled ? (
                                                    <p className="text-sm text-muted-foreground">
                                                        {t(
                                                            'stateRules.scheduledNote',
                                                            {
                                                                value: ruleText(
                                                                    t,
                                                                    rule.type,
                                                                    rule.unit,
                                                                    rule
                                                                        .scheduled
                                                                        .value,
                                                                ),
                                                                date: ruleDate(
                                                                    rule
                                                                        .scheduled
                                                                        .effective_from,
                                                                    i18n.language,
                                                                ),
                                                            },
                                                        )}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="min-h-12"
                                                aria-label={t(
                                                    'stateRules.editRule',
                                                    {
                                                        rule: t(
                                                            `stateRules.keys.${rule.key}` as 'stateRules.keys.ce.hours',
                                                        ),
                                                    },
                                                )}
                                                onClick={() => setEditing(rule)}
                                            >
                                                <Pencil aria-hidden />
                                                {t('stateRules.edit.button')}
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ul>
                            <p className="flex items-start gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                                <Info
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0"
                                />
                                {t('stateRules.note', {
                                    regulator: state.regulator ?? state.name,
                                })}
                            </p>
                        </section>

                        <section
                            aria-labelledby="where-used"
                            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <h2 id="where-used" className="font-semibold">
                                {t('stateRules.whereUsed')}
                            </h2>
                            <table className="w-full text-left text-body">
                                <thead className="text-sm text-muted-foreground uppercase">
                                    <tr className="border-b border-border">
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 font-semibold"
                                        >
                                            {t('stateRules.feature')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 font-semibold"
                                        >
                                            {t('stateRules.uses')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 font-semibold"
                                        >
                                            <span className="sr-only">
                                                {t('stateRules.status')}
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {features.map((feature) => (
                                        <tr
                                            key={feature}
                                            className="border-b border-border last:border-b-0"
                                        >
                                            <th
                                                scope="row"
                                                className="py-2 pr-3 font-medium"
                                            >
                                                {t(
                                                    `stateRules.features.${feature}` as 'stateRules.features.ce_card',
                                                )}
                                            </th>
                                            <td className="py-2 pr-3 text-muted-foreground">
                                                {shown
                                                    .filter((rule) =>
                                                        rule.used_by.includes(
                                                            feature,
                                                        ),
                                                    )
                                                    .map((rule) =>
                                                        t(
                                                            `stateRules.short.${rule.key}` as 'stateRules.short.ce.hours',
                                                        ),
                                                    )
                                                    .join(', ')}
                                            </td>
                                            <td className="py-2">
                                                <span
                                                    className={cn(
                                                        'rounded-reos-pill px-2 py-0.5 text-sm font-semibold',
                                                        liveFeatures.includes(
                                                            feature,
                                                        )
                                                            ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                                            : 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
                                                    )}
                                                >
                                                    {liveFeatures.includes(
                                                        feature,
                                                    )
                                                        ? t('stateRules.live')
                                                        : t(
                                                              'stateRules.planned',
                                                          )}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {sources.length > 0 ? (
                                <div className="space-y-2">
                                    <h3 className="text-sm font-semibold">
                                        {t('stateRules.source')}
                                    </h3>
                                    <ul className="space-y-2">
                                        {sources.map((source) => (
                                            <li
                                                key={source.citation}
                                                className="rounded-reos-md border border-border px-3 py-2 text-sm"
                                            >
                                                {source.source_url ? (
                                                    <a
                                                        href={source.source_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {source.citation}
                                                    </a>
                                                ) : (
                                                    <span className="font-medium">
                                                        {source.citation}
                                                    </span>
                                                )}
                                                {' · '}
                                                {t('stateRules.sourceDates', {
                                                    effective: ruleDate(
                                                        source.effective_from,
                                                        i18n.language,
                                                    ),
                                                    checked: ruleDate(
                                                        source.checked_on,
                                                        i18n.language,
                                                    ),
                                                })}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                        </section>
                    </div>
                ) : null}
            </div>

            {editing ? (
                <RuleEditorDialog
                    state={state.code}
                    rule={editing}
                    courses={mandatory}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            <RuleVersionsDialog
                open={history}
                onOpenChange={setHistory}
                rules={rules}
            />
        </>
    );
}
