import { BellRing, CircleCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

/** Who has read an announcement (A16.3), from GET announcements/{id}/readers. */
export type Readers = {
    id: string;
    title: string;
    recipients: number;
    read: number;
    reminded_at: string | null;
    can_remind: boolean;
    people: { name: string; read_at: string | null }[];
};

function useDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(iso).toLocaleDateString(i18n.language, {
            dateStyle: 'medium',
        });
}

/**
 * "Who's read it" (A16.3): the people who haven't read an announcement
 * first, and Remind them, once a day at most.
 */
export function ReadersSheet({
    open,
    onOpenChange,
    load,
    onRemind,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    load: () => Promise<Readers>;
    onRemind: () => Promise<void>;
}) {
    const { t } = useTranslation();
    const date = useDate();
    const [readers, setReaders] = useState<Readers | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (open) {
            void load().then(setReaders);
        }
    }, [open, load]);

    const remind = async () => {
        setBusy(true);

        try {
            await onRemind();
            setReaders(await load());
        } finally {
            setBusy(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="overflow-y-auto p-6">
                <SheetHeader className="p-0">
                    <SheetTitle>{t('announcements.receipts.title')}</SheetTitle>
                    <SheetDescription>
                        {readers
                            ? `${readers.title} · ${t('announcements.readBy', { read: readers.read, total: readers.recipients })}`
                            : t('announcements.receipts.loading')}
                    </SheetDescription>
                </SheetHeader>
                {readers ? (
                    <div className="mt-4 space-y-4">
                        {readers.read === readers.recipients ? (
                            <p className="flex items-center gap-2 text-base">
                                <CircleCheck
                                    aria-hidden
                                    className="size-5 text-reos-tone-success-fg"
                                />
                                {t('announcements.receipts.allRead')}
                            </p>
                        ) : (
                            <>
                                <p className="text-base text-muted-foreground">
                                    {t('announcements.receipts.description')}
                                </p>
                                <Button
                                    onClick={() => void remind()}
                                    disabled={!readers.can_remind || busy}
                                >
                                    <BellRing aria-hidden />
                                    {t('announcements.receipts.remind')}
                                </Button>
                                {readers.reminded_at ? (
                                    <p className="text-base text-muted-foreground">
                                        {t(
                                            'announcements.receipts.remindedAt',
                                            {
                                                date: date(readers.reminded_at),
                                            },
                                        )}
                                    </p>
                                ) : null}
                            </>
                        )}
                        <ul className="divide-y divide-border">
                            {readers.people.map((p, i) => (
                                <li
                                    key={`${p.name}-${i}`}
                                    className="flex items-center justify-between gap-3 py-3"
                                >
                                    <span>{p.name}</span>
                                    <span className="text-base text-muted-foreground">
                                        {p.read_at
                                            ? t(
                                                  'announcements.receipts.readOn',
                                                  {
                                                      date: date(p.read_at),
                                                  },
                                              )
                                            : t(
                                                  'announcements.receipts.unread',
                                              )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}
            </SheetContent>
        </Sheet>
    );
}
