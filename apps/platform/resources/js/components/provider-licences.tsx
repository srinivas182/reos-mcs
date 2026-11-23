import { router } from '@inertiajs/react';
import { BadgeCheck, Clock, ExternalLink, XCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import { approve, reject } from '@/routes/people/provider-licences';
import { store } from '@/routes/provider-licences';

export type ProviderLicenceKind = 'trec_inspector' | 'tdi' | 'nmls';

export type ProviderLicenceStatus =
    | 'verified'
    | 'inactive'
    | 'review'
    | 'rejected';

export type ProviderLicenceRow = {
    id: number;
    kind: ProviderLicenceKind;
    number: string;
    status: ProviderLicenceStatus;
    name_on_record: string | null;
    licence_type: string | null;
    expires_on: string | null;
    checked_via: 'trec-licence' | 'tdi-lookup' | 'reviewer' | null;
    review_note: string | null;
    consumer_url: string | null;
};

export type ProviderLicenceReview = {
    id: number;
    person: { id: number; name: string | null };
    kind: ProviderLicenceKind;
    number: string;
    name_on_record: string | null;
    note: string | null;
    consumer_url: string | null;
    created_at: string | null;
};

const KINDS: ProviderLicenceKind[] = ['trec_inspector', 'tdi', 'nmls'];

const STATUS_ICON = {
    verified: BadgeCheck,
    inactive: XCircle,
    review: Clock,
    rejected: XCircle,
} as const;

/**
 * A provider's professional licence (B10.3), in Settings › Profile: TREC
 * inspector and TDI numbers are checked against the regulators' data in
 * seconds; NMLS IDs go to the REOS team.
 */
export function ProviderLicences({
    licences,
}: {
    licences: ProviderLicenceRow[];
}) {
    const { t, i18n } = useTranslation();
    const [kind, setKind] = useState<ProviderLicenceKind>('trec_inspector');
    const [number, setNumber] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const date = (d: string) =>
        new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
            new Date(`${d}T12:00:00`),
        );
    const check = async () => {
        setBusy(true);
        try {
            await postJson(store().url, { kind, number });
            setNumber('');
            setError(null);
            router.reload({ only: ['providerLicences'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="space-y-4">
            {licences.length === 0 ? (
                <p className="text-body text-muted-foreground">
                    {t('providers.licences.empty')}
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                    {licences.map((l) => {
                        const Icon = STATUS_ICON[l.status];

                        return (
                            <li key={l.id} className="space-y-1 p-4">
                                <p className="font-semibold">
                                    {t(`providers.licences.kinds.${l.kind}`)} ·
                                    #{l.number}
                                </p>
                                <p className="flex items-center gap-2 text-body">
                                    <Icon aria-hidden className="size-5" />
                                    {t(
                                        `providers.licences.statuses.${l.status}`,
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {[
                                        l.name_on_record
                                            ? t('providers.licences.onRecord', {
                                                  name: l.name_on_record,
                                              })
                                            : null,
                                        l.expires_on
                                            ? t('providers.licences.expires', {
                                                  date: date(l.expires_on),
                                              })
                                            : null,
                                        l.checked_via
                                            ? t(
                                                  `providers.licences.via.${l.checked_via}`,
                                              )
                                            : null,
                                        l.review_note,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </li>
                        );
                    })}
                </ul>
            )}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div className="grid gap-2">
                    <Label id="provider-licence-kind">
                        {t('providers.licences.kind')}
                    </Label>
                    <Select
                        value={kind}
                        onValueChange={(v) => setKind(v as ProviderLicenceKind)}
                    >
                        <SelectTrigger
                            aria-labelledby="provider-licence-kind"
                            className="w-full sm:w-80"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {KINDS.map((k) => (
                                <SelectItem key={k} value={k}>
                                    {t(`providers.licences.kinds.${k}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="provider-licence-number">
                        {t('providers.licences.number')}
                    </Label>
                    <Input
                        id="provider-licence-number"
                        value={number}
                        maxLength={40}
                        onChange={(e) => setNumber(e.target.value)}
                    />
                </div>
                <Button
                    disabled={busy || number.trim().length < 3}
                    onClick={() => void check()}
                >
                    {t('providers.licences.check')}
                </Button>
            </div>
            {error ? (
                <p role="alert" className="text-body text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

/**
 * Provider licences to review (B10.3), on People: NMLS IDs (open NMLS
 * Consumer Access from here) and numbers with no regulator match.
 */
export function ProviderLicenceReviewQueue({
    items,
}: {
    items: ProviderLicenceReview[];
}) {
    const { t } = useTranslation();
    const [rejecting, setRejecting] = useState<number | null>(null);
    const [note, setNote] = useState('');

    if (items.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="provider-licence-reviews"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
        >
            <h2 id="provider-licence-reviews" className="text-lg font-semibold">
                {t('providers.licences.reviews.title', { count: items.length })}
            </h2>
            <ul className="divide-y divide-border">
                {items.map((item) => (
                    <li key={item.id} className="space-y-3 py-3">
                        <p className="font-semibold">
                            {item.person.name ?? t('people.unnamed')} ·{' '}
                            {t(`providers.licences.kinds.${item.kind}`)} #
                            {item.number}
                        </p>
                        {item.note || item.name_on_record ? (
                            <p className="text-body text-muted-foreground">
                                {[
                                    item.name_on_record
                                        ? t('providers.licences.onRecord', {
                                              name: item.name_on_record,
                                          })
                                        : null,
                                    item.note,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        ) : null}
                        <div className="flex flex-wrap gap-3">
                            {item.consumer_url ? (
                                <Button asChild variant="outline">
                                    <a
                                        href={item.consumer_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink aria-hidden />
                                        {t('providers.licences.reviews.open')}
                                    </a>
                                </Button>
                            ) : null}
                            <Button
                                onClick={() =>
                                    router.post(
                                        approve(item.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('providers.licences.reviews.approve')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setRejecting(item.id)}
                            >
                                {t('providers.licences.reviews.reject')}
                            </Button>
                        </div>
                        {rejecting === item.id ? (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.post(
                                        reject(item.id).url,
                                        { note },
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRejecting(null);
                                                setNote('');
                                            },
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`provider-reject-${item.id}`}
                                    >
                                        {t('providers.licences.reviews.why')}
                                    </Label>
                                    <Textarea
                                        id={`provider-reject-${item.id}`}
                                        value={note}
                                        onChange={(e) =>
                                            setNote(e.target.value)
                                        }
                                        required
                                        maxLength={500}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={note.trim() === ''}
                                >
                                    {t('providers.licences.reviews.reject')}
                                </Button>
                            </form>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}
