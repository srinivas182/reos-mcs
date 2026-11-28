import {
    ExternalLink,
    ScrollText,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { deleteJson, postJson, putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import type { BrokerageRule, TrecConflict } from './types';

/** The TREC rule a brokerage rule contradicts, with its source. */
export function ConflictNote({ conflict }: { conflict: TrecConflict }) {
    const { t } = useTranslation();

    return (
        <div
            role="alert"
            className="space-y-1 rounded-reos-md border border-reos-tone-danger-fg/40 bg-reos-tone-danger-bg p-3 text-reos-tone-danger-fg"
        >
            <p className="flex items-center gap-2 font-medium">
                <ShieldAlert aria-hidden className="size-5" />
                {t('brokerage.policies.conflict', {
                    citation: conflict.citation,
                })}
            </p>
            <p className="text-base">{conflict.rule_text}</p>
            <p className="text-sm">{t('brokerage.policies.conflictHint')}</p>
            {conflict.url ? (
                <a
                    href={conflict.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex min-h-12 items-center gap-1 text-base underline"
                >
                    {t('brokerage.policies.trecSource')}
                    <ExternalLink aria-hidden className="size-4" />
                </a>
            ) : null}
        </div>
    );
}

/**
 * Brokerage rules (A15.2, broker journey screen 5): rules the brokerage
 * writes beside its manual, checked against TREC as they're typed and when
 * saved. A conflicting rule is kept and flagged with the TREC source.
 */
export function BrokerageRules({
    rules: initial,
    canManage,
    routes,
}: {
    rules: BrokerageRule[];
    canManage: boolean;
    routes: {
        check: string;
        store: string;
        update: (id: number) => string;
        destroy: (id: number) => string;
        restore: (id: number) => string;
    };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [rules, setRules] = useState(initial);
    const [editing, setEditing] = useState<number | null>(null);
    const [topic, setTopic] = useState('');
    const [text, setText] = useState('');
    const [live, setLive] = useState<TrecConflict | null>(null);
    const [checked, setChecked] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Checked against TREC as the broker types (after a short pause).
    useEffect(() => {
        if (text.trim().length < 5) {
            return;
        }
        let current = true;
        const timer = window.setTimeout(() => {
            void postJson<{ data: { conflict: TrecConflict | null } }>(
                routes.check,
                { text },
            )
                .then((r) => {
                    if (current) {
                        setLive(r.data.conflict);
                        setChecked(true);
                    }
                })
                .catch(() => undefined);
        }, 500);

        return () => {
            current = false;
            window.clearTimeout(timer);
        };
    }, [text, routes.check]);

    const reset = () => {
        setEditing(null);
        setTopic('');
        setText('');
        setLive(null);
        setChecked(false);
    };

    const save = async () => {
        setBusy(true);
        setError(null);
        try {
            const body = { topic, text };
            const saved = (
                editing === null
                    ? await postJson<{ data: BrokerageRule }>(
                          routes.store,
                          body,
                      )
                    : await putJson<{ data: BrokerageRule }>(
                          routes.update(editing),
                          body,
                      )
            ).data;
            setRules((now) => [saved, ...now.filter((r) => r.id !== saved.id)]);
            reset();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    const remove = (rule: BrokerageRule) => {
        setRules((now) => now.filter((r) => r.id !== rule.id));
        void deleteJson(routes.destroy(rule.id));
        undoToast({
            message: t('brokerage.policies.removed'),
            onUndo: () =>
                postJson(routes.restore(rule.id)).then(() =>
                    setRules((now) => [rule, ...now]),
                ),
        });
    };

    return (
        <section
            aria-labelledby={`${ids}-title`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="space-y-1">
                <h2 id={`${ids}-title`} className="text-lg font-semibold">
                    {t('brokerage.policies.rules')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {canManage
                        ? t('brokerage.policies.rulesHint')
                        : t('brokerage.policies.readOnly')}
                </p>
            </header>

            {canManage ? (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void save();
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-topic`}>
                            {t('brokerage.policies.topic')}
                        </Label>
                        <Input
                            id={`${ids}-topic`}
                            required
                            minLength={2}
                            maxLength={120}
                            value={topic}
                            onChange={(e) => setTopic(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-text`}>
                            {t('brokerage.policies.text')}
                        </Label>
                        <Textarea
                            id={`${ids}-text`}
                            rows={4}
                            required
                            minLength={5}
                            maxLength={4000}
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                        />
                    </div>
                    {live ? <ConflictNote conflict={live} /> : null}
                    {!live && checked && text.trim().length >= 5 ? (
                        <p
                            role="status"
                            className="flex items-center gap-2 text-base"
                        >
                            <ShieldCheck aria-hidden className="size-5" />
                            {t('brokerage.policies.ok')}
                        </p>
                    ) : null}
                    <InputError message={error ?? undefined} />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            disabled={
                                busy ||
                                topic.trim().length < 2 ||
                                text.trim().length < 5
                            }
                        >
                            {editing === null
                                ? t('brokerage.policies.add')
                                : t('brokerage.policies.save')}
                        </Button>
                    </div>
                </form>
            ) : null}

            {rules.length === 0 ? (
                <EmptyState
                    icon={ScrollText}
                    title={t('brokerage.policies.none')}
                    description={t('brokerage.policies.noneHint')}
                />
            ) : (
                <ul className="space-y-3">
                    {rules.map((rule) => (
                        <li
                            key={rule.id}
                            className="space-y-2 rounded-reos-md border border-border p-3"
                        >
                            <p className="font-medium">{rule.topic}</p>
                            <p className="text-base">{rule.text}</p>
                            {rule.conflict ? (
                                <ConflictNote conflict={rule.conflict} />
                            ) : null}
                            {canManage ? (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        aria-label={`${t('brokerage.policies.edit')}: ${rule.topic}`}
                                        onClick={() => {
                                            setEditing(rule.id);
                                            setTopic(rule.topic);
                                            setText(rule.text);
                                        }}
                                    >
                                        {t('brokerage.policies.edit')}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        aria-label={`${t('brokerage.policies.remove')}: ${rule.topic}`}
                                        onClick={() => remove(rule)}
                                    >
                                        {t('brokerage.policies.remove')}
                                    </Button>
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
