import { Form } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store } from '@/routes/privacy';

export type MyPrivacyRequest = {
    id: number;
    kind: 'access' | 'correct' | 'delete';
    status: 'open' | 'in_progress' | 'hold' | 'done' | 'declined' | 'withdrawn';
    created_at: string | null;
    due_at: string;
    response: string | null;
    download: string | null;
};

const tone: Record<MyPrivacyRequest['status'], string> = {
    open: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    in_progress: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    hold: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    done: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    declined: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    withdrawn: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/**
 * Your data (Settings › Profile, A4.3b): the Texas privacy rights in plain
 * words. Download what REOS holds, ask for a correction, and see each
 * request with the date REOS answers by. Deleting the account is below.
 */
export function YourData({ requests }: { requests: MyPrivacyRequest[] }) {
    const { t, i18n } = useTranslation();
    const [correcting, setCorrecting] = useState(false);
    const date = (iso: string | null) =>
        iso === null
            ? ''
            : new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: 'medium',
              }).format(new Date(iso));

    return (
        <section className="space-y-6">
            <Heading
                variant="small"
                title={t('privacy.title')}
                description={t('privacy.description')}
            />

            <div className="flex flex-wrap gap-3">
                <Form {...store.form()} options={{ preserveScroll: true }}>
                    {({ processing }) => (
                        <>
                            <input type="hidden" name="kind" value="access" />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                <Download aria-hidden className="size-5" />
                                {t('privacy.download')}
                            </Button>
                        </>
                    )}
                </Form>
                <Button
                    variant="outline"
                    onClick={() => setCorrecting((open) => !open)}
                    aria-expanded={correcting}
                >
                    <FileText aria-hidden className="size-5" />
                    {t('privacy.correct')}
                </Button>
            </div>

            {correcting ? (
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setCorrecting(false)}
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="kind" value="correct" />
                            <div className="grid gap-2">
                                <Label htmlFor="privacy-details">
                                    {t('privacy.whatToCorrect')}
                                </Label>
                                <Textarea
                                    id="privacy-details"
                                    name="details"
                                    rows={4}
                                    required
                                    aria-invalid={Boolean(errors.details)}
                                />
                                <InputError
                                    message={errors.details ?? errors.kind}
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {t('privacy.send')}
                            </Button>
                        </>
                    )}
                </Form>
            ) : null}

            <p className="text-sm text-muted-foreground">
                {t('privacy.promise')}
            </p>

            {requests.length > 0 ? (
                <div className="space-y-2">
                    <h3 className="text-sm font-semibold">
                        {t('privacy.yourRequests')}
                    </h3>
                    <ul className="divide-y divide-border rounded-reos-md border border-border">
                        {requests.map((request) => (
                            <li
                                key={request.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {t(`privacy.kinds.${request.kind}`)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {[
                                            'open',
                                            'in_progress',
                                            'hold',
                                        ].includes(request.status)
                                            ? t('privacy.answerBy', {
                                                  date: date(request.due_at),
                                              })
                                            : t('privacy.asked', {
                                                  date: date(
                                                      request.created_at,
                                                  ),
                                              })}
                                    </p>
                                    {request.response ? (
                                        <p className="text-sm">
                                            {request.response}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex items-center gap-3">
                                    <span
                                        className={cn(
                                            'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                            tone[request.status],
                                        )}
                                    >
                                        {t(
                                            `privacy.statuses.${request.status}`,
                                        )}
                                    </span>
                                    {request.download ? (
                                        <Button asChild variant="outline">
                                            <a href={request.download}>
                                                {t('privacy.getFile')}
                                            </a>
                                        </Button>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}
