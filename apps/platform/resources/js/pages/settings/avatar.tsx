import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/avatar';

type Consent = {
    status: 'given' | 'withdrawn' | 'ended';
    version: string;
    given_at: string | null;
    withdrawn_at: string | null;
    lessons_due_by: string | null;
    sample_url: string | null;
    current: boolean;
};

const day = (value: string | null, language: string): string =>
    value === null
        ? ''
        : new Intl.DateTimeFormat(language, {
              dateStyle: 'medium',
              timeZone: 'UTC',
          }).format(
              new Date(value.length === 10 ? `${value}T00:00:00Z` : value),
          );

/**
 * AI avatar consent for instructors (A7.1b; Instructor journey: avatar
 * consent and the 2-minute sample): what they agree to, with its version;
 * agree with a sample; withdraw at any time.
 */
export default function AvatarConsentSettings({
    school,
    version,
    consent,
}: {
    school: { name: string };
    version: string;
    consent: Consent | null;
}) {
    const { t, i18n } = useTranslation();
    const form = useForm<{ agree: boolean; sample: File | null }>({
        agree: false,
        sample: null,
    });
    const given = consent?.status === 'given';

    const status = (): string => {
        if (consent === null) {
            return t('avatarConsent.statusNone');
        }

        if (consent.status === 'given') {
            return consent.current
                ? t('avatarConsent.statusGiven', {
                      date: day(consent.given_at, i18n.language),
                      version: consent.version,
                  })
                : t('avatarConsent.statusOld');
        }

        return consent.status === 'withdrawn'
            ? t('avatarConsent.statusWithdrawn', {
                  date: day(consent.withdrawn_at, i18n.language),
                  due: day(consent.lessons_due_by, i18n.language),
              })
            : t('avatarConsent.statusEnded', {
                  date: day(consent.withdrawn_at, i18n.language),
              });
    };

    return (
        <>
            <Head title={t('avatarConsent.title')} />
            <h1 className="sr-only">{t('avatarConsent.title')}</h1>
            <div className="space-y-10">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('avatarConsent.title')}
                        description={t('avatarConsent.description', {
                            school: school.name,
                        })}
                    />
                    <p className="rounded-reos-md bg-reos-tone-info-bg p-4 text-base text-reos-tone-info-fg">
                        {status()}
                    </p>
                    {given && consent?.sample_url ? (
                        <video
                            controls
                            preload="metadata"
                            className="w-full rounded-reos-md bg-black"
                            src={consent.sample_url}
                            aria-label={t('avatarConsent.sample')}
                        />
                    ) : null}
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('avatarConsent.templateTitle', { version })}
                    </h2>
                    <ul className="list-disc space-y-2 pl-6 text-base">
                        <li>
                            {t('avatarConsent.terms.school', {
                                school: school.name,
                            })}
                        </li>
                        <li>{t('avatarConsent.terms.approve')}</li>
                        <li>{t('avatarConsent.terms.vendor')}</li>
                        <li>{t('avatarConsent.terms.withdraw')}</li>
                        <li>{t('avatarConsent.terms.pay')}</li>
                    </ul>
                </section>

                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(store().url, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <Heading
                        variant="small"
                        title={t('avatarConsent.sampleTitle')}
                        description={t('avatarConsent.sampleHint')}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="avatar-sample">
                            {t('avatarConsent.sample')}
                        </Label>
                        <Input
                            id="avatar-sample"
                            type="file"
                            accept="video/mp4,video/quicktime,video/webm"
                            onChange={(e) =>
                                form.setData(
                                    'sample',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.sample} />
                    </div>
                    <label className="flex min-h-12 items-center gap-3 text-base">
                        <Checkbox
                            checked={form.data.agree}
                            onCheckedChange={(on) =>
                                form.setData('agree', on === true)
                            }
                        />
                        {t('avatarConsent.agree')}
                    </label>
                    <InputError message={form.errors.agree} />
                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.agree ||
                                form.data.sample === null
                            }
                        >
                            {given
                                ? t('avatarConsent.replace')
                                : t('avatarConsent.give')}
                        </Button>
                        {given ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.delete(destroy().url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                {t('avatarConsent.withdraw')}
                            </Button>
                        ) : null}
                    </div>
                </form>
            </div>
        </>
    );
}
