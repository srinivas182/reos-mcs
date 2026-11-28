import { Sparkles } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postForm, postJson, putJson } from '@/lib/content';
import { ProviderProfileCard } from './provider-card';
import type { ProfileBuilderData, ProfileDraft } from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const select =
    'h-12 rounded-reos-md border border-input bg-background px-3 text-base';

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

const split = (v: string) =>
    v
        .split(',')
        .map((s) => s.trim())
        .filter((s) => s !== '');

/**
 * Build your profile (B15.3, provider screen 7): photo and logo, a bio in
 * English and Spanish with "Write it for me", services, specialities, a
 * response-time goal, free resources and expert sessions. Saving checks the
 * wording first; the preview shows what agents see.
 */
export function ProfileBuilder({
    builder: initial,
    routes,
}: {
    builder: ProfileBuilderData;
    routes: { save: string; bio: string; photo: string };
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [builder, setBuilder] = useState(initial);
    const [draft, setDraft] = useState<ProfileDraft>(initial.draft);
    const [services, setServices] = useState(initial.draft.services.join(', '));
    const [specialities, setSpecialities] = useState(
        initial.draft.specialities.join(', '),
    );
    const [busy, setBusy] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saved, setSaved] = useState<'published' | 'blocked' | null>(null);

    const save = async () => {
        setBusy('save');
        setErrors({});

        try {
            const next = (
                await putJson<{ data: ProfileBuilderData }>(routes.save, {
                    ...draft,
                    services: split(services),
                    specialities: split(specialities),
                })
            ).data;
            setBuilder(next);
            setSaved(
                next.issues.some((i) => i.severity === 'block')
                    ? 'blocked'
                    : 'published',
            );
        } catch (e) {
            setErrors(errorsOf(e));
        } finally {
            setBusy(null);
        }
    };

    const upload = async (kind: 'photo' | 'logo', file: File | undefined) => {
        if (!file) {
            return;
        }

        const body = new FormData();
        body.append('kind', kind);
        body.append('image', file);
        setBusy(kind);

        try {
            setBuilder(
                (
                    await postForm<{ data: ProfileBuilderData }>(
                        routes.photo,
                        body,
                    )
                ).data,
            );
        } catch (e) {
            setErrors(errorsOf(e));
        } finally {
            setBusy(null);
        }
    };

    const rows = <K extends 'resources' | 'sessions'>(
        key: K,
        value: ProfileDraft[K],
    ) => setDraft({ ...draft, [key]: value });

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    void save();
                }}
            >
                <section aria-labelledby={`${ids}-photos`} className={card}>
                    <h2 id={`${ids}-photos`} className="sr-only">
                        {t('providers.profile.photo')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {(['photo', 'logo'] as const).map((kind) => (
                            <div key={kind} className="grid gap-2">
                                <Label htmlFor={`${ids}-${kind}`}>
                                    {kind === 'photo'
                                        ? t('providers.profile.photo')
                                        : t('providers.profile.logo')}
                                </Label>
                                <Input
                                    id={`${ids}-${kind}`}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    disabled={busy !== null}
                                    aria-describedby={`${ids}-photo-hint`}
                                    onChange={(e) =>
                                        void upload(kind, e.target.files?.[0])
                                    }
                                />
                                <InputError message={errors.image} />
                            </div>
                        ))}
                    </div>
                    <p
                        id={`${ids}-photo-hint`}
                        className="text-sm text-muted-foreground"
                    >
                        {t('providers.profile.photoHint')}
                    </p>
                </section>

                <section aria-labelledby={`${ids}-about`} className={card}>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id={`${ids}-about`} className="font-semibold">
                            {t('providers.profile.bio')}
                        </h2>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() => {
                                setBusy('bio');
                                postJson<{
                                    data: { bio: string; bio_es: string };
                                }>(routes.bio)
                                    .then((res) => {
                                        setDraft({
                                            ...draft,
                                            bio: res.data.bio,
                                            bio_es: res.data.bio_es,
                                        });
                                        toast.success(
                                            t('providers.profile.written'),
                                        );
                                    })
                                    .catch((err: unknown) =>
                                        setErrors(errorsOf(err)),
                                    )
                                    .finally(() => setBusy(null));
                            }}
                        >
                            <Sparkles aria-hidden className="size-5" />
                            {busy === 'bio'
                                ? t('providers.profile.writing')
                                : t('providers.profile.write')}
                        </Button>
                    </div>
                    <p
                        id={`${ids}-bio-hint`}
                        className="text-sm text-muted-foreground"
                    >
                        {t('providers.profile.bioHint')}
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-bio`}>
                            {t('providers.profile.bio')}
                        </Label>
                        <Textarea
                            id={`${ids}-bio`}
                            rows={4}
                            maxLength={1200}
                            aria-describedby={`${ids}-bio-hint`}
                            value={draft.bio ?? ''}
                            onChange={(e) =>
                                setDraft({ ...draft, bio: e.target.value })
                            }
                        />
                        <InputError message={errors.bio} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-bio-es`}>
                            {t('providers.profile.bioEs')}
                        </Label>
                        <Textarea
                            id={`${ids}-bio-es`}
                            lang="es"
                            rows={4}
                            maxLength={1200}
                            value={draft.bio_es ?? ''}
                            onChange={(e) =>
                                setDraft({ ...draft, bio_es: e.target.value })
                            }
                        />
                        <InputError message={errors.bio_es} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-services`}>
                            {t('providers.profile.services')}
                        </Label>
                        <Input
                            id={`${ids}-services`}
                            value={services}
                            aria-describedby={`${ids}-list-hint`}
                            onChange={(e) => setServices(e.target.value)}
                        />
                        <InputError
                            message={errors.services ?? errors['services.0']}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-specialities`}>
                            {t('providers.profile.specialities')}
                        </Label>
                        <Input
                            id={`${ids}-specialities`}
                            value={specialities}
                            aria-describedby={`${ids}-list-hint`}
                            onChange={(e) => setSpecialities(e.target.value)}
                        />
                        <p
                            id={`${ids}-list-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {t('providers.profile.listHint')}
                        </p>
                        <InputError message={errors.specialities} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-response`}>
                            {t('providers.profile.response')}
                        </Label>
                        <select
                            id={`${ids}-response`}
                            className={select}
                            value={draft.response_hours ?? ''}
                            onChange={(e) =>
                                setDraft({
                                    ...draft,
                                    response_hours:
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value),
                                })
                            }
                        >
                            <option value="">
                                {t('providers.profile.responseNone')}
                            </option>
                            {builder.response_choices.map((h) => (
                                <option key={h} value={h}>
                                    {t('providers.profile.responseHours', {
                                        count: h,
                                    })}
                                </option>
                            ))}
                        </select>
                    </div>
                </section>

                <section aria-labelledby={`${ids}-resources`} className={card}>
                    <h2 id={`${ids}-resources`} className="font-semibold">
                        {t('providers.profile.resources')}
                    </h2>
                    {draft.resources.map((r, i) => (
                        <div
                            key={i}
                            className="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-rt-${i}`}>
                                    {t('providers.profile.resourceTitle', {
                                        n: i + 1,
                                    })}
                                </Label>
                                <Input
                                    id={`${ids}-rt-${i}`}
                                    value={r.title}
                                    maxLength={80}
                                    onChange={(e) =>
                                        rows(
                                            'resources',
                                            draft.resources.map((x, j) =>
                                                j === i
                                                    ? {
                                                          ...x,
                                                          title: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={errors[`resources.${i}.title`]}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-ru-${i}`}>
                                    {t('providers.profile.resourceUrl', {
                                        n: i + 1,
                                    })}
                                </Label>
                                <Input
                                    id={`${ids}-ru-${i}`}
                                    type="url"
                                    value={r.url}
                                    maxLength={300}
                                    onChange={(e) =>
                                        rows(
                                            'resources',
                                            draft.resources.map((x, j) =>
                                                j === i
                                                    ? {
                                                          ...x,
                                                          url: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={errors[`resources.${i}.url`]}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    rows(
                                        'resources',
                                        draft.resources.filter(
                                            (_, j) => j !== i,
                                        ),
                                    )
                                }
                            >
                                {t('providers.profile.removeResource', {
                                    n: i + 1,
                                })}
                            </Button>
                        </div>
                    ))}
                    {draft.resources.length < 10 ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                rows('resources', [
                                    ...draft.resources,
                                    { title: '', url: 'https://' },
                                ])
                            }
                        >
                            {t('providers.profile.addResource')}
                        </Button>
                    ) : null}
                </section>

                <section aria-labelledby={`${ids}-sessions`} className={card}>
                    <h2 id={`${ids}-sessions`} className="font-semibold">
                        {t('providers.profile.sessions')}
                    </h2>
                    {draft.sessions.map((s, i) => (
                        <div
                            key={i}
                            className="grid gap-2 sm:grid-cols-[1fr_8rem_auto] sm:items-end"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-st-${i}`}>
                                    {t('providers.profile.sessionTitle', {
                                        n: i + 1,
                                    })}
                                </Label>
                                <Input
                                    id={`${ids}-st-${i}`}
                                    value={s.title}
                                    maxLength={80}
                                    onChange={(e) =>
                                        rows(
                                            'sessions',
                                            draft.sessions.map((x, j) =>
                                                j === i
                                                    ? {
                                                          ...x,
                                                          title: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={errors[`sessions.${i}.title`]}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-sm-${i}`}>
                                    {t('providers.profile.sessionMinutes', {
                                        n: i + 1,
                                    })}
                                </Label>
                                <Input
                                    id={`${ids}-sm-${i}`}
                                    type="number"
                                    min={10}
                                    max={120}
                                    value={s.minutes}
                                    onChange={(e) =>
                                        rows(
                                            'sessions',
                                            draft.sessions.map((x, j) =>
                                                j === i
                                                    ? {
                                                          ...x,
                                                          minutes: Number(
                                                              e.target.value,
                                                          ),
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    rows(
                                        'sessions',
                                        draft.sessions.filter(
                                            (_, j) => j !== i,
                                        ),
                                    )
                                }
                            >
                                {t('providers.profile.removeSession', {
                                    n: i + 1,
                                })}
                            </Button>
                        </div>
                    ))}
                    {draft.sessions.length < 10 ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                rows('sessions', [
                                    ...draft.sessions,
                                    { title: '', minutes: 30 },
                                ])
                            }
                        >
                            {t('providers.profile.addSession')}
                        </Button>
                    ) : null}
                </section>

                {builder.issues.length > 0 ? (
                    <div
                        role="alert"
                        className="space-y-2 rounded-reos-md border border-reos-tone-danger-fg/40 bg-reos-tone-danger-bg p-4 text-reos-tone-danger-fg"
                    >
                        <p className="font-medium">
                            {t('providers.profile.blocked')}
                        </p>
                        <ul className="list-disc space-y-1 pl-5 text-base">
                            {builder.issues.map((issue, i) => (
                                <li key={i}>
                                    {issue.phrase ? `"${issue.phrase}" · ` : ''}
                                    {issue.citation}
                                    {issue.fix ? ` · ${issue.fix}` : ''}
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}
                {saved === 'published' ? (
                    <p
                        role="status"
                        className="text-base text-reos-tone-success-fg"
                    >
                        {t('providers.profile.published')}
                    </p>
                ) : null}
                {builder.published_at ? (
                    <p className="text-base text-muted-foreground">
                        {t('providers.profile.lastPublished', {
                            date: new Date(builder.published_at).toLocaleString(
                                i18n.language,
                            ),
                        })}
                    </p>
                ) : null}
                <InputError message={errors.form ?? errors.category} />
                <Button type="submit" disabled={busy !== null}>
                    {t('providers.profile.save')}
                </Button>
            </form>

            {builder.preview ? (
                <aside
                    aria-label={t('providers.profile.preview')}
                    className="space-y-2"
                >
                    <h2 className="font-semibold">
                        {t('providers.profile.preview')}
                    </h2>
                    <ProviderProfileCard provider={builder.preview} />
                </aside>
            ) : null}
        </div>
    );
}
