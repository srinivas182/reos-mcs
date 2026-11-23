import { router } from '@inertiajs/react';
import { AlertTriangle, FileText } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { postForm, postJson } from '@/lib/content';
import { read, store } from '@/routes/certificates';

/** What the reader found on a certificate (B10.3b). */
export type CertificateRead = {
    upload: string;
    reader: string | null;
    name: string | null;
    course: string | null;
    hours: number | null;
    completed_on: string | null;
    provider: string | null;
    confidence: Record<string, number>;
    name_matches: boolean | null;
    states: string[];
};

/**
 * Add a CE certificate (B10.3b): upload a photo or one-page PDF, REOS reads
 * it (Textract), the person checks and corrects the fields, then saves it
 * as a completion for one of their licence states.
 */
export function CertificateDialog({
    open,
    onOpenChange,
    initial = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** A read to start from (stories). */
    initial?: CertificateRead | null;
}) {
    const { t } = useTranslation();
    const [file, setFile] = useState<File | null>(null);
    const [found, setFound] = useState<CertificateRead | null>(initial);
    const [values, setValues] = useState(() => fromRead(initial));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const reset = (next: boolean) => {
        if (!next) {
            setFile(null);
            setFound(initial);
            setValues(fromRead(initial));
            setError(null);
        }
        onOpenChange(next);
    };
    const readIt = async () => {
        if (!file) return;
        setBusy(true);
        try {
            const form = new FormData();
            form.append('file', file);
            const { data } = await postForm<{ data: CertificateRead }>(
                read().url,
                form,
            );
            setFound(data);
            setValues(fromRead(data));
            setError(null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const save = async () => {
        if (!found) return;
        setBusy(true);
        try {
            await postJson(store().url, {
                upload: found.upload,
                ...values,
                hours: Number(values.hours),
            });
            toast.success(t('certificates.saved'));
            reset(false);
            router.reload({ only: ['licences'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const field = (key: keyof typeof values) => ({
        value: values[key],
        onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
            setValues((v) => ({ ...v, [key]: e.target.value })),
    });
    const sure = (key: string) =>
        found?.confidence[key] !== undefined
            ? t('certificates.confidence', {
                  percent: Math.round(found.confidence[key] ?? 0),
              })
            : null;

    return (
        <Dialog open={open} onOpenChange={reset}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('certificates.title')}</DialogTitle>
                    <DialogDescription>
                        {t('certificates.description')}
                    </DialogDescription>
                </DialogHeader>
                {found === null ? (
                    <div className="grid gap-2">
                        <Label htmlFor="certificate-file">
                            {t('certificates.file')}
                        </Label>
                        <Input
                            id="certificate-file"
                            type="file"
                            accept="application/pdf,image/jpeg,image/png"
                            onChange={(e) =>
                                setFile(e.target.files?.[0] ?? null)
                            }
                        />
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <p className="flex items-center gap-2 font-semibold">
                                <FileText aria-hidden className="size-5" />
                                {t('certificates.review')}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {found.reader === null
                                    ? t('certificates.unread')
                                    : t('certificates.reviewHint')}
                            </p>
                        </div>
                        {found.name ? (
                            <p className="text-base">
                                {t('certificates.nameOnCertificate', {
                                    name: found.name,
                                })}
                            </p>
                        ) : null}
                        {found.name_matches === false ? (
                            <p
                                role="alert"
                                className="flex items-start gap-2 rounded-reos-md bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg"
                            >
                                <AlertTriangle
                                    aria-hidden
                                    className="mt-0.5 size-5 shrink-0"
                                />
                                {t('certificates.nameMismatch')}
                            </p>
                        ) : null}
                        <div className="grid gap-2">
                            <Label id="certificate-state">
                                {t('certificates.state')}
                            </Label>
                            <Select
                                value={values.state}
                                onValueChange={(state) =>
                                    setValues((v) => ({ ...v, state }))
                                }
                            >
                                <SelectTrigger aria-labelledby="certificate-state">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {found.states.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {(
                            [
                                ['title', 'course', 'course', 'text'],
                                ['hours', 'hours', 'hours', 'number'],
                                [
                                    'completed_on',
                                    'completedOn',
                                    'completed_on',
                                    'date',
                                ],
                                ['provider', 'provider', 'provider', 'text'],
                            ] as const
                        ).map(([key, label, confidenceKey, type]) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={`certificate-${key}`}>
                                    {t(`certificates.${label}`)}
                                </Label>
                                <Input
                                    id={`certificate-${key}`}
                                    type={type}
                                    step={type === 'number' ? '0.5' : undefined}
                                    aria-describedby={
                                        sure(confidenceKey)
                                            ? `certificate-${key}-sure`
                                            : undefined
                                    }
                                    {...field(key)}
                                />
                                {sure(confidenceKey) ? (
                                    <p
                                        id={`certificate-${key}-sure`}
                                        className="text-sm text-muted-foreground"
                                    >
                                        {sure(confidenceKey)}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                    </div>
                )}
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                <DialogFooter>
                    {found === null ? (
                        <Button
                            disabled={!file || busy}
                            onClick={() => void readIt()}
                        >
                            {busy
                                ? t('certificates.reading')
                                : t('certificates.read')}
                        </Button>
                    ) : (
                        <Button
                            disabled={
                                busy ||
                                values.title.trim() === '' ||
                                values.hours === '' ||
                                values.completed_on === '' ||
                                values.state === ''
                            }
                            onClick={() => void save()}
                        >
                            {t('certificates.save')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function fromRead(found: CertificateRead | null) {
    return {
        state: found?.states[0] ?? '',
        title: found?.course ?? '',
        hours:
            found?.hours === null || found?.hours === undefined
                ? ''
                : String(found.hours),
        completed_on: found?.completed_on ?? '',
        provider: found?.provider ?? '',
    };
}
