import { Check, Inbox } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { money } from '@/lib/billing';
import { postJson, putJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import type {
    Answer,
    CategoryCard,
    Joining,
    JoinField,
    JoinRoutes,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';

function errorsOf(e: unknown): Record<string, string> {
    if (typeof e === 'object' && e !== null && 'errors' in e) {
        const errors = (e as { errors: Record<string, string[] | string> })
            .errors;

        return Object.fromEntries(
            Object.entries(errors).map(([k, v]) => [
                k,
                Array.isArray(v) ? (v[0] ?? '') : v,
            ]),
        );
    }

    return { form: e instanceof Error ? e.message : String(e) };
}

/** The cheapest plan in the provider's state, if the admin has priced one. */
const lowest = (c: CategoryCard): number | null => {
    const prices = [c.prices.basic, c.prices.pro, c.prices.premium].filter(
        (p): p is number => p !== null,
    );

    return prices.length === 0 ? null : Math.min(...prices);
};

/**
 * What do you do? (B15.1, provider screen 2, ADR 0073): the category grid,
 * the licence each one checks and its profile fields all come from the
 * super admin's settings; a provider whose work isn't listed asks for it.
 */
export function CategoryStep({
    categories,
    joining: initial,
    routes,
    onChanged,
}: {
    categories: CategoryCard[];
    joining: Joining;
    routes: JoinRoutes;
    /** Told after a choice or saved answers (the joining steps refresh). */
    onChanged?: (joining: Joining) => void;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [joining, setJoining] = useState(initial);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [ask, setAsk] = useState({ name: '', note: '' });

    const choose = async (key: string) => {
        setBusy(true);
        setErrors({});

        try {
            const next = (
                await putJson<{ data: Joining }>(routes.category, {
                    category: key,
                })
            ).data;
            setJoining(next);
            onChanged?.(next);
        } catch (e) {
            setErrors(errorsOf(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="space-y-6">
            <section aria-labelledby={`${ids}-grid`} className="space-y-3">
                <div className="space-y-1">
                    <h2 id={`${ids}-grid`} className="font-semibold">
                        {t('providers.join.categoryTitle')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('providers.join.categoryHint')}
                    </p>
                </div>
                {categories.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title={t('providers.join.emptyTitle')}
                        description={t('providers.join.empty')}
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {categories.map((c) => {
                            const chosen = joining.category?.key === c.key;
                            const from = lowest(c);

                            return (
                                <li
                                    key={c.key}
                                    className={cn(
                                        'flex flex-col gap-3 rounded-reos-lg border bg-card p-5',
                                        chosen
                                            ? 'border-primary ring-2 ring-primary/30'
                                            : 'border-border',
                                    )}
                                >
                                    <h3 className="font-semibold">{c.name}</h3>
                                    {c.description ? (
                                        <p className="text-base">
                                            {c.description}
                                        </p>
                                    ) : null}
                                    <p className="text-base text-muted-foreground">
                                        {t(
                                            `providers.join.licence.${c.licence.kind}`,
                                        )}
                                    </p>
                                    <p className="text-base">
                                        {from === null
                                            ? t('providers.join.noPrice')
                                            : t('providers.join.from', {
                                                  price: money(
                                                      from,
                                                      i18n.language,
                                                  ),
                                              })}
                                    </p>
                                    <Button
                                        type="button"
                                        className="mt-auto"
                                        variant={chosen ? 'default' : 'outline'}
                                        disabled={busy}
                                        aria-pressed={chosen}
                                        aria-label={
                                            chosen
                                                ? `${t('providers.join.chosen')}: ${c.name}`
                                                : undefined
                                        }
                                        onClick={() => void choose(c.key)}
                                    >
                                        {chosen ? (
                                            <>
                                                <Check
                                                    aria-hidden
                                                    className="size-5"
                                                />
                                                {t('providers.join.chosen')}
                                            </>
                                        ) : (
                                            t('providers.join.choose', {
                                                name: c.name,
                                            })
                                        )}
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <InputError message={errors.category} />
            </section>

            {joining.category ? (
                <FieldsForm
                    key={joining.category.key}
                    joining={joining}
                    route={routes.fields}
                    onSaved={(next) => {
                        setJoining(next);
                        onChanged?.(next);
                    }}
                />
            ) : null}

            <section aria-labelledby={`${ids}-ask`} className={card}>
                <div className="space-y-1">
                    <h2 id={`${ids}-ask`} className="font-semibold">
                        {t('providers.join.request.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('providers.join.request.hint')}
                    </p>
                </div>
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setBusy(true);
                        setErrors({});
                        postJson<{ data: { name: string } }>(routes.request, {
                            name: ask.name,
                            note: ask.note === '' ? null : ask.note,
                        })
                            .then((res) => {
                                toast.success(
                                    t('providers.join.request.sent', {
                                        name: res.data.name,
                                    }),
                                );
                                setAsk({ name: '', note: '' });
                            })
                            .catch((err: unknown) => setErrors(errorsOf(err)))
                            .finally(() => setBusy(false));
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-ask-name`}>
                            {t('providers.join.request.name')}
                        </Label>
                        <Input
                            id={`${ids}-ask-name`}
                            value={ask.name}
                            maxLength={120}
                            required
                            minLength={3}
                            onChange={(e) =>
                                setAsk({ ...ask, name: e.target.value })
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-ask-note`}>
                            {t('providers.join.request.note')}
                        </Label>
                        <Textarea
                            id={`${ids}-ask-note`}
                            value={ask.note}
                            maxLength={300}
                            rows={2}
                            onChange={(e) =>
                                setAsk({ ...ask, note: e.target.value })
                            }
                        />
                    </div>
                    <Button type="submit" variant="outline" disabled={busy}>
                        {t('providers.join.request.send')}
                    </Button>
                </form>
            </section>
        </div>
    );
}

/** The chosen category's profile fields: text, a list or yes/no. */
function FieldsForm({
    joining,
    route,
    onSaved,
}: {
    joining: Joining;
    route: string;
    onSaved: (j: Joining) => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const category = joining.category as CategoryCard;
    const [values, setValues] = useState<Record<string, Answer>>(
        joining.fields,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const set = (key: string, value: Answer) =>
        setValues({ ...values, [key]: value });
    const control = (f: JoinField) => {
        const id = `${ids}-${f.key}`;
        const value = values[f.key];

        if (f.type === 'yes_no') {
            return (
                <fieldset className="grid gap-2">
                    <legend className="font-medium">{f.label}</legend>
                    <div className="flex gap-2">
                        {[true, false].map((v) => (
                            <Button
                                key={String(v)}
                                type="button"
                                variant={value === v ? 'default' : 'outline'}
                                aria-pressed={value === v}
                                onClick={() => set(f.key, v)}
                            >
                                {v
                                    ? t('providers.join.yes')
                                    : t('providers.join.no')}
                            </Button>
                        ))}
                    </div>
                </fieldset>
            );
        }

        if (f.type === 'list' && f.options.length > 0) {
            const chosen = Array.isArray(value) ? value : [];

            return (
                <fieldset className="grid gap-2">
                    <legend className="font-medium">{f.label}</legend>
                    <div className="flex flex-wrap gap-2">
                        {f.options.map((o) => (
                            <label
                                key={o}
                                className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3 text-base"
                            >
                                <input
                                    type="checkbox"
                                    className="size-5 accent-primary"
                                    checked={chosen.includes(o)}
                                    onChange={(e) =>
                                        set(
                                            f.key,
                                            e.target.checked
                                                ? [...chosen, o]
                                                : chosen.filter((c) => c !== o),
                                        )
                                    }
                                />
                                {o}
                            </label>
                        ))}
                    </div>
                </fieldset>
            );
        }

        if (f.type === 'list') {
            return (
                <div className="grid gap-2">
                    <Label htmlFor={id}>{f.label}</Label>
                    <Input
                        id={id}
                        aria-describedby={`${id}-hint`}
                        value={Array.isArray(value) ? value.join(', ') : ''}
                        onChange={(e) =>
                            set(
                                f.key,
                                e.target.value
                                    .split(',')
                                    .map((v) => v.trimStart()),
                            )
                        }
                    />
                    <p
                        id={`${id}-hint`}
                        className="text-sm text-muted-foreground"
                    >
                        {t('providers.join.listHint')}
                    </p>
                </div>
            );
        }

        return (
            <div className="grid gap-2">
                <Label htmlFor={id}>{f.label}</Label>
                <Input
                    id={id}
                    value={typeof value === 'string' ? value : ''}
                    maxLength={500}
                    onChange={(e) => set(f.key, e.target.value)}
                />
            </div>
        );
    };

    return (
        <section aria-labelledby={`${ids}-fields`} className={card}>
            <div className="space-y-1">
                <h2 id={`${ids}-fields`} className="font-semibold">
                    {t('providers.join.fieldsTitle', { name: category.name })}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('providers.join.fieldsHint')}
                </p>
                <p role="status" className="text-base">
                    {joining.missing.length === 0
                        ? t('providers.join.complete')
                        : t('providers.join.missing', {
                              count: joining.missing.length,
                          })}
                </p>
            </div>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    setBusy(true);
                    setErrors({});
                    const answers = Object.fromEntries(
                        Object.entries(values)
                            .map(([k, v]): [string, Answer] => [
                                k,
                                Array.isArray(v)
                                    ? v
                                          .map((x) => x.trim())
                                          .filter((x) => x !== '')
                                    : v,
                            ])
                            .filter(
                                ([, v]) =>
                                    !(Array.isArray(v) && v.length === 0) &&
                                    v !== '',
                            ),
                    );
                    putJson<{ data: Joining }>(route, { fields: answers })
                        .then((res) => {
                            onSaved(res.data);
                            toast.success(t('providers.join.saved'));
                        })
                        .catch((err: unknown) => setErrors(errorsOf(err)))
                        .finally(() => setBusy(false));
                }}
            >
                {category.fields.map((f) => (
                    <div key={f.key} className="space-y-1">
                        {control(f)}
                        <InputError message={errors[`fields.${f.key}`]} />
                    </div>
                ))}
                <Button type="submit" disabled={busy}>
                    {t('providers.join.save')}
                </Button>
            </form>
        </section>
    );
}
