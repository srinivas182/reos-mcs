import { FileText, ShieldAlert, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Attachment } from '@/components/messages/types';
import { cn } from '@/lib/utils';

const size = (bytes: number, language: string) =>
    new Intl.NumberFormat(language, {
        style: 'unit',
        unit: bytes >= 1_048_576 ? 'megabyte' : 'kilobyte',
        maximumFractionDigits: 1,
    }).format(
        bytes >= 1_048_576 ? bytes / 1_048_576 : Math.max(1, bytes / 1024),
    );

/**
 * Files in a message (B6.1b): images as thumbnails and PDFs as links once
 * virus-scanned; "Checking for viruses…" until then; removed if infected.
 */
export function AttachmentList({
    attachments,
    mine,
}: {
    attachments: Attachment[];
    mine: boolean;
}) {
    const { t, i18n } = useTranslation();

    if (attachments.length === 0) {
        return null;
    }

    return (
        <ul
            className="flex flex-wrap gap-2"
            aria-label={t('messages.files.label')}
        >
            {attachments.map((file) => (
                <li key={file.id}>
                    {file.status === 'clean' && file.url && file.image ? (
                        <a
                            href={file.url}
                            target="_blank"
                            rel="noreferrer"
                            className="block"
                        >
                            <img
                                src={file.url}
                                alt={file.name}
                                className="max-h-48 max-w-full rounded-reos-md border border-border object-cover"
                            />
                        </a>
                    ) : (
                        <a
                            href={
                                file.status === 'clean' && file.url
                                    ? file.url
                                    : undefined
                            }
                            target="_blank"
                            rel="noreferrer"
                            aria-disabled={file.status !== 'clean'}
                            className={cn(
                                'flex min-h-12 items-center gap-2 rounded-reos-md border px-3 py-2 text-sm',
                                mine
                                    ? 'border-primary-foreground/40'
                                    : 'border-border bg-background',
                                file.status === 'clean' &&
                                    'underline-offset-4 hover:underline',
                            )}
                        >
                            {file.status === 'infected' ? (
                                <ShieldAlert
                                    aria-hidden
                                    className="size-5 shrink-0"
                                />
                            ) : file.status === 'pending' ? (
                                <ShieldCheck
                                    aria-hidden
                                    className="size-5 shrink-0"
                                />
                            ) : (
                                <FileText
                                    aria-hidden
                                    className="size-5 shrink-0"
                                />
                            )}
                            <span className="min-w-0">
                                <span className="block truncate font-medium">
                                    {file.name}
                                </span>
                                <span className="block">
                                    {file.status === 'pending'
                                        ? t('messages.files.checking')
                                        : file.status === 'infected'
                                          ? t('messages.files.infected')
                                          : size(file.size, i18n.language)}
                                </span>
                            </span>
                        </a>
                    )}
                </li>
            ))}
        </ul>
    );
}
