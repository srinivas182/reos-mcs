import { Head, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/billing';
import { nativeSelect } from '@/lib/tenants';
import { update } from '@/routes/ai';

type Settings = {
    answer_order: Record<string, string[]>;
    model: string;
    costs: Record<string, number>;
    default_monthly_credits: number;
    hourly_limit: number;
};

/**
 * AI and credits (web journey screen 69, A6.1): the answer order per role,
 * the model and data rules, the credit cost of each AI action and the
 * limits. Customer data is never used for training. Each save is a new
 * version, audited.
 */
export default function AiAndCredits({
    settings,
    credit_pack,
    options,
}: {
    settings: Settings;
    versions: number;
    credit_pack: { credits: number | null; amount: number } | null;
    options: {
        sources: string[];
        roles: string[];
        models: string[];
        actions: string[];
    };
}) {
    const { t, i18n } = useTranslation();
    const [draft, setDraft] = useState<Settings>(settings);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const setStep = (role: string, index: number, source: string) => {
        const steps = [...(draft.answer_order[role] ?? [])];
        steps[index] = source;
        setDraft({
            ...draft,
            answer_order: {
                ...draft.answer_order,
                [role]: steps.filter(Boolean),
            },
        });
    };

    const save = () =>
        router.put(update().url, draft, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
        });

    return (
        <>
            <Head title={t('aiSettings.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('aiSettings.title')}
                    description={t('aiSettings.description')}
                    actions={
                        <Button onClick={save} disabled={processing}>
                            {t('aiSettings.save')}
                        </Button>
                    }
                />
                <div className="grid gap-6 2xl:grid-cols-2">
                    <section
                        aria-labelledby="answer-order"
                        className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="answer-order" className="font-semibold">
                            {t('aiSettings.answerOrder')}
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-base">
                                <thead className="text-sm text-muted-foreground uppercase">
                                    <tr className="border-b border-border">
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 font-semibold"
                                        >
                                            {t('aiSettings.role')}
                                        </th>
                                        {[1, 2, 3].map((n) => (
                                            <th
                                                key={n}
                                                scope="col"
                                                className="py-2 pr-3 font-semibold"
                                            >
                                                {t('aiSettings.step', {
                                                    count: n,
                                                    ordinal: true,
                                                })}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {options.roles.map((role) => (
                                        <tr
                                            key={role}
                                            className="border-b border-border last:border-b-0"
                                        >
                                            <th
                                                scope="row"
                                                className="py-2 pr-3 font-medium"
                                            >
                                                {t(
                                                    `aiSettings.roles.${role as 'agent'}`,
                                                )}
                                            </th>
                                            {[0, 1, 2].map((index) => (
                                                <td
                                                    key={index}
                                                    className="py-2 pr-3"
                                                >
                                                    <label
                                                        className="sr-only"
                                                        htmlFor={`order-${role}-${index}`}
                                                    >
                                                        {t(
                                                            'aiSettings.stepFor',
                                                            {
                                                                role: t(
                                                                    `aiSettings.roles.${role as 'agent'}`,
                                                                ),
                                                                count:
                                                                    index + 1,
                                                                ordinal: true,
                                                            },
                                                        )}
                                                    </label>
                                                    <select
                                                        id={`order-${role}-${index}`}
                                                        className={`${nativeSelect} min-w-52`}
                                                        value={
                                                            draft.answer_order[
                                                                role
                                                            ]?.[index] ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setStep(
                                                                role,
                                                                index,
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        <option value="">
                                                            {t(
                                                                'aiSettings.none',
                                                            )}
                                                        </option>
                                                        {options.sources.map(
                                                            (source) => (
                                                                <option
                                                                    key={source}
                                                                    value={
                                                                        source
                                                                    }
                                                                >
                                                                    {t(
                                                                        `aiSettings.sources.${source as 'general_ai'}`,
                                                                    )}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <InputError
                            message={
                                Object.entries(errors).find(([k]) =>
                                    k.startsWith('answer_order'),
                                )?.[1]
                            }
                        />
                        <dl className="divide-y divide-border">
                            <div className="flex items-center justify-between gap-4 py-2">
                                <dt>
                                    <Label htmlFor="ai-model">
                                        {t('aiSettings.model')}
                                    </Label>
                                </dt>
                                <dd>
                                    <select
                                        id="ai-model"
                                        className={nativeSelect}
                                        value={draft.model}
                                        onChange={(e) =>
                                            setDraft({
                                                ...draft,
                                                model: e.target.value,
                                            })
                                        }
                                    >
                                        {options.models.map((model) => (
                                            <option key={model} value={model}>
                                                {t(
                                                    `aiSettings.models.${model as 'claude-sonnet'}`,
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 py-2">
                                <dt className="text-muted-foreground">
                                    {t('aiSettings.embeddings')}
                                </dt>
                                <dd className="text-right font-medium">
                                    {t('aiSettings.embeddingsValue')}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 py-2">
                                <dt className="text-muted-foreground">
                                    {t('aiSettings.training')}
                                </dt>
                                <dd className="flex items-center gap-2 font-medium">
                                    <ShieldCheck
                                        aria-hidden
                                        className="size-5 text-reos-tone-success-fg"
                                    />
                                    {t('aiSettings.never')}
                                </dd>
                            </div>
                        </dl>
                    </section>
                    <section
                        aria-labelledby="costs"
                        className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                    >
                        <h2 id="costs" className="font-semibold">
                            {t('aiSettings.costs')}
                        </h2>
                        <div className="divide-y divide-border">
                            {options.actions.map((action) => (
                                <div
                                    key={action}
                                    className="flex items-center justify-between gap-4 py-2"
                                >
                                    <Label
                                        htmlFor={`cost-${action}`}
                                        className="font-normal"
                                    >
                                        {t(
                                            `aiSettings.actions.${action as 'general_answer'}`,
                                        )}
                                    </Label>
                                    <Input
                                        id={`cost-${action}`}
                                        type="number"
                                        min={0}
                                        max={1000}
                                        className="w-28 text-right"
                                        value={draft.costs[action] ?? 0}
                                        onChange={(e) =>
                                            setDraft({
                                                ...draft,
                                                costs: {
                                                    ...draft.costs,
                                                    [action]: Number(
                                                        e.target.value,
                                                    ),
                                                },
                                            })
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="divide-y divide-border">
                            <div className="flex items-center justify-between gap-4 py-2">
                                <Label
                                    htmlFor="ai-monthly"
                                    className="font-normal"
                                >
                                    {t('aiSettings.monthly')}
                                </Label>
                                <Input
                                    id="ai-monthly"
                                    type="number"
                                    min={0}
                                    className="w-28 text-right"
                                    value={draft.default_monthly_credits}
                                    onChange={(e) =>
                                        setDraft({
                                            ...draft,
                                            default_monthly_credits: Number(
                                                e.target.value,
                                            ),
                                        })
                                    }
                                />
                            </div>
                            <div className="flex justify-between gap-4 py-2">
                                <span className="text-muted-foreground">
                                    {t('aiSettings.creditPack')}
                                </span>
                                <span className="font-medium">
                                    {credit_pack
                                        ? t('aiSettings.creditPackValue', {
                                              credits: new Intl.NumberFormat(
                                                  i18n.language,
                                              ).format(
                                                  credit_pack.credits ?? 0,
                                              ),
                                              amount: money(
                                                  credit_pack.amount,
                                                  i18n.language,
                                              ),
                                          })
                                        : '—'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-4 py-2">
                                <Label
                                    htmlFor="ai-hourly"
                                    className="font-normal"
                                >
                                    {t('aiSettings.abuseLimit')}
                                </Label>
                                <Input
                                    id="ai-hourly"
                                    type="number"
                                    min={1}
                                    className="w-28 text-right"
                                    value={draft.hourly_limit}
                                    onChange={(e) =>
                                        setDraft({
                                            ...draft,
                                            hourly_limit: Number(
                                                e.target.value,
                                            ),
                                        })
                                    }
                                />
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('aiSettings.candidates')}
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}
