import {
    CheckCircle2,
    Circle,
    CircleX,
    Clock,
    ExternalLink,
    FileSignature,
    FileText,
    Mail,
    MailOpen,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { postForm, postJson } from '@/lib/content';
import { connect } from '@/routes/connections';
import { store as storeFile } from '@/routes/deals/files';
import { sender, store as storeSignature } from '@/routes/deals/signatures';
import { TOOL_NAMES } from './types';
import type {
    DealFull,
    KeptFile,
    SignatureEnvelope,
    SignerStatus,
} from './types';

const SIGNER_ICONS: Record<SignerStatus, typeof Circle> = {
    waiting: Circle,
    sent: Mail,
    delivered: MailOpen,
    signed: CheckCircle2,
    declined: CircleX,
};

/** Opens DocuSign (its sender view, or REOS's demo of it) in this tab. */
function go(url: string) {
    window.location.assign(url);
}

/**
 * Signatures on a deal (B9.3, screen 14): send PDFs for signature through
 * the agent's own DocuSign, follow each signer, and open the signed copy
 * and certificate, which REOS files here when everyone has signed.
 */
export function DealSignatures({
    deal,
    onChanged,
}: {
    deal: DealFull;
    onChanged: () => void;
}) {
    const { t } = useTranslation();
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const resume = async (envelope: SignatureEnvelope) => {
        try {
            const { sender_url } = await postJson<{ sender_url: string }>(
                sender(envelope.uuid).url,
            );
            go(sender_url);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <section aria-labelledby="deal-signatures" className="space-y-3">
            <div className="space-y-1">
                <h3 id="deal-signatures" className="font-semibold">
                    {t('deals.signatures.title')}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {deal.can_sign
                        ? t('deals.signatures.intro')
                        : t('deals.signatures.connectHint')}
                </p>
            </div>
            {deal.can_sign ? (
                <Button onClick={() => setSending(true)}>
                    <FileSignature aria-hidden />
                    {t('deals.signatures.send')}
                </Button>
            ) : (
                <Button variant="outline" asChild>
                    <a href={connect('docusign').url}>
                        {t('deals.signatures.connect')}
                    </a>
                </Button>
            )}
            {error ? <InputError message={error} /> : null}

            {deal.signatures.length === 0 ? (
                <p className="text-body text-muted-foreground">
                    {t('deals.signatures.empty')}
                </p>
            ) : (
                <ul className="space-y-3">
                    {deal.signatures.map((envelope) => (
                        <li
                            key={envelope.uuid}
                            className="space-y-2 rounded-reos-lg border border-border p-3"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="font-medium">
                                    {envelope.subject}
                                </p>
                                <span
                                    className={
                                        envelope.status === 'completed'
                                            ? 'rounded-reos-pill bg-reos-tone-success-bg px-2.5 py-0.5 text-sm font-semibold text-reos-tone-success-fg'
                                            : envelope.status === 'declined' ||
                                                envelope.status === 'voided'
                                              ? 'rounded-reos-pill bg-reos-tone-danger-bg px-2.5 py-0.5 text-sm font-semibold text-reos-tone-danger-fg'
                                              : 'rounded-reos-pill bg-muted px-2.5 py-0.5 text-sm font-semibold text-muted-foreground'
                                    }
                                >
                                    {t(
                                        `deals.signatures.statuses.${envelope.status}`,
                                    )}
                                </span>
                            </div>
                            <ul className="space-y-1">
                                {envelope.signers.map((s) => {
                                    const Icon = SIGNER_ICONS[s.status];

                                    return (
                                        <li
                                            key={`${s.email}-${s.routing_order}`}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Icon
                                                aria-hidden
                                                className={
                                                    s.status === 'signed'
                                                        ? 'size-4 text-reos-tone-success-fg'
                                                        : s.status ===
                                                            'declined'
                                                          ? 'size-4 text-reos-tone-danger-fg'
                                                          : 'size-4 text-muted-foreground'
                                                }
                                            />
                                            <span>{s.name}</span>
                                            <span className="text-muted-foreground">
                                                ·{' '}
                                                {t(
                                                    `deals.signatures.signerStatuses.${s.status}`,
                                                )}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                            {envelope.filed_in ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('deals.signatures.filedIn', {
                                        tool:
                                            TOOL_NAMES[envelope.filed_in] ??
                                            envelope.filed_in,
                                    })}
                                </p>
                            ) : null}
                            {envelope.status === 'draft' ? (
                                <Button
                                    variant="outline"
                                    onClick={() => void resume(envelope)}
                                >
                                    <ExternalLink aria-hidden />
                                    {t('deals.signatures.continue')}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            {deal.kept_files.length > 0 ? (
                <div className="space-y-2">
                    <h4 className="font-semibold">
                        {t('deals.signatures.kept')}
                    </h4>
                    <ul className="divide-y divide-border">
                        {deal.kept_files.map((file) => (
                            <li
                                key={file.uuid}
                                className="flex min-h-12 items-center justify-between gap-3 py-2"
                            >
                                {file.url ? (
                                    <a
                                        href={file.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                                        aria-label={t('deals.signatures.open', {
                                            name: file.name,
                                        })}
                                    >
                                        <FileText
                                            aria-hidden
                                            className="size-4"
                                        />
                                        {file.name}
                                    </a>
                                ) : (
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <FileText
                                            aria-hidden
                                            className="size-4"
                                        />
                                        {file.name}
                                    </span>
                                )}
                                <span className="text-sm text-muted-foreground">
                                    {file.scan === 'clean'
                                        ? t(
                                              `deals.signatures.kinds.${file.kind}`,
                                          )
                                        : t(
                                              `deals.signatures.scan.${file.scan}`,
                                          )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {sending ? (
                <SendForSignatureDialog
                    deal={deal}
                    onClose={() => setSending(false)}
                    onUploaded={onChanged}
                />
            ) : null}
        </section>
    );
}

type Signer = {
    name: string;
    email: string;
    on: boolean;
    order: number;
};

/**
 * Send for signature (B9.3): the subject, the deal's PDFs in order (or a
 * new one), and the signers from the deal's parties with their signing
 * order. "Continue in DocuSign" makes a draft and opens DocuSign's sender
 * view, where the agent places the fields and presses Send.
 */
export function SendForSignatureDialog({
    deal,
    onClose,
    onUploaded,
}: {
    deal: DealFull;
    onClose: () => void;
    onUploaded: () => void;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [subject, setSubject] = useState(
        t('deals.signatures.subjectDefault', { property: deal.property }),
    );
    const [files, setFiles] = useState<KeptFile[]>(
        deal.kept_files.filter(
            (f) => f.kind !== 'certificate' && f.scan !== 'infected',
        ),
    );
    const [chosen, setChosen] = useState<string[]>([]);
    const [signers, setSigners] = useState<Signer[]>(
        deal.parties.map((p, i) => ({
            name: p.name,
            email: p.email ?? '',
            on: p.email !== null,
            order: i + 1,
        })),
    );
    const [busy, setBusy] = useState<'upload' | 'send' | null>(null);
    const [error, setError] = useState<string | null>(null);
    const signing = signers.filter((s) => s.on && s.email !== '');

    const upload = async (file: File) => {
        setBusy('upload');
        setError(null);
        try {
            const body = new FormData();
            body.append('file', file);
            const { data } = await postForm<{ data: KeptFile }>(
                storeFile(deal.uuid).url,
                body,
            );
            setFiles((current) => [data, ...current]);
            setChosen((current) => [...current, data.uuid]);
            onUploaded();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(null);
            if (input.current) {
                input.current.value = '';
            }
        }
    };

    const send = async () => {
        setBusy('send');
        setError(null);
        try {
            const { sender_url } = await postJson<{ sender_url: string }>(
                storeSignature(deal.uuid).url,
                {
                    subject,
                    files: chosen,
                    signers: signing.map((s) => ({
                        name: s.name,
                        email: s.email,
                        routing_order: s.order,
                    })),
                },
            );
            go(sender_url);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
            setBusy(null);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => (open ? null : onClose())}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('deals.signatures.send')}</DialogTitle>
                    <DialogDescription>
                        {t('deals.signatures.intro')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="signature-subject">
                        {t('deals.signatures.subject')}
                    </Label>
                    <Input
                        id="signature-subject"
                        value={subject}
                        maxLength={100}
                        onChange={(e) => setSubject(e.target.value)}
                    />
                </div>

                <fieldset className="grid gap-2">
                    <legend className="font-semibold">
                        {t('deals.signatures.files')}
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        {files.length === 0
                            ? t('deals.signatures.noFiles')
                            : t('deals.signatures.filesHint')}
                    </p>
                    {files.map((file) => (
                        <div
                            key={file.uuid}
                            className="flex min-h-12 items-center gap-3"
                        >
                            <Checkbox
                                id={`file-${file.uuid}`}
                                checked={chosen.includes(file.uuid)}
                                onCheckedChange={(on) =>
                                    setChosen((current) =>
                                        on === true
                                            ? [...current, file.uuid]
                                            : current.filter(
                                                  (id) => id !== file.uuid,
                                              ),
                                    )
                                }
                            />
                            <Label htmlFor={`file-${file.uuid}`}>
                                {file.name}
                            </Label>
                        </div>
                    ))}
                    <input
                        ref={input}
                        type="file"
                        accept="application/pdf,.pdf"
                        className="sr-only"
                        id="signature-upload"
                        aria-label={t('deals.signatures.upload')}
                        tabIndex={-1}
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                                void upload(file);
                            }
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy !== null}
                        onClick={() => input.current?.click()}
                    >
                        <Upload aria-hidden />
                        {busy === 'upload'
                            ? t('deals.signatures.uploading')
                            : t('deals.signatures.upload')}
                    </Button>
                </fieldset>

                <fieldset className="grid gap-2">
                    <legend className="font-semibold">
                        {t('deals.signatures.signers')}
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        {t('deals.signatures.signersHint')}
                    </p>
                    {signers.map((s, i) => (
                        <div
                            key={`${s.name}-${i}`}
                            className="flex min-h-12 flex-wrap items-center gap-3"
                        >
                            {s.email === '' ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('deals.signatures.noEmail', {
                                        name: s.name,
                                    })}
                                </p>
                            ) : (
                                <>
                                    <Checkbox
                                        id={`signer-${i}`}
                                        checked={s.on}
                                        onCheckedChange={(on) =>
                                            setSigners((current) =>
                                                current.map((c, j) =>
                                                    j === i
                                                        ? {
                                                              ...c,
                                                              on: on === true,
                                                          }
                                                        : c,
                                                ),
                                            )
                                        }
                                        aria-label={t(
                                            'deals.signatures.signerOn',
                                            { name: s.name },
                                        )}
                                    />
                                    <span className="flex-1">
                                        <span className="block font-medium">
                                            {s.name}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {s.email}
                                        </span>
                                    </span>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={10}
                                        className="w-20"
                                        value={s.order}
                                        aria-label={t(
                                            'deals.signatures.order',
                                            { name: s.name },
                                        )}
                                        onChange={(e) =>
                                            setSigners((current) =>
                                                current.map((c, j) =>
                                                    j === i
                                                        ? {
                                                              ...c,
                                                              order: Math.min(
                                                                  10,
                                                                  Math.max(
                                                                      1,
                                                                      Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ) || 1,
                                                                  ),
                                                              ),
                                                          }
                                                        : c,
                                                ),
                                            )
                                        }
                                    />
                                </>
                            )}
                        </div>
                    ))}
                </fieldset>

                {error ? <InputError message={error} /> : null}

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={onClose}>
                        {t('deals.signatures.cancel')}
                    </Button>
                    <Button
                        disabled={
                            busy !== null ||
                            chosen.length === 0 ||
                            signing.length === 0 ||
                            subject.trim().length < 3
                        }
                        onClick={() => void send()}
                    >
                        {busy === 'send' ? (
                            <Clock aria-hidden />
                        ) : (
                            <ExternalLink aria-hidden />
                        )}
                        {t('deals.signatures.prepare')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
